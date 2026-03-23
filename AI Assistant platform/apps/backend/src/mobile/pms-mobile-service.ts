import {
  AvailabilityFilters,
  AvailabilityPropertyGroup,
  AvailabilityRoomAction,
  AvailabilityRoomCard,
  MobilePmsAvailabilityResponse,
  MobilePmsBootstrapBedConfiguration,
  MobilePmsBootstrapCategory,
  MobilePmsBootstrapProperty,
  MobilePmsBootstrapResponse,
  MobilePmsBootstrapRoom,
  MobilePmsRequestContext,
  ReservationDraftPreview
} from "@vlv-ai/shared";

import { MariaDbPool } from "../db/mariadb-pool";
import { ActivityLogService } from "../logging/activity-log-service";
import { AuthorizationError, ValidationError } from "../shared/errors";

interface AvailabilitySearchInput {
  propertyCode?: string | null;
  dateStart: string;
  dateEnd?: string | null;
  nights?: number | null;
  people?: number | null;
  visibleWindowDays?: number;
}

interface AccessContext {
  permissionCodes: string[];
  allowedPropertyCodes: string[];
  isOwner: boolean;
  authzMode: "audit" | "enforce";
}

interface QuoteResult {
  totalCents: number | null;
  avgNightlyCents: number | null;
}

interface PropertyCatalog {
  properties: MobilePmsBootstrapProperty[];
  categories: MobilePmsBootstrapCategory[];
  rooms: MobilePmsBootstrapRoom[];
  bedConfigurations: MobilePmsBootstrapBedConfiguration[];
}

interface PricingContext {
  propertyId: number;
  rateplanId: number;
  roomId: number;
  categoryId: number;
}

const DEFAULT_VISIBLE_WINDOW_DAYS = 30;
const VIEW_PERMISSION_CODE = "calendar.view";

export class PmsMobileService {
  constructor(
    private readonly mariaDbPool: MariaDbPool,
    private readonly activityLogService: ActivityLogService
  ) {}

  async getBootstrap(context: MobilePmsRequestContext): Promise<MobilePmsBootstrapResponse> {
    const access = await this.loadAccessContext(context);
    this.assertAccess(access, null, VIEW_PERMISSION_CODE);

    const catalog = await this.loadPropertyCatalog(context, access);

    return {
      context: this.toResponseContext(context, access),
      defaults: {
        propertyCode: null,
        dateStart: todayYmd(),
        dateEnd: null,
        nights: null,
        people: null,
        visibleWindowDays: DEFAULT_VISIBLE_WINDOW_DAYS
      },
      properties: catalog.properties,
      categories: catalog.categories,
      rooms: catalog.rooms,
      bedConfigurations: catalog.bedConfigurations
    };
  }

  async searchAvailability(
    context: MobilePmsRequestContext,
    rawInput: AvailabilitySearchInput
  ): Promise<MobilePmsAvailabilityResponse> {
    const startedAt = Date.now();
    const access = await this.loadAccessContext(context);
    const filters = this.normalizeFilters(rawInput);
    this.assertAccess(access, filters.propertyCode, VIEW_PERMISSION_CODE);

    const catalog = await this.loadPropertyCatalog(context, access);
    const properties = filters.propertyCode
      ? catalog.properties.filter((property) => property.code === filters.propertyCode)
      : catalog.properties;

    const groups: AvailabilityPropertyGroup[] = [];
    let totalMatches = 0;

    for (const property of properties) {
      await this.assertProcedureAuthorization(context, access, VIEW_PERMISSION_CODE, property.code);
      const group = await this.buildPropertyAvailabilityGroup(context, property, catalog, filters);
      if (!group || group.rooms.length === 0) {
        continue;
      }
      groups.push(group);
      totalMatches += group.rooms.length;
    }

    await this.activityLogService.info(
      "mobile.pms.availability.search",
      "Calculated mobile PMS availability.",
      {
        tenantId: context.tenantId,
        companyCode: context.companyCode,
        actorUserId: context.actorUserId,
        propertyCode: filters.propertyCode,
        people: filters.people,
        nights: filters.nights,
        totalMatches,
        propertyCount: groups.length,
        durationMs: Date.now() - startedAt
      },
      "pms"
    );

    return {
      context: this.toResponseContext(context, access),
      filtersApplied: filters,
      totalMatches,
      groups
    };
  }

  private async buildPropertyAvailabilityGroup(
    context: MobilePmsRequestContext,
    property: MobilePmsBootstrapProperty,
    catalog: PropertyCatalog,
    filters: AvailabilityFilters
  ): Promise<AvailabilityPropertyGroup | null> {
    const calendarRecordsets = await this.callStandardProcedure("sp_property_room_calendar", [
      property.code,
      filters.dateStart,
      filters.visibleWindowDays
    ]);
    const dayRows = this.asRows(calendarRecordsets[1]);
    const eventRows = this.asRows(calendarRecordsets[2]);
    const roomRows = this.asRows(calendarRecordsets[0]);
    const occupancyMap = buildOccupancyMap(dayRows, eventRows);

    const categoryMap = new Map(
      catalog.categories
        .filter((category) => category.propertyCode === property.code)
        .map((category) => [category.code, category] as const)
    );
    const roomMetadataMap = new Map(
      catalog.rooms
        .filter((room) => room.propertyCode === property.code)
        .map((room) => [room.code, room] as const)
    );

    let allowedRoomKeys: Set<string> | null = null;
    if (filters.people !== null && filters.nights !== null) {
      const availabilityRecordsets = await this.callStandardProcedure("sp_search_availability", [
        context.companyCode,
        filters.dateStart,
        filters.nights,
        filters.people
      ]);
      allowedRoomKeys = new Set(
        this.asRows(availabilityRecordsets[0])
          .map((row) => {
            const propertyCode =
              normalizeCode(firstDefined(row, ["property_code", "propertyCode"])) ?? property.code;
            const roomCode = normalizeCode(firstDefined(row, ["room_code", "roomCode", "code"]));
            if (!roomCode) {
              return null;
            }
            return `${propertyCode}:${roomCode}`;
          })
          .filter((value): value is string => Boolean(value))
      );
    }

    const cards = await Promise.all(
      roomRows.map(async (roomRow) => {
        const roomCode = normalizeCode(firstDefined(roomRow, ["room_code", "code"]));
        if (!roomCode) {
          return null;
        }

        const room = roomMetadataMap.get(roomCode);
        if (!room) {
          return null;
        }

        const category = categoryMap.get(room.categoryCode);
        const continuousNights = countContinuousNights(room.id, roomCode, dayRows, occupancyMap, 0);
        if (continuousNights <= 0) {
          return null;
        }
        if (filters.nights !== null && continuousNights < filters.nights) {
          return null;
        }
        if (filters.people !== null && !roomSupportsPeople(room, category, filters.people)) {
          return null;
        }
        if (
          allowedRoomKeys &&
          !allowedRoomKeys.has(`${property.code}:${roomCode}`) &&
          !allowedRoomKeys.has(roomCode)
        ) {
          return null;
        }

        const pricingContext = this.resolvePricingContext(room, category, property);
        if (!pricingContext) {
          await this.activityLogService.warn(
            "mobile.pms.availability.missing_pricing_context",
            "Skipping room without pricing context.",
            {
              propertyCode: property.code,
              roomCode
            },
            "pms"
          );
          return null;
        }

        const oneNightQuote = await this.quoteStay(
          pricingContext,
          filters.dateStart,
          addNights(filters.dateStart, 1)
        );
        if (oneNightQuote.totalCents === null) {
          return null;
        }

        let requestedStayTotalCents: number | null = null;
        let continuousStayTotalCents: number | null = null;

        if (filters.nights !== null) {
          const requestedQuote = await this.quoteStay(
            pricingContext,
            filters.dateStart,
            addNights(filters.dateStart, filters.nights)
          );
          requestedStayTotalCents = requestedQuote.totalCents;
          if (requestedStayTotalCents === null) {
            return null;
          }
        } else {
          const continuousQuote = await this.quoteStay(
            pricingContext,
            filters.dateStart,
            addNights(filters.dateStart, continuousNights)
          );
          continuousStayTotalCents = continuousQuote.totalCents;
        }

        const actions = this.buildActions(
          context,
          property,
          room,
          continuousNights,
          filters,
          oneNightQuote,
          requestedStayTotalCents,
          continuousStayTotalCents
        );

        const card: AvailabilityRoomCard = {
          propertyCode: property.code,
          propertyName: property.name,
          roomCode: room.code,
          roomName: room.name,
          roomId: room.id,
          categoryCode: room.categoryCode,
          categoryName: room.categoryName,
          categoryId: room.categoryId ?? 0,
          requestedStartDate: filters.dateStart,
          visibleContinuousNights: continuousNights,
          requestedNights: filters.nights,
          people: filters.people,
          currency: property.currency,
          nightlyPriceCents: oneNightQuote.totalCents,
          requestedStayTotalCents,
          continuousStayTotalCents,
          capacityTotal: room.capacityTotal,
          maxAdults: room.maxAdults,
          maxChildren: room.maxChildren,
          actions
        };

        return card;
      })
    );

    const rooms = cards
      .filter((card): card is AvailabilityRoomCard => Boolean(card))
      .sort((left, right) => left.roomName.localeCompare(right.roomName, "es-MX"));

    if (rooms.length === 0) {
      return null;
    }

    return {
      propertyCode: property.code,
      propertyName: property.name,
      currency: property.currency,
      roomCount: rooms.length,
      rooms
    };
  }

  private buildActions(
    context: MobilePmsRequestContext,
    property: MobilePmsBootstrapProperty,
    room: MobilePmsBootstrapRoom,
    continuousNights: number,
    filters: AvailabilityFilters,
    oneNightQuote: QuoteResult,
    requestedStayTotalCents: number | null,
    continuousStayTotalCents: number | null
  ): AvailabilityRoomAction[] {
    const actions: AvailabilityRoomAction[] = [];

    const addAction = (
      kind: AvailabilityRoomAction["kind"],
      label: string,
      nights: number,
      totalCents: number | null
    ) => {
      const draft: ReservationDraftPreview = {
        source: "mobile.availability.phase1",
        action: kind,
        tenantId: context.tenantId,
        companyCode: context.companyCode,
        actorUserId: context.actorUserId,
        propertyCode: property.code,
        propertyName: property.name,
        roomCode: room.code,
        roomName: room.name,
        categoryCode: room.categoryCode,
        categoryName: room.categoryName,
        checkIn: filters.dateStart,
        checkOut: addNights(filters.dateStart, nights),
        nights,
        people: filters.people,
        currency: property.currency,
        nightlyPriceCents: oneNightQuote.avgNightlyCents ?? oneNightQuote.totalCents,
        totalCents,
        visibleContinuousNights: continuousNights
      };
      actions.push({ kind, label, draft });
    };

    if (filters.nights !== null) {
      addAction(
        "book_requested_stay",
        "Reservar",
        filters.nights,
        requestedStayTotalCents
      );
      return actions;
    }

    addAction("book_one_night", "Reservar 1 noche", 1, oneNightQuote.totalCents);
    if (continuousNights > 1) {
      addAction(
        "book_continuous_stay",
        `Reservar ${continuousNights} noches`,
        continuousNights,
        continuousStayTotalCents
      );
    }

    return actions;
  }

  private resolvePricingContext(
    room: MobilePmsBootstrapRoom,
    category: MobilePmsBootstrapCategory | undefined,
    property: MobilePmsBootstrapProperty
  ): PricingContext | null {
    const rateplanId = room.rateplanId ?? category?.rateplanId ?? null;
    const categoryId = room.categoryId ?? category?.id ?? null;
    if (!rateplanId || !categoryId || room.id <= 0 || property.id <= 0) {
      return null;
    }

    return {
      propertyId: property.id,
      rateplanId,
      roomId: room.id,
      categoryId
    };
  }

  private async quoteStay(
    pricingContext: PricingContext,
    checkIn: string,
    checkOut: string
  ): Promise<QuoteResult> {
    const result = await this.callProcedureWithOutputs(
      "sp_rateplan_calc_total",
      [
        pricingContext.propertyId,
        pricingContext.rateplanId,
        pricingContext.roomId,
        pricingContext.categoryId,
        checkIn,
        checkOut
      ],
      ["total_cents", "avg_nightly_cents", "breakdown_json"]
    );

    return {
      totalCents: asNumber(result.outputVariables?.total_cents),
      avgNightlyCents: asNumber(result.outputVariables?.avg_nightly_cents)
    };
  }

  private normalizeFilters(rawInput: AvailabilitySearchInput): AvailabilityFilters {
    const propertyCode = normalizeCode(rawInput.propertyCode ?? null) ?? null;
    const dateStart = rawInput.dateStart;
    const visibleWindowDays = clampNumber(
      rawInput.visibleWindowDays ?? DEFAULT_VISIBLE_WINDOW_DAYS,
      1,
      DEFAULT_VISIBLE_WINDOW_DAYS
    );
    const dateEnd = rawInput.dateEnd ?? null;
    const derivedNights = dateEnd ? diffNights(dateStart, dateEnd) : null;
    const nights = derivedNights ?? rawInput.nights ?? null;
    const people = rawInput.people ?? null;

    if (derivedNights !== null && derivedNights <= 0) {
      throw new ValidationError("dateEnd must be after dateStart.");
    }
    if (nights !== null && nights > visibleWindowDays) {
      throw new ValidationError("nights cannot exceed the visible window.");
    }

    return {
      propertyCode,
      dateStart,
      dateEnd,
      nights,
      people,
      visibleWindowDays
    };
  }

  private async loadPropertyCatalog(
    context: MobilePmsRequestContext,
    access: AccessContext
  ): Promise<PropertyCatalog> {
    const recordsets = await this.callStandardProcedure("sp_portal_property_data", [
      context.companyCode,
      null,
      1,
      null
    ]);
    const propertyRows = this.asRows(recordsets[0]);
    const categoryRows = this.asRows(recordsets[3]);
    const roomRows = this.asRows(recordsets[4]);
    const bedRows = this.asRows(recordsets[5]);
    const allowedSet = new Set(access.allowedPropertyCodes);

    const properties = propertyRows
      .map((row) => ({
        id: asNumber(firstDefined(row, ["id_property"])) ?? 0,
        code: normalizeCode(firstDefined(row, ["code", "property_code"])) ?? "",
        name: asString(firstDefined(row, ["name", "property_name"])),
        currency: asString(firstDefined(row, ["currency"]), "MXN")
      }))
      .filter((property) => property.code !== "")
      .filter((property) => access.isOwner || allowedSet.has(property.code))
      .sort((left, right) => left.name.localeCompare(right.name, "es-MX"));

    const propertySet = new Set(properties.map((property) => property.code));

    const categories = categoryRows
      .map((row) => ({
        id: asNumber(firstDefined(row, ["id_category"])) ?? 0,
        propertyCode:
          normalizeCode(firstDefined(row, ["property_code", "code_property"])) ?? "",
        code: normalizeCode(firstDefined(row, ["category_code", "code"])) ?? "",
        name: asString(firstDefined(row, ["category_name", "name"])),
        maxOccupancy: asNumber(firstDefined(row, ["max_occupancy", "capacity_total"])),
        rateplanId: asNumber(firstDefined(row, ["id_rateplan", "rateplan_id"]))
      }))
      .filter((category) => category.propertyCode !== "" && propertySet.has(category.propertyCode));

    const rooms = roomRows
      .map((row) => ({
        id: asNumber(firstDefined(row, ["id_room"])) ?? 0,
        propertyCode:
          normalizeCode(firstDefined(row, ["property_code", "code_property"])) ?? "",
        code: normalizeCode(firstDefined(row, ["room_code", "code"])) ?? "",
        name: asString(firstDefined(row, ["room_name", "name"])),
        categoryCode: normalizeCode(firstDefined(row, ["category_code"])) ?? "",
        categoryName: asString(firstDefined(row, ["category_name"])),
        categoryId: asNumber(firstDefined(row, ["id_category"])),
        rateplanId: asNumber(firstDefined(row, ["id_rateplan", "rateplan_id"])),
        capacityTotal: asNumber(firstDefined(row, ["capacity_total", "max_occupancy"])),
        maxAdults: asNumber(firstDefined(row, ["max_adults"])),
        maxChildren: asNumber(firstDefined(row, ["max_children"]))
      }))
      .filter((room) => room.propertyCode !== "" && room.code !== "" && propertySet.has(room.propertyCode));

    const roomSet = new Set(rooms.map((room) => `${room.propertyCode}:${room.code}`));

    const bedConfigurations = bedRows
      .map((row) => ({
        propertyCode:
          normalizeCode(firstDefined(row, ["property_code", "code_property"])) ?? "",
        roomCode: normalizeCode(firstDefined(row, ["room_code", "code"])) ?? "",
        label: asString(firstDefined(row, ["label", "bed_configuration", "bed_config"]))
      }))
      .filter(
        (row) =>
          row.propertyCode !== "" &&
          row.roomCode !== "" &&
          row.label !== "" &&
          roomSet.has(`${row.propertyCode}:${row.roomCode}`)
      );

    return {
      properties,
      categories,
      rooms,
      bedConfigurations
    };
  }

  private async loadAccessContext(context: MobilePmsRequestContext): Promise<AccessContext> {
    const recordsets = await this.callStandardProcedure("sp_access_context_data", [
      context.companyCode,
      context.actorUserId
    ]);
    const permissionCodes = this.asRows(recordsets[0])
      .map((row) => asString(firstDefined(row, ["code", "permission_code", 0])))
      .map((code) => code.trim())
      .filter(Boolean);
    const allowedPropertyCodes = this.asRows(recordsets[1])
      .map((row) => normalizeCode(firstDefined(row, ["code", "property_code", 0])))
      .filter((code): code is string => Boolean(code));
    const meta = this.firstRow(recordsets[2]);
    const authzMode = asString(firstDefined(meta, ["authz_mode"])).toLowerCase() === "enforce"
      ? "enforce"
      : "audit";

    return {
      permissionCodes: Array.from(new Set(permissionCodes)),
      allowedPropertyCodes: Array.from(new Set(allowedPropertyCodes)),
      isOwner: asNumber(firstDefined(meta, ["is_owner"])) === 1,
      authzMode
    };
  }

  private assertAccess(
    access: AccessContext,
    propertyCode: string | null,
    permissionCode: string
  ): void {
    if (access.isOwner) {
      return;
    }
    if (propertyCode && !access.allowedPropertyCodes.includes(propertyCode)) {
      throw new AuthorizationError("The actor is not allowed to access the requested property.", {
        propertyCode
      });
    }
    if (!access.permissionCodes.includes(permissionCode)) {
      throw new AuthorizationError("The actor is missing the required permission.", {
        permissionCode
      });
    }
  }

  private async assertProcedureAuthorization(
    context: MobilePmsRequestContext,
    access: AccessContext,
    permissionCode: string,
    propertyCode: string | null
  ): Promise<void> {
    try {
      await this.callStandardProcedure("sp_authz_assert", [
        context.companyCode,
        context.actorUserId,
        permissionCode,
        propertyCode,
        access.authzMode
      ]);
    } catch (error) {
      const message = error instanceof Error ? error.message : "Authorization failed.";
      if (/AUTHZ_DENIED|forbidden|denied/i.test(message)) {
        throw new AuthorizationError("The actor is not authorized for this property.", {
          permissionCode,
          propertyCode
        });
      }
      await this.activityLogService.warn(
        "mobile.pms.authz_assert.failed",
        "sp_authz_assert failed; falling back to prechecked access context.",
        {
          message,
          permissionCode,
          propertyCode
        },
        "pms"
      );
    }
  }

  private toResponseContext(
    context: MobilePmsRequestContext,
    access: AccessContext
  ): MobilePmsBootstrapResponse["context"] {
    return {
      tenantId: context.tenantId,
      companyCode: context.companyCode,
      actorUserId: context.actorUserId,
      allowedPropertyCodes: access.allowedPropertyCodes,
      permissionCodes: access.permissionCodes,
      isOwner: access.isOwner,
      authzMode: access.authzMode
    };
  }

  private async callStandardProcedure(name: string, params: unknown[]): Promise<unknown[]> {
    const pool = await this.mariaDbPool.getPool("pms");
    const placeholders = params.map(() => "?").join(", ");
    const [rows] = await pool.query(`CALL ${name}(${placeholders});`, params);
    return normalizeRecordsets(rows);
  }

  private async callProcedureWithOutputs(
    name: string,
    params: unknown[],
    outputVariables: string[]
  ): Promise<{ recordsets: unknown[]; outputVariables?: Record<string, unknown> }> {
    const pool = await this.mariaDbPool.getPool("pms");
    const sessionVariables = outputVariables.map((key) => `@${name}_${key}`);
    const reset = sessionVariables.map((key) => `SET ${key} = NULL`).join("; ");
    const select = `SELECT ${sessionVariables
      .map((key, index) => `${key} AS ${outputVariables[index]}`)
      .join(", ")}`;
    const placeholders = [...params.map(() => "?"), ...sessionVariables].join(", ");
    const sql = `${reset}; CALL ${name}(${placeholders}); ${select};`;
    const [rows] = await pool.query(sql, params);
    const normalized = normalizeRecordsets(rows);
    const outputSet = normalized.at(-1);
    const outputVariablesRow =
      Array.isArray(outputSet) && outputSet[0] && typeof outputSet[0] === "object"
        ? (outputSet[0] as Record<string, unknown>)
        : undefined;

    return {
      recordsets: normalized.slice(0, -1),
      outputVariables: outputVariablesRow
    };
  }

  private asRows(recordset: unknown): Array<Record<string, unknown>> {
    return Array.isArray(recordset)
      ? recordset.filter((row): row is Record<string, unknown> => Boolean(row && typeof row === "object"))
      : [];
  }

  private firstRow(recordset: unknown): Record<string, unknown> | null {
    const rows = this.asRows(recordset);
    return rows[0] ?? null;
  }
}

function normalizeRecordsets(rows: unknown): unknown[] {
  if (!Array.isArray(rows)) {
    return [rows];
  }

  return rows
    .filter((entry) => !(typeof entry === "object" && entry !== null && "affectedRows" in entry))
    .map((entry) => {
      if (!Array.isArray(entry)) {
        return entry;
      }

      return entry.map((row) => {
        if (!row || typeof row !== "object") {
          return row;
        }

        return { ...(row as Record<string, unknown>) };
      });
    });
}

function buildOccupancyMap(
  dayRows: Array<Record<string, unknown>>,
  eventRows: Array<Record<string, unknown>>
): Map<string, Set<number>> {
  const map = new Map<string, Set<number>>();

  for (const eventRow of eventRows) {
    const roomId = asNumber(firstDefined(eventRow, ["id_room"]));
    const roomCode = normalizeCode(firstDefined(eventRow, ["room_code", "code"]));
    const checkIn = asString(firstDefined(eventRow, ["check_in_date"]));
    const checkOut = asString(firstDefined(eventRow, ["check_out_date"]));
    const eventType = asString(firstDefined(eventRow, ["event_type"]), "reservation");
    if (!checkIn || !checkOut || (!roomId && !roomCode)) {
      continue;
    }

    const key = roomId ? String(roomId) : roomCode ?? "";
    const endExclusive = eventType === "block" ? addNights(checkOut, 1) : checkOut;
    if (!map.has(key)) {
      map.set(key, new Set<number>());
    }
    const target = map.get(key)!;

    dayRows.forEach((dayRow, index) => {
      const dateKey = asString(firstDefined(dayRow, ["date_key", "calendar_date"]));
      if (dateKey >= checkIn && dateKey < endExclusive) {
        target.add(index);
      }
    });
  }

  return map;
}

function countContinuousNights(
  roomId: number,
  roomCode: string,
  dayRows: Array<Record<string, unknown>>,
  occupancyMap: Map<string, Set<number>>,
  startIndex: number
): number {
  const byId = roomId > 0 ? occupancyMap.get(String(roomId)) : undefined;
  const byCode = occupancyMap.get(roomCode);
  let count = 0;

  for (let index = startIndex; index < dayRows.length; index += 1) {
    if ((byId && byId.has(index)) || (byCode && byCode.has(index))) {
      break;
    }
    count += 1;
  }

  return count;
}

function roomSupportsPeople(
  room: MobilePmsBootstrapRoom,
  category: MobilePmsBootstrapCategory | undefined,
  people: number
): boolean {
  const totalCapacity = room.capacityTotal ?? category?.maxOccupancy ?? null;
  if (typeof totalCapacity === "number" && totalCapacity > 0) {
    return people <= totalCapacity;
  }
  if (typeof room.maxAdults === "number" && typeof room.maxChildren === "number") {
    return people <= room.maxAdults + room.maxChildren;
  }
  if (typeof room.maxAdults === "number") {
    return people <= room.maxAdults;
  }
  return true;
}

function firstDefined(
  row: Record<string, unknown> | null,
  keys: Array<string | number>
): unknown {
  if (!row) {
    return undefined;
  }

  for (const key of keys) {
    const value = typeof key === "number" ? row[String(key)] : row[key];
    if (value !== undefined && value !== null) {
      return value;
    }
  }

  return undefined;
}

function normalizeCode(value: unknown): string | null {
  const code = asString(value).trim().toUpperCase();
  return code === "" ? null : code;
}

function asString(value: unknown, fallback = ""): string {
  if (typeof value === "string") {
    return value;
  }
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }
  return fallback;
}

function asNumber(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function clampNumber(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

function todayYmd(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function diffNights(start: string, end: string): number {
  const startDate = Date.parse(`${start}T00:00:00Z`);
  const endDate = Date.parse(`${end}T00:00:00Z`);
  return Math.round((endDate - startDate) / 86_400_000);
}

function addNights(date: string, nights: number): string {
  const next = new Date(`${date}T00:00:00Z`);
  next.setUTCDate(next.getUTCDate() + nights);
  return next.toISOString().slice(0, 10);
}

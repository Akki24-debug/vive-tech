import { ActionCatalogEntry, AssistantRequest } from "@vlv-ai/shared";
import { z } from "zod";

export type ProcedureKind = "standard" | "with_output_variables";

export interface ActionDefinition<TArguments extends z.ZodRawShape = z.ZodRawShape> {
  name: string;
  description: string;
  executable: boolean;
  mode: "read" | "write" | "none";
  requiredPermissions: string[];
  argsSchema: z.ZodObject<TArguments>;
  procedure?: {
    name: string;
    kind: ProcedureKind;
    outputVariables?: string[];
    mapArguments: (
      args: z.infer<z.ZodObject<TArguments>>,
      request: AssistantRequest
    ) => unknown[];
  };
}

const dateString = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);

const definitions: ActionDefinition[] = [
  {
    name: "conversation.clarify",
    description: "Ask the user for missing information before any stored procedure is executed.",
    executable: false,
    mode: "none",
    requiredPermissions: [],
    argsSchema: z.object({
      question: z.string().min(1)
    })
  },
  {
    name: "availability.search",
    description: "Search room availability through the approved availability stored procedure.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.availability"],
    argsSchema: z.object({
      checkIn: dateString,
      nights: z.number().int().positive(),
      people: z.number().int().positive(),
      propertyCode: z.string().min(1).optional(),
      categoryCode: z.string().min(1).optional()
    }),
    procedure: {
      name: "sp_search_availability",
      kind: "standard",
      mapArguments: (args, request) => [request.companyCode, args.checkIn, args.nights, args.people]
    }
  },
  {
    name: "pricing.quote",
    description: "Calculate a stay total using the pricing stored procedure.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.pricing"],
    argsSchema: z.object({
      propertyId: z.number().int().positive(),
      rateplanId: z.number().int().positive(),
      roomId: z.number().int().positive().nullable().optional(),
      categoryId: z.number().int().positive().nullable().optional(),
      checkIn: dateString,
      checkOut: dateString
    }),
    procedure: {
      name: "sp_rateplan_calc_total",
      kind: "with_output_variables",
      outputVariables: ["total_cents", "avg_nightly_cents", "breakdown_json"],
      mapArguments: (args) => [
        args.propertyId,
        args.rateplanId,
        args.roomId ?? null,
        args.categoryId ?? null,
        args.checkIn,
        args.checkOut
      ]
    }
  },
  {
    name: "property.lookup",
    description: "Look up properties, room categories, rooms, and rate plans for the configured company.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.properties"],
    argsSchema: z.object({
      search: z.string().optional(),
      onlyActive: z.boolean().optional(),
      propertyCode: z.string().min(1).optional()
    }),
    procedure: {
      name: "sp_portal_property_data",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.propertyCode ?? request.propertyCode ?? null
      ]
    }
  },
  {
    name: "guest.lookup",
    description: "Look up guests and their reservation history for the configured company.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.guests"],
    argsSchema: z.object({
      search: z.string().optional(),
      onlyActive: z.boolean().optional(),
      guestId: z.number().int().positive().optional()
    }),
    procedure: {
      name: "sp_portal_guest_data",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.guestId ?? null,
        request.actorUserId
      ]
    }
  },
  {
    name: "catalog.lookup",
    description: "Look up catalog items used for lodging, payments, taxes, and operational charges.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.catalog"],
    argsSchema: z.object({
      propertyCode: z.string().min(1).optional(),
      includeInactive: z.boolean().optional(),
      itemId: z.number().int().positive().optional(),
      categoryId: z.number().int().positive().optional()
    }),
    procedure: {
      name: "sp_sale_item_catalog_data",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.propertyCode ?? request.propertyCode ?? null,
        args.includeInactive ? 1 : 0,
        args.itemId ?? null,
        args.categoryId ?? null
      ]
    }
  },
  {
    name: "reservation.lookup",
    description: "Look up reservation data for a company and optional property scope.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.reservations"],
    argsSchema: z.object({
      propertyCode: z.string().min(1).optional(),
      status: z.string().optional(),
      from: dateString.optional(),
      to: dateString.optional(),
      reservationId: z.number().int().positive().optional(),
      reservationCode: z.string().min(1).optional()
    }),
    procedure: {
      name: "sp_portal_reservation_data",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.propertyCode ?? request.propertyCode ?? null,
        args.status ?? null,
        args.from ?? null,
        args.to ?? null,
        args.reservationId ?? null,
        request.actorUserId
      ]
    }
  },
  {
    name: "operations.current_state",
    description:
      "Build a broad operational snapshot with current properties and reservation data, useful for state-of-business questions.",
    executable: true,
    mode: "read",
    requiredPermissions: ["assistant.read.operations"],
    argsSchema: z.object({
      propertyCode: z.string().min(1).optional(),
      status: z.string().optional(),
      from: dateString.optional(),
      to: dateString.optional(),
      reservationId: z.number().int().positive().optional(),
      reservationCode: z.string().min(1).optional()
    })
  },
  {
    name: "reservation.create_hold",
    description: "Create a reservation hold through the reservation hold stored procedure.",
    executable: true,
    mode: "write",
    requiredPermissions: ["assistant.write.reservations"],
    argsSchema: z.object({
      propertyCode: z.string().min(1).optional(),
      roomCode: z.string().min(1),
      checkIn: dateString,
      checkOut: dateString,
      totalCentsOverride: z.number().int().nonnegative().nullable().optional(),
      notes: z.string().optional()
    }),
    procedure: {
      name: "sp_create_reservation_hold",
      kind: "standard",
      mapArguments: (args, request) => [
        args.propertyCode ?? request.propertyCode ?? null,
        args.roomCode,
        args.checkIn,
        args.checkOut,
        args.totalCentsOverride ?? null,
        args.notes ?? null,
        request.actorUserId
      ]
    }
  },
  {
    name: "reservation.confirm_hold",
    description: "Confirm a held reservation and create its main folio.",
    executable: true,
    mode: "write",
    requiredPermissions: ["assistant.write.reservations"],
    argsSchema: z.object({
      reservationId: z.number().int().positive(),
      guestId: z.number().int().positive(),
      lodgingCatalogId: z.number().int().positive(),
      totalCentsOverride: z.number().int().nonnegative().nullable().optional(),
      adults: z.number().int().nonnegative(),
      children: z.number().int().nonnegative()
    }),
    procedure: {
      name: "sp_reservation_confirm_hold",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.reservationId,
        args.guestId,
        args.lodgingCatalogId,
        args.totalCentsOverride ?? null,
        args.adults,
        args.children,
        request.actorUserId
      ]
    }
  },
  {
    name: "reservation.update",
    description: "Update reservation state, room, dates, occupancy, and notes through the stored procedure.",
    executable: true,
    mode: "write",
    requiredPermissions: ["assistant.write.reservations"],
    argsSchema: z.object({
      reservationId: z.number().int().positive(),
      status: z.string().min(1),
      source: z.string().min(1),
      otaAccountId: z.number().int().positive().nullable().optional(),
      roomCode: z.string().min(1),
      checkInDate: dateString,
      checkOutDate: dateString,
      adults: z.number().int().nonnegative(),
      children: z.number().int().nonnegative(),
      reservationCode: z.string().min(1).optional(),
      internalNotes: z.string().optional(),
      guestNotes: z.string().optional()
    }),
    procedure: {
      name: "sp_reservation_update_v2",
      kind: "standard",
      mapArguments: (args, request) => [
        request.companyCode,
        args.reservationId,
        args.status,
        args.source,
        args.otaAccountId ?? null,
        args.roomCode,
        args.checkInDate,
        args.checkOutDate,
        args.adults,
        args.children,
        args.reservationCode ?? null,
        args.internalNotes ?? null,
        args.guestNotes ?? null,
        request.actorUserId
      ]
    }
  }
];

export function getActionDefinition(actionName: string): ActionDefinition | undefined {
  return definitions.find((definition) => definition.name === actionName);
}

export function listActionCatalog(): ActionCatalogEntry[] {
  return definitions.map((definition) => ({
    name: definition.name,
    description: definition.description,
    executable: definition.executable,
    procedureName: definition.procedure?.name,
    mode: definition.mode === "none" ? undefined : definition.mode,
    requiredPermissions: definition.requiredPermissions,
    requiredArguments: Object.keys(definition.argsSchema.shape)
  }));
}

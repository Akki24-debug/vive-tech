import { AssistantRequest } from "@vlv-ai/shared";

import { ProcedureExecutionResult, ProcedureExecutor } from "../db/procedure-executor";
import { ValidationError } from "../shared/errors";
import { ActionDefinition, getActionDefinition } from "./action-registry";

type ExecutionMode = "read" | "write";

export interface ActionExecutionResult {
  actionName: string;
  target: AssistantRequest["target"];
  mode: ExecutionMode;
  sources: string[];
  data: unknown;
  raw?: ProcedureExecutionResult | Record<string, unknown>;
}

export class ActionExecutionService {
  constructor(private readonly procedureExecutor: ProcedureExecutor) {}

  async execute(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    if (definition.target === "pms") {
      return this.executePms(definition, parsedArguments, request);
    }

    return this.executeBusinessBrain(definition, parsedArguments, request);
  }

  private async executePms(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    switch (definition.name) {
      case "availability.search":
        return this.executeAvailabilitySearch(definition, parsedArguments, request);
      case "pricing.quote":
        return this.executePricingQuote(definition, parsedArguments, request);
      case "property.lookup":
        return this.executePropertyLookup(definition, parsedArguments, request);
      case "guest.lookup":
        return this.executeGuestLookup(definition, parsedArguments, request);
      case "catalog.lookup":
        return this.executeCatalogLookup(definition, parsedArguments, request);
      case "reservation.lookup":
        return this.executeReservationLookup(definition, parsedArguments, request);
      case "operations.current_state":
        return this.executeCurrentState(parsedArguments, request);
      default:
        return this.executeRawProcedure(definition, parsedArguments, request);
    }
  }

  private async executeBusinessBrain(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    if (definition.name === "brain.current_context") {
      return this.executeBrainCurrentContext(parsedArguments, request);
    }

    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);
    const rows = this.asRows(execution.recordsets[0]);

    return {
      actionName: definition.name,
      target: definition.target,
      mode: definition.mode === "write" ? "write" : "read",
      sources: [execution.procedureName],
      data:
        definition.mode === "read"
          ? {
              filtersApplied: parsedArguments,
              rows,
              rowCount: rows.length
            }
          : rows[0] ?? null,
      raw: execution
    };
  }

  private async executeBrainCurrentContext(
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const lookupNames = [
      "brain.organization.lookup",
      "brain.business_area.lookup",
      "brain.business_line.lookup",
      "brain.business_priority.lookup",
      "brain.objective.lookup",
      "brain.external_system.lookup",
      "brain.knowledge_document.lookup"
    ] as const;

    const definitions = lookupNames.map((name) => getActionDefinition(request.target, name));

    if (definitions.some((definition) => !definition)) {
      throw new ValidationError("Required Business Brain lookup actions are not registered.");
    }

    const [organization, businessAreas, businessLines, priorities, objectives, externalSystems, documents] =
      await Promise.all(
        definitions.map((definition) =>
          this.executeBusinessBrain(
            definition!,
            {
              organizationId: this.asOptionalNumber(parsedArguments.organizationId),
              search: this.asOptionalString(parsedArguments.search),
              onlyActive: parsedArguments.onlyActive,
              limit: this.asOptionalNumber(parsedArguments.limit)
            },
            request
          )
        )
      );

    return {
      actionName: "brain.current_context",
      target: request.target,
      mode: "read",
      sources: [
        ...organization.sources,
        ...businessAreas.sources,
        ...businessLines.sources,
        ...priorities.sources,
        ...objectives.sources,
        ...externalSystems.sources,
        ...documents.sources
      ],
      data: {
        filtersApplied: parsedArguments,
        organization: organization.data,
        businessAreas: businessAreas.data,
        businessLines: businessLines.data,
        priorities: priorities.data,
        objectives: objectives.data,
        externalSystems: externalSystems.data,
        knowledgeDocuments: documents.data
      },
      raw: {
        organization: organization.raw ?? null,
        businessAreas: businessAreas.raw ?? null,
        businessLines: businessLines.raw ?? null,
        priorities: priorities.raw ?? null,
        objectives: objectives.raw ?? null,
        externalSystems: externalSystems.raw ?? null,
        knowledgeDocuments: documents.raw ?? null
      }
    };
  }

  private async executeAvailabilitySearch(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);
    const rows = this.asRows(execution.recordsets[0]);
    const propertyCode = this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode;
    const categoryCode = this.asOptionalString(parsedArguments.categoryCode);
    const filtered = rows.filter((row) => {
      if (propertyCode && String(row.property_code).toUpperCase() !== propertyCode.toUpperCase()) {
        return false;
      }

      if (categoryCode && String(row.category_code).toUpperCase() !== categoryCode.toUpperCase()) {
        return false;
      }

      return true;
    });

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: {
          checkIn: parsedArguments.checkIn,
          nights: parsedArguments.nights,
          people: parsedArguments.people,
          propertyCode: propertyCode ?? null,
          categoryCode: categoryCode ?? null
        },
        totalMatches: filtered.length,
        availability: filtered
      },
      raw: execution
    };
  }

  private async executePricingQuote(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);
    const breakdownRaw = execution.outputVariables?.breakdown_json;
    let breakdown: unknown = breakdownRaw ?? null;

    if (typeof breakdownRaw === "string" && breakdownRaw.trim() !== "") {
      try {
        breakdown = JSON.parse(breakdownRaw);
      } catch {
        breakdown = breakdownRaw;
      }
    }

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: parsedArguments,
        quote: {
          totalCents: this.asOptionalNumber(execution.outputVariables?.total_cents),
          avgNightlyCents: this.asOptionalNumber(execution.outputVariables?.avg_nightly_cents),
          breakdown
        }
      },
      raw: execution
    };
  }

  private async executePropertyLookup(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: {
          search: this.asOptionalString(parsedArguments.search),
          propertyCode:
            this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode ?? null,
          onlyActive: parsedArguments.onlyActive !== false
        },
        properties: this.asRows(execution.recordsets[0]),
        propertyDetail: this.firstRow(execution.recordsets[1]),
        rateplans: this.asRows(execution.recordsets[2]),
        categories: this.asRows(execution.recordsets[3]),
        rooms: this.asRows(execution.recordsets[4]),
        bedConfigurations: this.asRows(execution.recordsets[5])
      },
      raw: execution
    };
  }

  private async executeGuestLookup(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: {
          search: this.asOptionalString(parsedArguments.search),
          guestId: this.asOptionalNumber(parsedArguments.guestId),
          onlyActive: parsedArguments.onlyActive !== false
        },
        guests: this.asRows(execution.recordsets[0]),
        guestDetail: this.firstRow(execution.recordsets[1]),
        reservations: this.asRows(execution.recordsets[2]),
        activityBookings: this.asRows(execution.recordsets[3])
      },
      raw: execution
    };
  }

  private async executeCatalogLookup(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: {
          propertyCode:
            this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode ?? null,
          itemId: this.asOptionalNumber(parsedArguments.itemId),
          categoryId: this.asOptionalNumber(parsedArguments.categoryId),
          includeInactive: Boolean(parsedArguments.includeInactive)
        },
        catalogItems: this.asRows(execution.recordsets[0]),
        catalogItemDetail: this.firstRow(execution.recordsets[1])
      },
      raw: execution
    };
  }

  private async executeReservationLookup(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const propertyCode = this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode;
    const reservationId = this.asOptionalNumber(parsedArguments.reservationId);
    const reservationCode = this.asOptionalString(parsedArguments.reservationCode);

    let execution = await this.procedureExecutor.execute(
      definition,
      {
        ...parsedArguments,
        propertyCode,
        reservationId: reservationId ?? null
      },
      request
    );

    let resolvedReservationId = reservationId ?? null;

    if (!resolvedReservationId && reservationCode) {
      const matched = this.asRows(execution.recordsets[0]).find((row) => {
        return String(row.reservation_code ?? "").toUpperCase() === reservationCode.toUpperCase();
      });

      if (matched?.id_reservation) {
        resolvedReservationId = Number(matched.id_reservation);
        execution = await this.procedureExecutor.execute(
          definition,
          {
            ...parsedArguments,
            propertyCode,
            reservationId: resolvedReservationId
          },
          request
        );
      }
    }

    return {
      actionName: definition.name,
      target: definition.target,
      mode: "read",
      sources: [execution.procedureName],
      data: {
        filtersApplied: {
          propertyCode: propertyCode ?? null,
          status: this.asOptionalString(parsedArguments.status),
          from: this.asOptionalString(parsedArguments.from),
          to: this.asOptionalString(parsedArguments.to),
          reservationId: reservationId ?? null,
          reservationCode: reservationCode ?? null
        },
        resolvedReservationId,
        reservations: this.asRows(execution.recordsets[0]),
        reservationDetail: this.firstRow(execution.recordsets[1]),
        folios: this.asRows(execution.recordsets[2]),
        lineItems: this.asRows(execution.recordsets[3]),
        payments: this.asRows(execution.recordsets[5]),
        refunds: this.asRows(execution.recordsets[6]),
        activityBookings: this.asRows(execution.recordsets[7]),
        reservationInterests: this.asRows(execution.recordsets[8])
      },
      raw: execution
    };
  }

  private async executeCurrentState(
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const propertyDefinition = getActionDefinition(request.target, "property.lookup");
    const reservationDefinition = getActionDefinition(request.target, "reservation.lookup");

    if (!propertyDefinition || !reservationDefinition) {
      throw new ValidationError("Required lookup actions are not registered for current state.");
    }

    const propertyResult = await this.executePropertyLookup(
      propertyDefinition,
      {
        propertyCode: this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode,
        onlyActive: true
      },
      request
    );
    const reservationResult = await this.executeReservationLookup(
      reservationDefinition,
      {
        propertyCode: this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode,
        status: this.asOptionalString(parsedArguments.status),
        from: this.asOptionalString(parsedArguments.from),
        to: this.asOptionalString(parsedArguments.to),
        reservationId: this.asOptionalNumber(parsedArguments.reservationId),
        reservationCode: this.asOptionalString(parsedArguments.reservationCode)
      },
      request
    );

    return {
      actionName: "operations.current_state",
      target: request.target,
      mode: "read",
      sources: [...propertyResult.sources, ...reservationResult.sources],
      data: {
        filtersApplied: {
          propertyCode:
            this.asOptionalString(parsedArguments.propertyCode) ?? request.propertyCode ?? null,
          status: this.asOptionalString(parsedArguments.status),
          from: this.asOptionalString(parsedArguments.from),
          to: this.asOptionalString(parsedArguments.to),
          reservationId: this.asOptionalNumber(parsedArguments.reservationId),
          reservationCode: this.asOptionalString(parsedArguments.reservationCode)
        },
        properties: propertyResult.data,
        reservations: reservationResult.data
      },
      raw: {
        propertyLookup: propertyResult.raw ?? null,
        reservationLookup: reservationResult.raw ?? null
      }
    };
  }

  private async executeRawProcedure(
    definition: ActionDefinition,
    parsedArguments: Record<string, unknown>,
    request: AssistantRequest
  ): Promise<ActionExecutionResult> {
    const execution = await this.procedureExecutor.execute(definition, parsedArguments, request);

    return {
      actionName: definition.name,
      target: definition.target,
      mode: definition.mode === "write" ? "write" : "read",
      sources: [execution.procedureName],
      data: {
        procedureName: execution.procedureName,
        recordsets: execution.recordsets,
        outputVariables: execution.outputVariables
      },
      raw: execution
    };
  }

  private asRows(recordset: unknown): Record<string, unknown>[] {
    if (!Array.isArray(recordset)) {
      return [];
    }

    return recordset.filter((row) => Boolean(row) && typeof row === "object") as Record<
      string,
      unknown
    >[];
  }

  private firstRow(recordset: unknown): Record<string, unknown> | null {
    return this.asRows(recordset)[0] ?? null;
  }

  private asOptionalString(value: unknown): string | undefined {
    if (typeof value !== "string") {
      return undefined;
    }

    const trimmed = value.trim();
    return trimmed ? trimmed : undefined;
  }

  private asOptionalNumber(value: unknown): number | undefined {
    if (typeof value === "number" && Number.isFinite(value)) {
      return value;
    }

    return undefined;
  }
}

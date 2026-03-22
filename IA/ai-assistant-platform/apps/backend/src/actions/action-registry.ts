import { ActionCatalogEntry, AssistantRequest, AssistantTarget } from "@vlv-ai/shared";
import { z } from "zod";

export type ProcedureKind = "standard" | "with_output_variables";

export interface ActionDefinition<TArguments extends z.ZodRawShape = z.ZodRawShape> {
  target: AssistantTarget;
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
const optionalId = z.number().int().positive().optional();
const optionalNullableId = z.number().int().positive().nullable().optional();
const optionalText = z.string().optional();
const optionalDate = dateString.optional();
const optionalPositiveNumber = z.number().int().positive().optional();
const optionalLimit = z.number().int().positive().optional();

const clarifyDefinition = (
  target: AssistantTarget
): ActionDefinition => ({
  target,
  name: "conversation.clarify",
  description: "Ask the user for missing information before any stored procedure is executed.",
  executable: false,
  mode: "none",
  requiredPermissions: [],
  argsSchema: z.object({
    question: z.string().min(1)
  })
});

const pmsDefinitions: ActionDefinition[] = [
  clarifyDefinition("pms"),
  {
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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
    target: "pms",
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

const businessBrainDefinitions: ActionDefinition[] = [
  clarifyDefinition("business_brain"),
  {
    target: "business_brain",
    name: "brain.current_context",
    description:
      "Build an operational snapshot of the current business brain context: organization, business areas, lines, priorities, objectives, systems, and knowledge docs.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.context"],
    argsSchema: z.object({
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    })
  },
  {
    target: "business_brain",
    name: "brain.organization.lookup",
    description: "Look up organizations stored in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.organization"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_organization_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.organization.upsert",
    description: "Create or update the main organization record in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.organization"],
    argsSchema: z.object({
      id: optionalId,
      name: z.string().min(1),
      legalName: optionalText,
      description: optionalText,
      baseCity: optionalText,
      baseState: optionalText,
      country: optionalText,
      status: optionalText,
      currentStage: optionalText,
      visionSummary: optionalText,
      notes: optionalText
    }),
    procedure: {
      name: "sp_organization_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.name,
        args.legalName ?? null,
        args.description ?? null,
        args.baseCity ?? null,
        args.baseState ?? null,
        args.country ?? null,
        args.status ?? null,
        args.currentStage ?? null,
        args.visionSummary ?? null,
        args.notes ?? null,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.user_account.lookup",
    description: "Look up business brain users and bootstrap accounts.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.users"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_user_account_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.user_account.upsert",
    description: "Create or update a business brain user account; supports bootstrap when actor user id is 0.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.users"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      firstName: z.string().min(1),
      lastName: optionalText,
      displayName: z.string().min(1),
      email: optionalText,
      phone: optionalText,
      roleSummary: optionalText,
      employmentStatus: optionalText,
      timezone: optionalText,
      notes: optionalText,
      isActive: z.boolean().optional()
    }),
    procedure: {
      name: "sp_user_account_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.firstName,
        args.lastName ?? null,
        args.displayName,
        args.email ?? null,
        args.phone ?? null,
        args.roleSummary ?? null,
        args.employmentStatus ?? null,
        args.timezone ?? null,
        args.notes ?? null,
        args.isActive === false ? 0 : 1,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.role.lookup",
    description: "Look up global roles available to the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.roles"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_role_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.role.upsert",
    description: "Create or update a business brain role; supports bootstrap when actor user id is 0.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.roles"],
    argsSchema: z.object({
      id: optionalId,
      name: z.string().min(1),
      description: optionalText
    }),
    procedure: {
      name: "sp_role_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.name,
        args.description ?? null,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.user_roles.sync",
    description: "Replace the full role set assigned to a business brain user.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.roles"],
    argsSchema: z.object({
      userId: z.number().int().positive(),
      roleIdsCsv: z.string().optional()
    }),
    procedure: {
      name: "sp_user_role_sync",
      kind: "standard",
      mapArguments: (args, request) => [args.userId, args.roleIdsCsv ?? "", request.actorUserId]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_area.lookup",
    description: "Look up business areas registered in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.business_areas"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_business_area_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_area.upsert",
    description: "Create or update a business area in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.business_areas"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      name: z.string().min(1),
      code: optionalText,
      description: optionalText,
      priorityLevel: optionalText,
      responsibleUserId: optionalNullableId,
      isActive: z.boolean().optional()
    }),
    procedure: {
      name: "sp_business_area_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.name,
        args.code ?? null,
        args.description ?? null,
        args.priorityLevel ?? null,
        args.responsibleUserId ?? null,
        args.isActive === false ? 0 : 1,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_line.lookup",
    description: "Look up business lines registered in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.business_lines"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_business_line_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_line.upsert",
    description: "Create or update a business line in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.business_lines"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      businessAreaId: optionalNullableId,
      name: z.string().min(1),
      description: optionalText,
      businessModelSummary: optionalText,
      currentStatus: optionalText,
      monetizationNotes: optionalText,
      strategicPriority: optionalText,
      ownerUserId: optionalNullableId,
      isActive: z.boolean().optional()
    }),
    procedure: {
      name: "sp_business_line_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.businessAreaId ?? null,
        args.name,
        args.description ?? null,
        args.businessModelSummary ?? null,
        args.currentStatus ?? null,
        args.monetizationNotes ?? null,
        args.strategicPriority ?? null,
        args.ownerUserId ?? null,
        args.isActive === false ? 0 : 1,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_priority.lookup",
    description: "Look up strategic priorities in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.priorities"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_business_priority_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.business_priority.upsert",
    description: "Create or update a strategic priority in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.priorities"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      title: z.string().min(1),
      description: optionalText,
      scopeType: optionalText,
      scopeId: optionalNullableId,
      priorityOrder: optionalPositiveNumber,
      status: optionalText,
      targetPeriod: optionalText,
      ownerUserId: optionalNullableId
    }),
    procedure: {
      name: "sp_business_priority_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.title,
        args.description ?? null,
        args.scopeType ?? null,
        args.scopeId ?? null,
        args.priorityOrder ?? null,
        args.status ?? null,
        args.targetPeriod ?? null,
        args.ownerUserId ?? null,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.objective.lookup",
    description: "Look up strategic objectives in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.objectives"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_objective_record_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.objective.upsert",
    description: "Create or update a strategic objective in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.objectives"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      businessAreaId: optionalNullableId,
      title: z.string().min(1),
      description: optionalText,
      objectiveType: optionalText,
      status: optionalText,
      targetDate: optionalDate,
      ownerUserId: optionalNullableId,
      completionPercent: z.number().min(0).max(100).optional()
    }),
    procedure: {
      name: "sp_objective_record_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.businessAreaId ?? null,
        args.title,
        args.description ?? null,
        args.objectiveType ?? null,
        args.status ?? null,
        args.targetDate ?? null,
        args.ownerUserId ?? null,
        args.completionPercent ?? null,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.external_system.lookup",
    description: "Look up external systems referenced by the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.integrations"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_external_system_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.external_system.upsert",
    description: "Create or update an external system record in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.integrations"],
    argsSchema: z.object({
      id: optionalId,
      name: z.string().min(1),
      systemType: z.string().min(1),
      description: optionalText,
      isActive: z.boolean().optional()
    }),
    procedure: {
      name: "sp_external_system_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.name,
        args.systemType,
        args.description ?? null,
        args.isActive === false ? 0 : 1,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.knowledge_document.lookup",
    description: "Look up knowledge documents loaded in the business brain.",
    executable: true,
    mode: "read",
    requiredPermissions: ["brain.read.knowledge"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: optionalId,
      search: optionalText,
      onlyActive: z.boolean().optional(),
      limit: optionalLimit
    }),
    procedure: {
      name: "sp_knowledge_document_data",
      kind: "standard",
      mapArguments: (args) => [
        args.id ?? null,
        args.organizationId ?? null,
        args.search ?? null,
        args.onlyActive === false ? 0 : 1,
        args.limit ?? 100
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.knowledge_document.upsert",
    description: "Create or update a knowledge document in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.knowledge"],
    argsSchema: z.object({
      id: optionalId,
      organizationId: z.number().int().positive(),
      businessAreaId: optionalNullableId,
      projectId: optionalNullableId,
      title: z.string().min(1),
      documentType: optionalText,
      storageType: optionalText,
      externalUrl: optionalText,
      versionLabel: optionalText,
      status: optionalText,
      ownerUserId: optionalNullableId,
      summary: optionalText
    }),
    procedure: {
      name: "sp_knowledge_document_upsert",
      kind: "standard",
      mapArguments: (args, request) => [
        args.id ?? null,
        args.organizationId,
        args.businessAreaId ?? null,
        args.projectId ?? null,
        args.title,
        args.documentType ?? null,
        args.storageType ?? null,
        args.externalUrl ?? null,
        args.versionLabel ?? null,
        args.status ?? null,
        args.ownerUserId ?? null,
        args.summary ?? null,
        request.actorUserId
      ]
    }
  },
  {
    target: "business_brain",
    name: "brain.knowledge_document.publish",
    description: "Publish a draft knowledge document in the business brain.",
    executable: true,
    mode: "write",
    requiredPermissions: ["brain.write.knowledge"],
    argsSchema: z.object({
      knowledgeDocumentId: z.number().int().positive()
    }),
    procedure: {
      name: "sp_knowledge_document_publish",
      kind: "standard",
      mapArguments: (args, request) => [args.knowledgeDocumentId, request.actorUserId]
    }
  }
];

const definitionsByTarget: Record<AssistantTarget, ActionDefinition[]> = {
  business_brain: businessBrainDefinitions,
  pms: pmsDefinitions
};

export function getActionDefinition(
  target: AssistantTarget,
  actionName: string
): ActionDefinition | undefined {
  return definitionsByTarget[target].find((definition) => definition.name === actionName);
}

export function listActionCatalog(target: AssistantTarget): ActionCatalogEntry[] {
  return definitionsByTarget[target].map((definition) => ({
    target: definition.target,
    name: definition.name,
    description: definition.description,
    executable: definition.executable,
    procedureName: definition.procedure?.name,
    mode: definition.mode === "none" ? undefined : definition.mode,
    requiredPermissions: definition.requiredPermissions,
    requiredArguments: Object.keys(definition.argsSchema.shape)
  }));
}

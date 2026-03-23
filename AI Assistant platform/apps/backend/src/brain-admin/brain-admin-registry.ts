import {
  BrainAdminModuleDescriptor,
  BrainAdminModuleKey,
  BrainAdminReferenceOption,
  BrainAdminResourceKey
} from "@vlv-ai/shared";
import { z } from "zod";

import { ActionDefinition, getActionDefinition } from "../actions/action-registry";

const optionalId = z.number().int().positive().optional();
const optionalNullableId = z.number().int().positive().nullable().optional();
const optionalText = z.string().optional();
const optionalLimit = z.number().int().positive().optional();
const optionalDate = z
  .string()
  .regex(/^\d{4}-\d{2}-\d{2}$/)
  .optional();

const standardLookupArgsSchema = z.object({
  id: optionalId,
  organizationId: optionalId,
  search: optionalText,
  onlyActive: z.boolean().optional(),
  limit: optionalLimit
});

function requireBrainAction(name: string): ActionDefinition {
  const definition = getActionDefinition("business_brain", name);

  if (!definition) {
    throw new Error(`Missing business brain action definition: ${name}`);
  }

  return definition;
}

const userRoleLookupDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_role.lookup",
  description: "Look up user-role assignments in the business brain.",
  executable: true,
  mode: "read",
  requiredPermissions: [],
  argsSchema: standardLookupArgsSchema,
  procedure: {
    name: "sp_user_role_data",
    kind: "standard",
    mapArguments: (args) => [
      args.id ?? null,
      args.organizationId ?? null,
      args.search ?? null,
      args.onlyActive === false ? 0 : 1,
      args.limit ?? 250
    ]
  }
};

const userAreaAssignmentLookupDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_area_assignment.lookup",
  description: "Look up user-area assignments in the business brain.",
  executable: true,
  mode: "read",
  requiredPermissions: [],
  argsSchema: standardLookupArgsSchema,
  procedure: {
    name: "sp_user_area_assignment_data",
    kind: "standard",
    mapArguments: (args) => [
      args.id ?? null,
      args.organizationId ?? null,
      args.search ?? null,
      args.onlyActive === false ? 0 : 1,
      args.limit ?? 250
    ]
  }
};

const userAreaAssignmentUpsertDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_area_assignment.upsert",
  description: "Create or update user-area assignments in the business brain.",
  executable: true,
  mode: "write",
  requiredPermissions: [],
  argsSchema: z.object({
    id: optionalId,
    userId: z.number().int().positive(),
    businessAreaId: z.number().int().positive(),
    responsibilityLevel: z.string().min(1).optional(),
    isPrimary: z.boolean().optional(),
    startDate: optionalDate,
    endDate: optionalDate
  }),
  procedure: {
    name: "sp_user_area_assignment_upsert",
    kind: "standard",
    mapArguments: (args, request) => [
      args.id ?? null,
      args.userId,
      args.businessAreaId,
      args.responsibilityLevel ?? "member",
      args.isPrimary ? 1 : 0,
      args.startDate ?? null,
      args.endDate ?? null,
      request.actorUserId
    ]
  }
};

const userAreaAssignmentDeleteDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_area_assignment.delete",
  description: "Delete user-area assignments in the business brain.",
  executable: true,
  mode: "write",
  requiredPermissions: [],
  argsSchema: z.object({
    id: z.number().int().positive(),
    reason: optionalText
  }),
  procedure: {
    name: "sp_user_area_assignment_delete",
    kind: "standard",
    mapArguments: (args, request) => [args.id, request.actorUserId, args.reason ?? null]
  }
};

const userCapacityProfileLookupDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_capacity_profile.lookup",
  description: "Look up user capacity profiles in the business brain.",
  executable: true,
  mode: "read",
  requiredPermissions: [],
  argsSchema: standardLookupArgsSchema,
  procedure: {
    name: "sp_user_capacity_profile_data",
    kind: "standard",
    mapArguments: (args) => [
      args.id ?? null,
      args.organizationId ?? null,
      args.search ?? null,
      args.onlyActive === false ? 0 : 1,
      args.limit ?? 250
    ]
  }
};

const userCapacityProfileUpsertDefinition: ActionDefinition = {
  target: "business_brain",
  name: "brain_admin.user_capacity_profile.upsert",
  description: "Create or update user capacity profiles in the business brain.",
  executable: true,
  mode: "write",
  requiredPermissions: [],
  argsSchema: z.object({
    id: optionalId,
    userId: z.number().int().positive(),
    weeklyCapacityHours: z.number().nonnegative().nullable().optional(),
    maxParallelProjects: z.number().int().nonnegative().nullable().optional(),
    maxParallelTasks: z.number().int().nonnegative().nullable().optional(),
    notes: optionalText
  }),
  procedure: {
    name: "sp_user_capacity_profile_upsert",
    kind: "standard",
    mapArguments: (args, request) => [
      args.id ?? null,
      args.userId,
      args.weeklyCapacityHours ?? null,
      args.maxParallelProjects ?? null,
      args.maxParallelTasks ?? null,
      args.notes ?? null,
      request.actorUserId
    ]
  }
};

export interface BrainAdminResourceDefinition {
  key: BrainAdminResourceKey;
  title: string;
  lookupAction: ActionDefinition;
  upsertAction?: ActionDefinition;
  deleteAction?: ActionDefinition;
  defaultSortBy: string;
  defaultSortDir: "asc" | "desc";
  bootstrapWritable?: boolean;
}

export const brainAdminModules: BrainAdminModuleDescriptor[] = [
  {
    key: "overview",
    title: "Overview",
    description: "KPIs del Brain, estado del actor y cambios recientes."
  },
  {
    key: "organization",
    title: "Organization",
    description: "Perfil unico de Vive la Vibe y estado general.",
    resourceKey: "organization"
  },
  {
    key: "people",
    title: "People",
    description: "Usuarios, roles sincronizados, areas y capacidad.",
    resourceKey: "user_account"
  },
  {
    key: "roles_access",
    title: "Roles & Access",
    description: "Catalogo de roles y su uso actual.",
    resourceKey: "role"
  },
  {
    key: "business_areas",
    title: "Business Areas",
    description: "Areas funcionales con ownership y prioridad.",
    resourceKey: "business_area"
  },
  {
    key: "business_lines",
    title: "Business Lines",
    description: "Lineas de negocio con area, owner y estado.",
    resourceKey: "business_line"
  },
  {
    key: "priorities",
    title: "Priorities",
    description: "Prioridades activas e historicas del negocio.",
    resourceKey: "business_priority"
  },
  {
    key: "objectives",
    title: "Objectives",
    description: "Objetivos estrategicos con area, owner y avance.",
    resourceKey: "objective_record"
  },
  {
    key: "external_systems",
    title: "External Systems",
    description: "Sistemas externos reconocidos por el Brain.",
    resourceKey: "external_system"
  },
  {
    key: "knowledge_documents",
    title: "Knowledge Documents",
    description: "Metadata documental y links del negocio.",
    resourceKey: "knowledge_document"
  }
];

export const bootstrapWritableResources: BrainAdminResourceKey[] = [
  "organization",
  "user_account",
  "role",
  "user_role"
];

export const brainAdminResources: Record<BrainAdminResourceKey, BrainAdminResourceDefinition> = {
  organization: {
    key: "organization",
    title: "Organization",
    lookupAction: requireBrainAction("brain.organization.lookup"),
    upsertAction: requireBrainAction("brain.organization.upsert"),
    defaultSortBy: "updated_at",
    defaultSortDir: "desc",
    bootstrapWritable: true
  },
  user_account: {
    key: "user_account",
    title: "People",
    lookupAction: requireBrainAction("brain.user_account.lookup"),
    upsertAction: requireBrainAction("brain.user_account.upsert"),
    defaultSortBy: "display_name",
    defaultSortDir: "asc",
    bootstrapWritable: true
  },
  role: {
    key: "role",
    title: "Roles",
    lookupAction: requireBrainAction("brain.role.lookup"),
    upsertAction: requireBrainAction("brain.role.upsert"),
    defaultSortBy: "name",
    defaultSortDir: "asc",
    bootstrapWritable: true
  },
  user_role: {
    key: "user_role",
    title: "User Roles",
    lookupAction: userRoleLookupDefinition,
    defaultSortBy: "created_at",
    defaultSortDir: "desc",
    bootstrapWritable: true
  },
  user_area_assignment: {
    key: "user_area_assignment",
    title: "User Area Assignments",
    lookupAction: userAreaAssignmentLookupDefinition,
    upsertAction: userAreaAssignmentUpsertDefinition,
    deleteAction: userAreaAssignmentDeleteDefinition,
    defaultSortBy: "created_at",
    defaultSortDir: "desc"
  },
  user_capacity_profile: {
    key: "user_capacity_profile",
    title: "User Capacity Profiles",
    lookupAction: userCapacityProfileLookupDefinition,
    upsertAction: userCapacityProfileUpsertDefinition,
    defaultSortBy: "updated_at",
    defaultSortDir: "desc"
  },
  business_area: {
    key: "business_area",
    title: "Business Areas",
    lookupAction: requireBrainAction("brain.business_area.lookup"),
    upsertAction: requireBrainAction("brain.business_area.upsert"),
    defaultSortBy: "name",
    defaultSortDir: "asc"
  },
  business_line: {
    key: "business_line",
    title: "Business Lines",
    lookupAction: requireBrainAction("brain.business_line.lookup"),
    upsertAction: requireBrainAction("brain.business_line.upsert"),
    defaultSortBy: "name",
    defaultSortDir: "asc"
  },
  business_priority: {
    key: "business_priority",
    title: "Priorities",
    lookupAction: requireBrainAction("brain.business_priority.lookup"),
    upsertAction: requireBrainAction("brain.business_priority.upsert"),
    defaultSortBy: "priority_order",
    defaultSortDir: "asc"
  },
  objective_record: {
    key: "objective_record",
    title: "Objectives",
    lookupAction: requireBrainAction("brain.objective.lookup"),
    upsertAction: requireBrainAction("brain.objective.upsert"),
    defaultSortBy: "target_date",
    defaultSortDir: "asc"
  },
  external_system: {
    key: "external_system",
    title: "External Systems",
    lookupAction: requireBrainAction("brain.external_system.lookup"),
    upsertAction: requireBrainAction("brain.external_system.upsert"),
    defaultSortBy: "name",
    defaultSortDir: "asc"
  },
  knowledge_document: {
    key: "knowledge_document",
    title: "Knowledge Documents",
    lookupAction: requireBrainAction("brain.knowledge_document.lookup"),
    upsertAction: requireBrainAction("brain.knowledge_document.upsert"),
    defaultSortBy: "updated_at",
    defaultSortDir: "desc"
  }
};

export const brainAdminStatusOptions: Record<string, BrainAdminReferenceOption[]> = {
  organizationStatus: [
    { value: "active", label: "Activo" },
    { value: "planning", label: "Planeacion" },
    { value: "paused", label: "Pausado" },
    { value: "archived", label: "Archivado" }
  ],
  employmentStatus: [
    { value: "active", label: "Activo" },
    { value: "inactive", label: "Inactivo" },
    { value: "contractor", label: "Contractor" },
    { value: "paused", label: "Pausado" }
  ],
  priorityLevel: [
    { value: "low", label: "Low" },
    { value: "medium", label: "Medium" },
    { value: "high", label: "High" },
    { value: "critical", label: "Critical" }
  ],
  businessLineStatus: [
    { value: "planned", label: "Planned" },
    { value: "active", label: "Active" },
    { value: "paused", label: "Paused" },
    { value: "archived", label: "Archived" }
  ],
  businessPriorityStatus: [
    { value: "active", label: "Active" },
    { value: "planned", label: "Planned" },
    { value: "completed", label: "Completed" },
    { value: "archived", label: "Archived" }
  ],
  objectiveStatus: [
    { value: "active", label: "Active" },
    { value: "planned", label: "Planned" },
    { value: "completed", label: "Completed" },
    { value: "archived", label: "Archived" }
  ],
  objectiveType: [
    { value: "strategic", label: "Strategic" },
    { value: "tactical", label: "Tactical" },
    { value: "operational", label: "Operational" }
  ],
  responsibilityLevel: [
    { value: "owner", label: "Owner" },
    { value: "lead", label: "Lead" },
    { value: "member", label: "Member" },
    { value: "support", label: "Support" }
  ],
  systemType: [
    { value: "pms", label: "PMS" },
    { value: "crm", label: "CRM" },
    { value: "finance", label: "Finance" },
    { value: "communication", label: "Communication" },
    { value: "automation", label: "Automation" },
    { value: "other", label: "Other" }
  ],
  knowledgeDocumentStatus: [
    { value: "draft", label: "Draft" },
    { value: "review", label: "Review" },
    { value: "published", label: "Published" },
    { value: "archived", label: "Archived" }
  ],
  knowledgeDocumentType: [
    { value: "general", label: "General" },
    { value: "plan", label: "Plan" },
    { value: "strategy", label: "Strategy" },
    { value: "policy", label: "Policy" },
    { value: "sop", label: "SOP" },
    { value: "notes", label: "Notes" },
    { value: "spec", label: "Spec" }
  ],
  knowledgeStorageType: [
    { value: "drive", label: "Drive" },
    { value: "url", label: "URL" },
    { value: "notion", label: "Notion" },
    { value: "local", label: "Local" },
    { value: "other", label: "Other" }
  ],
  scopeType: [
    { value: "organization", label: "Organization" },
    { value: "business_area", label: "Business Area" },
    { value: "business_line", label: "Business Line" }
  ],
  yesNo: [
    { value: "true", label: "Si" },
    { value: "false", label: "No" }
  ]
};

export function getBrainAdminResourceDefinition(
  resource: BrainAdminResourceKey
): BrainAdminResourceDefinition {
  return brainAdminResources[resource];
}

export function isBrainAdminModuleKey(value: string): value is BrainAdminModuleKey {
  return brainAdminModules.some((module) => module.key === value);
}

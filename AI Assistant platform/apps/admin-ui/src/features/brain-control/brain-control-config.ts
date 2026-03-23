import { BrainAdminModuleKey, BrainAdminResourceKey } from "@vlv-ai/shared";

export type BrainControlOptionSource =
  | "users"
  | "roles"
  | "businessAreas"
  | "businessLines"
  | "priorities"
  | "organizationStatus"
  | "employmentStatus"
  | "priorityLevel"
  | "businessLineStatus"
  | "businessPriorityStatus"
  | "objectiveStatus"
  | "objectiveType"
  | "responsibilityLevel"
  | "systemType"
  | "knowledgeDocumentStatus"
  | "knowledgeDocumentType"
  | "knowledgeStorageType"
  | "scopeType";

export interface BrainControlColumnConfig {
  key: string;
  label: string;
  type?: "text" | "status" | "date" | "boolean" | "number" | "array";
}

export interface BrainControlFilterConfig {
  key: string;
  label: string;
  type: "text" | "select";
  placeholder?: string;
  optionSource?: BrainControlOptionSource;
  options?: Array<{ value: string; label: string }>;
  defaultValue?: string;
}

export interface BrainControlFieldConfig {
  key: string;
  label: string;
  type: "text" | "textarea" | "select" | "number" | "date" | "checkbox";
  required?: boolean;
  placeholder?: string;
  optionSource?: BrainControlOptionSource;
  options?: Array<{ value: string; label: string }>;
  visibleWhen?: { field: string; notEquals?: string; equals?: string };
}

export interface BrainControlResourceConfig {
  moduleKey: BrainAdminModuleKey;
  resource: BrainAdminResourceKey;
  title: string;
  subtitle: string;
  createLabel: string;
  defaultVisibleColumns: string[];
  columns: BrainControlColumnConfig[];
  filters: BrainControlFilterConfig[];
  fields: BrainControlFieldConfig[];
  canDelete?: boolean;
}

export const genericResourceConfigs: Record<
  Exclude<BrainAdminResourceKey, "user_account" | "user_role" | "user_area_assignment" | "user_capacity_profile">,
  BrainControlResourceConfig
> = {
  organization: {
    moduleKey: "organization",
    resource: "organization",
    title: "Organization",
    subtitle: "Perfil unico de Vive la Vibe.",
    createLabel: "Editar organizacion",
    defaultVisibleColumns: ["name", "status", "current_stage", "base_city", "country", "updated_at"],
    columns: [
      { key: "name", label: "Nombre" },
      { key: "legal_name", label: "Razón social" },
      { key: "status", label: "Status", type: "status" },
      { key: "current_stage", label: "Etapa" },
      { key: "base_city", label: "Ciudad base" },
      { key: "country", label: "País" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Nombre, ciudad, visión..." },
      { key: "status", label: "Status", type: "select", optionSource: "organizationStatus" },
      { key: "currentStage", label: "Etapa actual", type: "text", placeholder: "growth, build, launch..." }
    ],
    fields: [
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "legalName", label: "Razón social", type: "text" },
      { key: "status", label: "Status", type: "select", optionSource: "organizationStatus", required: true },
      { key: "currentStage", label: "Etapa actual", type: "text" },
      { key: "baseCity", label: "Ciudad base", type: "text" },
      { key: "baseState", label: "Estado base", type: "text" },
      { key: "country", label: "País", type: "text" },
      { key: "description", label: "Descripción", type: "textarea" },
      { key: "visionSummary", label: "Visión", type: "textarea" },
      { key: "notes", label: "Notas", type: "textarea" }
    ]
  },
  role: {
    moduleKey: "roles_access",
    resource: "role",
    title: "Roles & Access",
    subtitle: "Catálogo de roles y su uso actual.",
    createLabel: "Nuevo rol",
    defaultVisibleColumns: ["name", "assigned_user_count", "updated_at"],
    columns: [
      { key: "name", label: "Rol" },
      { key: "description", label: "Descripción" },
      { key: "assigned_user_count", label: "Usuarios", type: "number" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [{ key: "search", label: "Buscar", type: "text", placeholder: "Rol o descripción..." }],
    fields: [
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "description", label: "Descripción", type: "textarea" }
    ]
  },
  business_area: {
    moduleKey: "business_areas",
    resource: "business_area",
    title: "Business Areas",
    subtitle: "Áreas funcionales del negocio.",
    createLabel: "Nueva área",
    defaultVisibleColumns: ["name", "code", "priority_level", "responsible_user_name", "is_active"],
    columns: [
      { key: "name", label: "Área" },
      { key: "code", label: "Código" },
      { key: "priority_level", label: "Prioridad", type: "status" },
      { key: "responsible_user_name", label: "Responsable" },
      { key: "is_active", label: "Activa", type: "boolean" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Nombre, código..." },
      {
        key: "activeState",
        label: "Activo",
        type: "select",
        options: [
          { value: "all", label: "Todos" },
          { value: "active", label: "Solo activos" },
          { value: "inactive", label: "Solo inactivos" }
        ],
        defaultValue: "active"
      },
      { key: "priorityLevel", label: "Priority level", type: "select", optionSource: "priorityLevel" },
      { key: "responsibleUserId", label: "Responsable", type: "select", optionSource: "users" }
    ],
    fields: [
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "code", label: "Código", type: "text" },
      { key: "priorityLevel", label: "Priority level", type: "select", optionSource: "priorityLevel" },
      { key: "responsibleUserId", label: "Responsable", type: "select", optionSource: "users" },
      { key: "isActive", label: "Activa", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" }
    ]
  },
  business_line: {
    moduleKey: "business_lines",
    resource: "business_line",
    title: "Business Lines",
    subtitle: "Líneas de negocio con ownership y estado.",
    createLabel: "Nueva línea",
    defaultVisibleColumns: ["name", "business_area_name", "current_status", "owner_user_name", "is_active"],
    columns: [
      { key: "name", label: "Línea" },
      { key: "business_area_name", label: "Área" },
      { key: "current_status", label: "Status", type: "status" },
      { key: "strategic_priority", label: "Prioridad", type: "status" },
      { key: "owner_user_name", label: "Owner" },
      { key: "is_active", label: "Activa", type: "boolean" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Nombre, modelo, monetización..." },
      {
        key: "activeState",
        label: "Activo",
        type: "select",
        options: [
          { value: "all", label: "Todos" },
          { value: "active", label: "Solo activos" },
          { value: "inactive", label: "Solo inactivos" }
        ],
        defaultValue: "active"
      },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "currentStatus", label: "Status", type: "select", optionSource: "businessLineStatus" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "strategicPriority", label: "Prioridad", type: "select", optionSource: "priorityLevel" }
    ],
    fields: [
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "currentStatus", label: "Status", type: "select", optionSource: "businessLineStatus" },
      { key: "strategicPriority", label: "Prioridad", type: "select", optionSource: "priorityLevel" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "isActive", label: "Activa", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" },
      { key: "businessModelSummary", label: "Modelo de negocio", type: "textarea" },
      { key: "monetizationNotes", label: "Notas de monetización", type: "textarea" }
    ]
  },
  business_priority: {
    moduleKey: "priorities",
    resource: "business_priority",
    title: "Priorities",
    subtitle: "Prioridades activas e históricas.",
    createLabel: "Nueva prioridad",
    defaultVisibleColumns: ["title", "status", "scope_label", "owner_user_name", "priority_order"],
    columns: [
      { key: "title", label: "Prioridad" },
      { key: "status", label: "Status", type: "status" },
      { key: "scope_label", label: "Scope" },
      { key: "owner_user_name", label: "Owner" },
      { key: "priority_order", label: "Orden", type: "number" },
      { key: "target_period", label: "Target period" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Título, descripción..." },
      { key: "status", label: "Status", type: "select", optionSource: "businessPriorityStatus" },
      { key: "scopeType", label: "Scope type", type: "select", optionSource: "scopeType" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "targetPeriod", label: "Target period", type: "text", placeholder: "Q2, 2026..." }
    ],
    fields: [
      { key: "title", label: "Título", type: "text", required: true },
      { key: "status", label: "Status", type: "select", optionSource: "businessPriorityStatus" },
      { key: "scopeType", label: "Scope type", type: "select", optionSource: "scopeType" },
      {
        key: "scopeId",
        label: "Scope item",
        type: "select",
        optionSource: "businessAreas",
        visibleWhen: { field: "scopeType", notEquals: "organization" }
      },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "priorityOrder", label: "Orden", type: "number" },
      { key: "targetPeriod", label: "Target period", type: "text" },
      { key: "description", label: "Descripción", type: "textarea" }
    ]
  },
  objective_record: {
    moduleKey: "objectives",
    resource: "objective_record",
    title: "Objectives",
    subtitle: "Objetivos estratégicos con avance.",
    createLabel: "Nuevo objetivo",
    defaultVisibleColumns: ["title", "status", "business_area_name", "owner_user_name", "completion_percent"],
    columns: [
      { key: "title", label: "Objetivo" },
      { key: "status", label: "Status", type: "status" },
      { key: "objective_type", label: "Tipo", type: "status" },
      { key: "business_area_name", label: "Área" },
      { key: "owner_user_name", label: "Owner" },
      { key: "completion_percent", label: "Avance", type: "number" },
      { key: "target_date", label: "Target date", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Título, descripción..." },
      { key: "status", label: "Status", type: "select", optionSource: "objectiveStatus" },
      { key: "objectiveType", label: "Objective type", type: "select", optionSource: "objectiveType" },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      {
        key: "completionBucket",
        label: "Completion bucket",
        type: "select",
        options: [
          { value: "0-24", label: "0-24%" },
          { value: "25-49", label: "25-49%" },
          { value: "50-74", label: "50-74%" },
          { value: "75-99", label: "75-99%" },
          { value: "100", label: "100%" }
        ]
      }
    ],
    fields: [
      { key: "title", label: "Título", type: "text", required: true },
      { key: "status", label: "Status", type: "select", optionSource: "objectiveStatus" },
      { key: "objectiveType", label: "Objective type", type: "select", optionSource: "objectiveType" },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "completionPercent", label: "Avance %", type: "number" },
      { key: "targetDate", label: "Target date", type: "date" },
      { key: "description", label: "Descripción", type: "textarea" }
    ]
  },
  external_system: {
    moduleKey: "external_systems",
    resource: "external_system",
    title: "External Systems",
    subtitle: "Sistemas externos reconocidos por el Brain.",
    createLabel: "Nuevo sistema",
    defaultVisibleColumns: ["name", "system_type", "is_active", "updated_at"],
    columns: [
      { key: "name", label: "Sistema" },
      { key: "system_type", label: "Tipo", type: "status" },
      { key: "is_active", label: "Activo", type: "boolean" },
      { key: "description", label: "Descripción" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Nombre o descripción..." },
      {
        key: "activeState",
        label: "Activo",
        type: "select",
        options: [
          { value: "all", label: "Todos" },
          { value: "active", label: "Solo activos" },
          { value: "inactive", label: "Solo inactivos" }
        ],
        defaultValue: "active"
      },
      { key: "systemType", label: "Tipo", type: "select", optionSource: "systemType" }
    ],
    fields: [
      { key: "name", label: "Nombre", type: "text", required: true },
      { key: "systemType", label: "Tipo", type: "select", optionSource: "systemType", required: true },
      { key: "isActive", label: "Activo", type: "checkbox" },
      { key: "description", label: "Descripción", type: "textarea" }
    ]
  },
  knowledge_document: {
    moduleKey: "knowledge_documents",
    resource: "knowledge_document",
    title: "Knowledge Documents",
    subtitle: "Metadata documental y links; sin upload en v1.",
    createLabel: "Nuevo documento",
    defaultVisibleColumns: ["title", "document_type", "status", "business_area_name", "version_label"],
    columns: [
      { key: "title", label: "Documento" },
      { key: "document_type", label: "Tipo", type: "status" },
      { key: "status", label: "Status", type: "status" },
      { key: "storage_type", label: "Storage", type: "status" },
      { key: "business_area_name", label: "Área" },
      { key: "owner_user_name", label: "Owner" },
      { key: "version_label", label: "Versión" },
      { key: "updated_at", label: "Actualizado", type: "date" }
    ],
    filters: [
      { key: "search", label: "Buscar", type: "text", placeholder: "Título, resumen..." },
      { key: "status", label: "Status", type: "select", optionSource: "knowledgeDocumentStatus" },
      { key: "documentType", label: "Tipo", type: "select", optionSource: "knowledgeDocumentType" },
      { key: "storageType", label: "Storage", type: "select", optionSource: "knowledgeStorageType" },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "versionLabel", label: "Versión", type: "text", placeholder: "v1, v2..." }
    ],
    fields: [
      { key: "title", label: "Título", type: "text", required: true },
      { key: "documentType", label: "Tipo", type: "select", optionSource: "knowledgeDocumentType" },
      { key: "status", label: "Status", type: "select", optionSource: "knowledgeDocumentStatus" },
      { key: "storageType", label: "Storage", type: "select", optionSource: "knowledgeStorageType" },
      { key: "businessAreaId", label: "Área", type: "select", optionSource: "businessAreas" },
      { key: "ownerUserId", label: "Owner", type: "select", optionSource: "users" },
      { key: "versionLabel", label: "Versión", type: "text" },
      { key: "externalUrl", label: "URL / referencia", type: "text" },
      { key: "summary", label: "Resumen", type: "textarea" }
    ]
  }
};

export const peopleFilters: BrainControlFilterConfig[] = [
  { key: "search", label: "Buscar", type: "text", placeholder: "Nombre, email, rol..." },
  {
    key: "activeState",
    label: "Activo",
    type: "select",
    options: [
      { value: "all", label: "Todos" },
      { value: "active", label: "Solo activos" },
      { value: "inactive", label: "Solo inactivos" }
    ],
    defaultValue: "active"
  },
  { key: "employmentStatus", label: "Employment status", type: "select", optionSource: "employmentStatus" },
  { key: "timezone", label: "Timezone", type: "text", placeholder: "America/Mexico_City" }
];

export const peopleColumns: BrainControlColumnConfig[] = [
  { key: "display_name", label: "Usuario" },
  { key: "email", label: "Email" },
  { key: "employment_status", label: "Employment", type: "status" },
  { key: "primary_role_label", label: "Rol principal" },
  { key: "primary_business_area_label", label: "Área principal" },
  { key: "is_active", label: "Activo", type: "boolean" },
  { key: "weekly_capacity_hours", label: "Capacidad h/sem", type: "number" }
];

export const peopleProfileFields: BrainControlFieldConfig[] = [
  { key: "firstName", label: "Nombre", type: "text", required: true },
  { key: "lastName", label: "Apellido", type: "text" },
  { key: "displayName", label: "Display name", type: "text", required: true },
  { key: "email", label: "Email", type: "text" },
  { key: "phone", label: "Teléfono", type: "text" },
  { key: "employmentStatus", label: "Employment status", type: "select", optionSource: "employmentStatus" },
  { key: "timezone", label: "Timezone", type: "text", placeholder: "America/Mexico_City" },
  { key: "roleSummary", label: "Resumen de rol", type: "text" },
  { key: "isActive", label: "Activo", type: "checkbox" },
  { key: "notes", label: "Notas", type: "textarea" }
];

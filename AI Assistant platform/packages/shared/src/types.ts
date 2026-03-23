export type ChannelType = "web" | "whatsapp" | "admin";

export type ExecutionMode = "auto" | "manual" | "hybrid";

export type ApprovalStatus = "pending" | "approved" | "rejected" | "executed";

export type AssistantTarget = "business_brain" | "pms";

export type ConnectionTestTarget = "database" | "openai" | "whatsapp";

export type RequiredDocumentKey =
  | "business_rules"
  | "stored_procedures"
  | "assistant_behavior"
  | "permissions"
  | "company_context";

export type SharedDocumentKey = "platform_overview" | "target_routing";

export interface TargetAssistantRuntimeInput {
  companyCode: string;
  defaultLocale?: string;
  defaultPropertyCode?: string;
  defaultActorUserId: number;
  whatsappActorUserId?: number;
  whatsappRolesCsv?: string;
  whatsappPermissionsCsv?: string;
}

export interface TargetDatabaseRuntimeInput {
  host: string;
  port: number;
  user: string;
  password?: string;
  database: string;
  connectionLimit?: number;
  ssl?: boolean;
}

export interface TargetRuntimeConfigInput {
  enabled: boolean;
  docsDirectory?: string;
  assistant: TargetAssistantRuntimeInput;
  database: TargetDatabaseRuntimeInput;
}

export interface OptimizationRuntimeConfigInput {
  cheapModeEnabled?: boolean;
  debugModelOverride?: string;
  disableBroadBrainSnapshots?: boolean;
  skipFinalLlmForSimpleReads?: boolean;
  logEstimatedCost?: boolean;
  maxRecentConversationMessages?: number;
  maxDocs?: number;
  maxDocsBundleBytes?: number;
}

export interface RuntimeConfigInput {
  tenantId: string;
  defaultTarget?: AssistantTarget;
  domains: Record<AssistantTarget, TargetRuntimeConfigInput>;
  openai: {
    apiKey?: string;
    model: string;
    baseUrl?: string;
    timeoutMs?: number;
  };
  whatsapp: {
    provider: "meta-cloud";
    baseUrl: string;
    phoneNumberId: string;
    businessAccountId?: string;
    apiToken?: string;
    appSecret?: string;
    webhookVerifyToken?: string;
  };
  execution: {
    mode: ExecutionMode;
    enableWrites: boolean;
  };
  optimization?: OptimizationRuntimeConfigInput;
}

export interface TargetAssistantRuntimeConfig {
  companyCode: string;
  defaultLocale: string;
  defaultPropertyCode?: string;
  defaultActorUserId: number;
  whatsappActorUserId: number;
  whatsappRolesCsv: string;
  whatsappPermissionsCsv: string;
}

export interface TargetDatabaseRuntimeConfig {
  host: string;
  port: number;
  user: string;
  password: string;
  database: string;
  connectionLimit: number;
  ssl: boolean;
}

export interface TargetDecryptedRuntimeConfig {
  enabled: boolean;
  docsDirectory: string;
  assistant: TargetAssistantRuntimeConfig;
  database: TargetDatabaseRuntimeConfig;
}

export interface TargetSanitizedRuntimeConfig {
  enabled: boolean;
  docsDirectory: string;
  assistant: TargetAssistantRuntimeConfig;
  database: {
    host: string;
    port: number;
    user: string;
    database: string;
    connectionLimit: number;
    ssl: boolean;
    hasPassword: boolean;
  };
}

export interface DecryptedRuntimeConfig {
  tenantId: string;
  defaultTarget: AssistantTarget;
  domains: Record<AssistantTarget, TargetDecryptedRuntimeConfig>;
  openai: {
    apiKey: string;
    model: string;
    baseUrl?: string;
    timeoutMs: number;
  };
  whatsapp: {
    provider: "meta-cloud";
    baseUrl: string;
    phoneNumberId: string;
    businessAccountId?: string;
    apiToken: string;
    appSecret?: string;
    webhookVerifyToken?: string;
  };
  execution: {
    mode: ExecutionMode;
    enableWrites: boolean;
  };
  optimization: Required<OptimizationRuntimeConfigInput>;
  updatedAt: string;
}

export interface SanitizedRuntimeConfig {
  tenantId: string;
  defaultTarget: AssistantTarget;
  domains: Record<AssistantTarget, TargetSanitizedRuntimeConfig>;
  openai: {
    model: string;
    baseUrl?: string;
    timeoutMs: number;
    hasApiKey: boolean;
  };
  whatsapp: {
    provider: "meta-cloud";
    baseUrl: string;
    phoneNumberId: string;
    businessAccountId?: string;
    hasApiToken: boolean;
    hasAppSecret: boolean;
    hasWebhookVerifyToken: boolean;
  };
  execution: {
    mode: ExecutionMode;
    enableWrites: boolean;
  };
  optimization: Required<OptimizationRuntimeConfigInput>;
  updatedAt: string;
}

export interface DocumentDescriptor {
  key: RequiredDocumentKey | SharedDocumentKey;
  title: string;
  path: string;
  exists: boolean;
  size: number;
  target?: AssistantTarget | "shared";
  lastModifiedAt?: string;
  content?: string;
  contentHash?: string;
}

export interface ActionProposal {
  intent: string;
  confidence: number;
  action: string;
  arguments: Record<string, unknown>;
  summary: string;
  needsHumanApproval: boolean;
}

export interface ActionCatalogEntry {
  target: AssistantTarget;
  name: string;
  description: string;
  executable: boolean;
  procedureName?: string;
  mode?: "read" | "write";
  requiredPermissions: string[];
  requiredArguments: string[];
}

export interface AssistantRequest {
  tenantId: string;
  target: AssistantTarget;
  requestId?: string;
  companyCode: string;
  conversationId: string;
  userId: string;
  actorUserId: number;
  message: string;
  propertyCode?: string;
  locale?: string;
  channel: ChannelType;
  roles: string[];
  permissions: string[];
}

export interface AssistantResponse {
  status: "completed" | "pending_approval" | "clarification";
  answer: string;
  requestId?: string;
  approvalId?: string;
  actionProposal: ActionProposal;
  result?: unknown;
}

export interface ApprovalRecord {
  id: string;
  tenantId: string;
  target: AssistantTarget;
  conversationId: string;
  status: ApprovalStatus;
  requestContext: Omit<AssistantRequest, "message">;
  requestedBy: string;
  requestedAt: string;
  decidedBy?: string;
  decidedAt?: string;
  procedureName?: string;
  actionProposal: ActionProposal;
  executionPreview: {
    target: AssistantTarget;
    procedureName?: string;
    arguments: Record<string, unknown>;
    mode: "read" | "write" | "none";
  };
  result?: unknown;
}

export interface ConnectionTestRequest {
  target: ConnectionTestTarget;
  domainTarget?: AssistantTarget;
  candidateConfig?: Partial<RuntimeConfigInput>;
}

export interface ConnectionTestResult {
  target: ConnectionTestTarget;
  domainTarget?: AssistantTarget;
  success: boolean;
  details: string;
  durationMs: number;
}

export interface ConversationMessage {
  id: string;
  role: "system" | "user" | "assistant";
  content: string;
  createdAt: string;
}

export interface ConversationRecord {
  id: string;
  tenantId: string;
  target: AssistantTarget;
  channel: ChannelType;
  userId: string;
  summary: string;
  messages: ConversationMessage[];
  context: Record<string, unknown>;
  updatedAt: string;
}

export interface LogEvent {
  id: string;
  type: string;
  level: "info" | "warn" | "error";
  message: string;
  timestamp: string;
  target?: AssistantTarget | "shared";
  payload?: unknown;
}

export interface MobilePmsRequestContext {
  tenantId: string;
  companyCode: string;
  userId: string;
  actorUserId: number;
  locale?: string;
}

export interface AvailabilityFilters {
  propertyCode: string | null;
  dateStart: string;
  dateEnd: string | null;
  nights: number | null;
  people: number | null;
  visibleWindowDays: number;
}

export interface ReservationDraftPreview {
  source: "mobile.availability.phase1";
  action:
    | "book_requested_stay"
    | "book_one_night"
    | "book_continuous_stay";
  tenantId: string;
  companyCode: string;
  actorUserId: number;
  propertyCode: string;
  propertyName: string;
  roomCode: string;
  roomName: string;
  categoryCode: string;
  categoryName: string;
  checkIn: string;
  checkOut: string;
  nights: number;
  people: number | null;
  currency: string;
  nightlyPriceCents: number | null;
  totalCents: number | null;
  visibleContinuousNights: number;
}

export interface AvailabilityRoomAction {
  kind:
    | "book_requested_stay"
    | "book_one_night"
    | "book_continuous_stay";
  label: string;
  draft: ReservationDraftPreview;
}

export interface AvailabilityRoomCard {
  propertyCode: string;
  propertyName: string;
  roomCode: string;
  roomName: string;
  roomId: number;
  categoryCode: string;
  categoryName: string;
  categoryId: number;
  requestedStartDate: string;
  visibleContinuousNights: number;
  requestedNights: number | null;
  people: number | null;
  currency: string;
  nightlyPriceCents: number | null;
  requestedStayTotalCents: number | null;
  continuousStayTotalCents: number | null;
  capacityTotal: number | null;
  maxAdults: number | null;
  maxChildren: number | null;
  actions: AvailabilityRoomAction[];
}

export interface AvailabilityPropertyGroup {
  propertyCode: string;
  propertyName: string;
  currency: string;
  roomCount: number;
  rooms: AvailabilityRoomCard[];
}

export interface MobilePmsBootstrapProperty {
  id: number;
  code: string;
  name: string;
  currency: string;
}

export interface MobilePmsBootstrapCategory {
  id: number;
  propertyCode: string;
  code: string;
  name: string;
  maxOccupancy: number | null;
  rateplanId: number | null;
}

export interface MobilePmsBootstrapRoom {
  id: number;
  propertyCode: string;
  code: string;
  name: string;
  categoryCode: string;
  categoryName: string;
  categoryId: number | null;
  rateplanId: number | null;
  capacityTotal: number | null;
  maxAdults: number | null;
  maxChildren: number | null;
}

export interface MobilePmsBootstrapBedConfiguration {
  propertyCode: string;
  roomCode: string;
  label: string;
}

export interface MobilePmsBootstrapResponse {
  context: {
    tenantId: string;
    companyCode: string;
    actorUserId: number;
    allowedPropertyCodes: string[];
    permissionCodes: string[];
    isOwner: boolean;
    authzMode: "audit" | "enforce";
  };
  defaults: AvailabilityFilters;
  properties: MobilePmsBootstrapProperty[];
  categories: MobilePmsBootstrapCategory[];
  rooms: MobilePmsBootstrapRoom[];
  bedConfigurations: MobilePmsBootstrapBedConfiguration[];
}

export interface MobilePmsAvailabilityResponse {
  context: MobilePmsBootstrapResponse["context"];
  filtersApplied: AvailabilityFilters;
  totalMatches: number;
  groups: AvailabilityPropertyGroup[];
}

export type BrainAdminResourceKey =
  | "organization"
  | "user_account"
  | "role"
  | "user_role"
  | "user_area_assignment"
  | "user_capacity_profile"
  | "business_area"
  | "business_line"
  | "business_priority"
  | "objective_record"
  | "external_system"
  | "knowledge_document";

export type BrainAdminModuleKey =
  | "overview"
  | "organization"
  | "people"
  | "roles_access"
  | "business_areas"
  | "business_lines"
  | "priorities"
  | "objectives"
  | "external_systems"
  | "knowledge_documents";

export type BrainAdminSortDirection = "asc" | "desc";

export interface BrainAdminReferenceOption {
  value: string | number;
  label: string;
  description?: string;
  meta?: Record<string, unknown>;
}

export interface BrainAdminModuleDescriptor {
  key: BrainAdminModuleKey;
  title: string;
  description: string;
  resourceKey?: BrainAdminResourceKey;
}

export interface BrainAdminActorContext {
  defaultActorUserId: number;
  sessionActorUserId: number | null;
  effectiveActorUserId: number;
  resolvedOrganizationId: number;
  actorFound: boolean;
  bootstrapMode: boolean;
  writeMode: "full" | "bootstrap_only" | "blocked";
  writableResources: BrainAdminResourceKey[];
  reason?: string;
}

export interface BrainAdminBootstrap {
  target: "business_brain";
  organization: Record<string, unknown> | null;
  actorContext: BrainAdminActorContext;
  modules: BrainAdminModuleDescriptor[];
}

export interface BrainAdminSummary {
  target: "business_brain";
  actorContext: BrainAdminActorContext;
  counts: Partial<Record<BrainAdminResourceKey, number>>;
  recentChanges: Record<string, unknown>[];
}

export interface BrainAdminReferenceOptions {
  actorContext: BrainAdminActorContext;
  users: BrainAdminReferenceOption[];
  roles: BrainAdminReferenceOption[];
  businessAreas: BrainAdminReferenceOption[];
  businessLines: BrainAdminReferenceOption[];
  priorities: BrainAdminReferenceOption[];
  statuses: Record<string, BrainAdminReferenceOption[]>;
}

export interface BrainAdminListQuery {
  search?: string;
  page?: number;
  pageSize?: number;
  sortBy?: string;
  sortDir?: BrainAdminSortDirection;
  onlyActive?: boolean;
  filters?: Record<string, unknown>;
}

export interface BrainAdminListResponse<T = Record<string, unknown>> {
  resource: BrainAdminResourceKey;
  items: T[];
  total: number;
  page: number;
  pageSize: number;
  totalPages: number;
  sortBy?: string;
  sortDir: BrainAdminSortDirection;
  filtersApplied: Record<string, unknown>;
  actorContext: BrainAdminActorContext;
}

export interface BrainAdminDetailResponse<T = Record<string, unknown>> {
  resource: BrainAdminResourceKey;
  item: T | null;
  actorContext: BrainAdminActorContext;
  related?: Record<string, unknown>;
}

export interface BrainAdminSavePayload {
  values: Record<string, unknown>;
}

export interface BrainAdminDeletePayload {
  reason?: string;
}

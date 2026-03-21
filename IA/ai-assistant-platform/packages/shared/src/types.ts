export type ChannelType = "web" | "whatsapp" | "admin";

export type ExecutionMode = "auto" | "manual" | "hybrid";

export type ApprovalStatus = "pending" | "approved" | "rejected" | "executed";

export type ConnectionTestTarget = "database" | "openai" | "whatsapp";

export type RequiredDocumentKey =
  | "business_rules"
  | "stored_procedures"
  | "assistant_behavior"
  | "permissions"
  | "company_context";

export interface RuntimeConfigInput {
  tenantId: string;
  docsDirectory?: string;
  assistant: {
    companyCode: string;
    defaultLocale?: string;
    defaultPropertyCode?: string;
    defaultActorUserId: number;
    whatsappActorUserId?: number;
    whatsappRolesCsv?: string;
    whatsappPermissionsCsv?: string;
  };
  database: {
    host: string;
    port: number;
    user: string;
    password?: string;
    database: string;
    connectionLimit?: number;
    ssl?: boolean;
  };
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
}

export interface DecryptedRuntimeConfig {
  tenantId: string;
  docsDirectory: string;
  assistant: {
    companyCode: string;
    defaultLocale: string;
    defaultPropertyCode?: string;
    defaultActorUserId: number;
    whatsappActorUserId: number;
    whatsappRolesCsv: string;
    whatsappPermissionsCsv: string;
  };
  database: {
    host: string;
    port: number;
    user: string;
    password: string;
    database: string;
    connectionLimit: number;
    ssl: boolean;
  };
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
  updatedAt: string;
}

export interface SanitizedRuntimeConfig {
  tenantId: string;
  docsDirectory: string;
  assistant: {
    companyCode: string;
    defaultLocale: string;
    defaultPropertyCode?: string;
    defaultActorUserId: number;
    whatsappActorUserId: number;
    whatsappRolesCsv: string;
    whatsappPermissionsCsv: string;
  };
  database: {
    host: string;
    port: number;
    user: string;
    database: string;
    connectionLimit: number;
    ssl: boolean;
    hasPassword: boolean;
  };
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
  updatedAt: string;
}

export interface DocumentDescriptor {
  key: RequiredDocumentKey;
  title: string;
  path: string;
  exists: boolean;
  size: number;
  lastModifiedAt?: string;
  content?: string;
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
  approvalId?: string;
  actionProposal: ActionProposal;
  result?: unknown;
}

export interface ApprovalRecord {
  id: string;
  tenantId: string;
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
    procedureName?: string;
    arguments: Record<string, unknown>;
    mode: "read" | "write" | "none";
  };
  result?: unknown;
}

export interface ConnectionTestRequest {
  target: ConnectionTestTarget;
  candidateConfig?: Partial<RuntimeConfigInput>;
}

export interface ConnectionTestResult {
  target: ConnectionTestTarget;
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
  payload?: unknown;
}

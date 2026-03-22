import {
  AssistantTarget,
  ActionCatalogEntry,
  ApprovalRecord,
  AssistantRequest,
  AssistantResponse,
  ConnectionTestRequest,
  ConnectionTestResult,
  ConversationRecord,
  DocumentDescriptor,
  LogEvent,
  RuntimeConfigInput,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(path, {
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers ?? {})
    },
    ...init
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    const message = payload?.error?.message ?? `Request failed with ${response.status}`;
    throw new Error(message);
  }

  return (await response.json()) as T;
}

export const api = {
  getHealth: () =>
    request<{ ok: boolean; configured: boolean; tenantId: string | null; defaultTarget: AssistantTarget | null }>("/api/health"),
  getConfig: () => request<{ config: SanitizedRuntimeConfig | null }>("/api/config"),
  saveConfig: (input: RuntimeConfigInput) =>
    request<{ config: SanitizedRuntimeConfig }>("/api/config", {
      method: "POST",
      body: JSON.stringify(input)
    }),
  testConnection: (input: ConnectionTestRequest) =>
    request<{ result: ConnectionTestResult }>("/api/config/test", {
      method: "POST",
      body: JSON.stringify(input)
    }),
  getDocs: (target: AssistantTarget, includeContent = false) =>
    request<{ documents: DocumentDescriptor[] }>(
      `/api/docs?target=${target}&includeContent=${String(includeContent)}`
    ),
  getApprovals: (target?: AssistantTarget) =>
    request<{ approvals: ApprovalRecord[] }>(
      `/api/approvals${target ? `?target=${target}` : ""}`
    ),
  approve: (approvalId: string, approverId: string) =>
    request(`/api/approvals/${approvalId}/approve`, {
      method: "POST",
      body: JSON.stringify({ approverId })
    }),
  reject: (approvalId: string, approverId: string) =>
    request(`/api/approvals/${approvalId}/reject`, {
      method: "POST",
      body: JSON.stringify({ approverId })
    }),
  getLogs: (limit = 100, target?: AssistantTarget | "shared") =>
    request<{ events: LogEvent[] }>(`/api/logs?limit=${limit}${target ? `&target=${target}` : ""}`),
  getActions: (target: AssistantTarget) =>
    request<{ actions: ActionCatalogEntry[] }>(`/api/actions?target=${target}`),
  sendAssistantMessage: (input: AssistantRequest) =>
    request<AssistantResponse>("/api/assistant/messages", {
      method: "POST",
      body: JSON.stringify(input)
    }),
  getConversation: (conversationId: string) =>
    request<{ conversation: ConversationRecord | null }>(`/api/conversations/${conversationId}`),
  getConversations: (limit = 20, target?: AssistantTarget) =>
    request<{ conversations: ConversationRecord[] }>(
      `/api/conversations?limit=${limit}${target ? `&target=${target}` : ""}`
    )
};

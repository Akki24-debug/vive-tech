import {
  AssistantTarget,
  ActionCatalogEntry,
  ApprovalRecord,
  AssistantRequest,
  AssistantResponse,
  BrainAdminBootstrap,
  BrainAdminDeletePayload,
  BrainAdminDetailResponse,
  BrainAdminListResponse,
  BrainAdminReferenceOptions,
  BrainAdminResourceKey,
  BrainAdminSavePayload,
  BrainAdminSummary,
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
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers ?? {})
    }
  });

  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    const message = payload?.error?.message ?? `Request failed with ${response.status}`;
    throw new Error(message);
  }

  return (await response.json()) as T;
}

function buildBrainAdminHeaders(actorUserId?: number | null): HeadersInit | undefined {
  if (typeof actorUserId !== "number" || !Number.isFinite(actorUserId)) {
    return undefined;
  }

  return {
    "x-brain-actor-user-id": String(actorUserId)
  };
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
  getBrainAdminBootstrap: (actorUserId?: number | null) =>
    request<BrainAdminBootstrap>("/api/brain-admin/bootstrap", {
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  getBrainAdminSummary: (actorUserId?: number | null) =>
    request<BrainAdminSummary>("/api/brain-admin/summary", {
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  getBrainAdminOptions: (actorUserId?: number | null) =>
    request<BrainAdminReferenceOptions>("/api/brain-admin/options", {
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  getBrainAdminResource: (
    resource: BrainAdminResourceKey,
    query: Record<string, unknown>,
    actorUserId?: number | null
  ) => {
    const searchParams = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null || value === "") {
        continue;
      }

      searchParams.set(key, String(value));
    }

    const suffix = searchParams.size ? `?${searchParams.toString()}` : "";
    return request<BrainAdminListResponse>(`/api/brain-admin/${resource}${suffix}`, {
      headers: buildBrainAdminHeaders(actorUserId)
    });
  },
  getBrainAdminDetail: (
    resource: BrainAdminResourceKey,
    id: number,
    actorUserId?: number | null
  ) =>
    request<BrainAdminDetailResponse>(`/api/brain-admin/${resource}/${id}`, {
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  saveBrainAdminResource: (
    resource: BrainAdminResourceKey,
    payload: BrainAdminSavePayload,
    id?: number | null,
    actorUserId?: number | null
  ) =>
    request<BrainAdminDetailResponse>(
      id ? `/api/brain-admin/${resource}/${id}` : `/api/brain-admin/${resource}`,
      {
        method: id ? "PUT" : "POST",
        headers: buildBrainAdminHeaders(actorUserId),
        body: JSON.stringify(payload)
      }
    ),
  deleteBrainAdminResource: (
    resource: BrainAdminResourceKey,
    id: number,
    payload: BrainAdminDeletePayload,
    actorUserId?: number | null
  ) =>
    request<Record<string, unknown>>(`/api/brain-admin/${resource}/${id}`, {
      method: "DELETE",
      headers: buildBrainAdminHeaders(actorUserId),
      body: JSON.stringify(payload)
    }),
  getBrainAdminUserContext: (id: number, actorUserId?: number | null) =>
    request<BrainAdminDetailResponse>(`/api/brain-admin/users/${id}/context`, {
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  syncBrainAdminUserRoles: (id: number, roleIds: number[], actorUserId?: number | null) =>
    request<BrainAdminDetailResponse>(`/api/brain-admin/users/${id}/roles/sync`, {
      method: "POST",
      headers: buildBrainAdminHeaders(actorUserId),
      body: JSON.stringify({ roleIds })
    }),
  publishBrainAdminKnowledgeDocument: (id: number, actorUserId?: number | null) =>
    request<BrainAdminDetailResponse>(`/api/brain-admin/knowledge-documents/${id}/publish`, {
      method: "POST",
      headers: buildBrainAdminHeaders(actorUserId)
    }),
  getConversation: (conversationId: string) =>
    request<{ conversation: ConversationRecord | null }>(`/api/conversations/${conversationId}`),
  getConversations: (limit = 20, target?: AssistantTarget) =>
    request<{ conversations: ConversationRecord[] }>(
      `/api/conversations?limit=${limit}${target ? `&target=${target}` : ""}`
    )
};

import { FormEvent, useEffect, useState } from "react";

import { AssistantRequest, AssistantResponse, ConversationRecord, SanitizedRuntimeConfig } from "@vlv-ai/shared";

import { api } from "../../api/client";
import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface ChatPanelProps {
  configured: boolean;
  config: SanitizedRuntimeConfig | null;
}

interface ChatDraft {
  conversationId: string;
  userId: string;
  actorUserId: number;
  propertyCode: string;
  locale: string;
  channel: AssistantRequest["channel"];
  rolesCsv: string;
  permissionsCsv: string;
  message: string;
}

const suggestedPermissionSet = [
  "assistant.read.availability",
  "assistant.read.pricing",
  "assistant.read.properties",
  "assistant.read.guests",
  "assistant.read.catalog",
  "assistant.read.reservations",
  "assistant.read.operations",
  "assistant.write.reservations"
].join(", ");

export function ChatPanel({ configured, config }: ChatPanelProps) {
  const [draft, setDraft] = useState<ChatDraft>(() => createDraft(config));
  const [conversation, setConversation] = useState<ConversationRecord | null>(null);
  const [recentConversations, setRecentConversations] = useState<ConversationRecord[]>([]);
  const [lastResponse, setLastResponse] = useState<AssistantResponse | null>(null);
  const [error, setError] = useState("");
  const [sending, setSending] = useState(false);

  useEffect(() => {
    const conversationId = localStorage.getItem("vlv-ai-conversation-id");
    const nextDraft = createDraft(config, conversationId ?? undefined);
    setDraft((current) => ({
      ...nextDraft,
      conversationId: conversationId ?? current.conversationId,
      message: current.message
    }));
  }, [config]);

  useEffect(() => {
    void refreshConversations();
  }, []);

  useEffect(() => {
    localStorage.setItem("vlv-ai-conversation-id", draft.conversationId);
    void loadConversation(draft.conversationId);
  }, [draft.conversationId]);

  async function refreshConversations(): Promise<void> {
    const response = await api.getConversations(12);
    setRecentConversations(response.conversations);
  }

  async function loadConversation(conversationId: string): Promise<void> {
    const response = await api.getConversation(conversationId);
    setConversation(response.conversation);
  }

  async function handleSubmit(event: FormEvent): Promise<void> {
    event.preventDefault();

    if (!configured || !config) {
      setError("Save runtime configuration first.");
      return;
    }

    if (!draft.message.trim()) {
      return;
    }

    setSending(true);
    setError("");

    try {
      const payload: AssistantRequest = {
        tenantId: config.tenantId,
        companyCode: config.assistant.companyCode,
        conversationId: draft.conversationId,
        userId: draft.userId,
        actorUserId: draft.actorUserId,
        message: draft.message.trim(),
        propertyCode: draft.propertyCode || undefined,
        locale: draft.locale || undefined,
        channel: draft.channel,
        roles: parseCsv(draft.rolesCsv),
        permissions: parseCsv(draft.permissionsCsv)
      };

      const response = await api.sendAssistantMessage(payload);
      setLastResponse(response);
      setDraft((current) => ({
        ...current,
        message: ""
      }));
      await loadConversation(draft.conversationId);
      await refreshConversations();
    } catch (caughtError) {
      setError(caughtError instanceof Error ? caughtError.message : "Request failed.");
    } finally {
      setSending(false);
    }
  }

  function handleNewConversation(): void {
    const nextId = `admin_${Date.now()}`;
    setDraft((current) => ({
      ...current,
      conversationId: nextId,
      message: ""
    }));
    setConversation(null);
    setLastResponse(null);
    setError("");
  }

  return (
    <div className="panel-grid panel-grid--chat">
      <SectionCard
        title="Test Chat"
        subtitle="Direct console against the Node orchestration backend"
        actions={
          <div className="button-row">
            <button className="button button--secondary" onClick={handleNewConversation} type="button">
              New conversation
            </button>
            <button className="button button--secondary" onClick={() => void refreshConversations()} type="button">
              Reload recent
            </button>
          </div>
        }
      >
        <form className="chat-layout" onSubmit={handleSubmit}>
          <div className="chat-sidebar">
            <div className="form-grid">
              <label>
                <span>Conversation ID</span>
                <input
                  className="input"
                  value={draft.conversationId}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, conversationId: event.target.value }))
                  }
                />
              </label>
              <label>
                <span>Channel User ID</span>
                <input
                  className="input"
                  value={draft.userId}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, userId: event.target.value }))
                  }
                />
              </label>
              <label>
                <span>PMS Actor User ID</span>
                <input
                  className="input"
                  type="number"
                  value={draft.actorUserId}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, actorUserId: Number(event.target.value) }))
                  }
                />
              </label>
              <label>
                <span>Property Scope</span>
                <input
                  className="input"
                  value={draft.propertyCode}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, propertyCode: event.target.value }))
                  }
                />
              </label>
              <label>
                <span>Locale</span>
                <input
                  className="input"
                  value={draft.locale}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, locale: event.target.value }))
                  }
                />
              </label>
              <label>
                <span>Simulated Channel</span>
                <select
                  className="input"
                  value={draft.channel}
                  onChange={(event) =>
                    setDraft((current) => ({
                      ...current,
                      channel: event.target.value as AssistantRequest["channel"]
                    }))
                  }
                >
                  <option value="admin">admin</option>
                  <option value="web">web</option>
                  <option value="whatsapp">whatsapp</option>
                </select>
              </label>
              <label>
                <span>Roles CSV</span>
                <input
                  className="input"
                  value={draft.rolesCsv}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, rolesCsv: event.target.value }))
                  }
                />
              </label>
              <label>
                <span>Permissions CSV</span>
                <textarea
                  className="input input--multiline"
                  rows={4}
                  value={draft.permissionsCsv}
                  onChange={(event) =>
                    setDraft((current) => ({ ...current, permissionsCsv: event.target.value }))
                  }
                />
              </label>
            </div>

            <div className="chat-presets">
              <button
                className="button button--secondary"
                onClick={() =>
                  setDraft((current) => ({
                    ...current,
                    rolesCsv: "admin",
                    permissionsCsv: ""
                  }))
                }
                type="button"
              >
                Use admin preset
              </button>
              <button
                className="button button--secondary"
                onClick={() =>
                  setDraft((current) => ({
                    ...current,
                    rolesCsv: "",
                    permissionsCsv: suggestedPermissionSet
                  }))
                }
                type="button"
              >
                Use explicit permissions
              </button>
            </div>

            <div className="stack-list">
              <h3>Recent conversations</h3>
              {recentConversations.map((entry) => (
                <button
                  className="chat-history-item"
                  key={entry.id}
                  onClick={() =>
                    setDraft((current) => ({
                      ...current,
                      conversationId: entry.id
                    }))
                  }
                  type="button"
                >
                  <strong>{entry.id}</strong>
                  <span>{entry.updatedAt}</span>
                  <small>{entry.summary || "No summary yet."}</small>
                </button>
              ))}
              {recentConversations.length === 0 ? <p>No saved conversations yet.</p> : null}
            </div>
          </div>

          <div className="chat-main">
            <div className="chat-messages">
              {conversation?.messages.map((message: ConversationRecord["messages"][number]) => (
                <article
                  className={
                    message.role === "user"
                      ? "chat-message chat-message--user"
                      : "chat-message chat-message--assistant"
                  }
                  key={message.id}
                >
                  <header className="inline-row">
                    <strong>{message.role}</strong>
                    <small>{message.createdAt}</small>
                  </header>
                  <p>{message.content}</p>
                </article>
              ))}
              {!conversation?.messages.length ? (
                <div className="chat-empty">
                  <p>The conversation is empty. Send the first message from this console.</p>
                </div>
              ) : null}
            </div>

            <label className="chat-composer">
              <span>Message</span>
              <textarea
                className="input input--multiline"
                rows={5}
                value={draft.message}
                onChange={(event) =>
                  setDraft((current) => ({ ...current, message: event.target.value }))
                }
              />
            </label>

            <div className="button-row">
              <button className="button" disabled={sending || !configured} type="submit">
                {sending ? "Sending..." : "Send message"}
              </button>
              {error ? <span className="feedback feedback--error">{error}</span> : null}
            </div>

            {lastResponse ? (
              <div className="chat-response-meta">
                <div className="inline-row">
                  <StatusBadge
                    label={lastResponse.status}
                    tone={
                      lastResponse.status === "completed"
                        ? "success"
                        : lastResponse.status === "pending_approval"
                          ? "warning"
                          : "neutral"
                    }
                  />
                  <strong>{lastResponse.actionProposal.action}</strong>
                </div>
                <p>{lastResponse.actionProposal.summary}</p>
                {lastResponse.approvalId ? <p>Approval ID: {lastResponse.approvalId}</p> : null}
                <pre className="code-block">
                  {JSON.stringify(
                    {
                      actionProposal: lastResponse.actionProposal,
                      result: lastResponse.result
                    },
                    null,
                    2
                  )}
                </pre>
              </div>
            ) : null}
          </div>
        </form>
      </SectionCard>
    </div>
  );
}

function createDraft(
  config: SanitizedRuntimeConfig | null,
  conversationId = `admin_${Date.now()}`
): ChatDraft {
  return {
    conversationId,
    userId: "admin-console",
    actorUserId: config?.assistant.defaultActorUserId ?? 1,
    propertyCode: config?.assistant.defaultPropertyCode ?? "",
    locale: config?.assistant.defaultLocale ?? "es-MX",
    channel: "admin",
    rolesCsv: "admin",
    permissionsCsv: "",
    message: ""
  };
}

function parseCsv(value: string): string[] {
  return value
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);
}

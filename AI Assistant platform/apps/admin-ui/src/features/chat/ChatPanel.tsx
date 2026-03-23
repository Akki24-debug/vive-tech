import { FormEvent, KeyboardEvent, useEffect, useRef, useState } from "react";

import {
  AssistantRequest,
  AssistantResponse,
  AssistantTarget,
  ConversationRecord,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";

import { api } from "../../api/client";
import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface ChatPanelProps {
  configured: boolean;
  config: SanitizedRuntimeConfig | null;
  selectedTarget: AssistantTarget;
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

interface PendingExchange {
  userMessage: string;
  createdAt: string;
}

type DisplayMessage = ConversationRecord["messages"][number] & {
  pending?: boolean;
};

const suggestedPermissionsByTarget: Record<AssistantTarget, string> = {
  business_brain: [
    "brain.read.context",
    "brain.read.organization",
    "brain.read.users",
    "brain.read.roles",
    "brain.read.business_areas",
    "brain.read.business_lines",
    "brain.read.priorities",
    "brain.read.objectives",
    "brain.read.integrations",
    "brain.read.knowledge",
    "brain.write.organization",
    "brain.write.users",
    "brain.write.roles",
    "brain.write.business_areas",
    "brain.write.business_lines",
    "brain.write.priorities",
    "brain.write.objectives",
    "brain.write.integrations",
    "brain.write.knowledge"
  ].join(", "),
  pms: [
    "assistant.read.availability",
    "assistant.read.pricing",
    "assistant.read.properties",
    "assistant.read.guests",
    "assistant.read.catalog",
    "assistant.read.reservations",
    "assistant.read.operations",
    "assistant.write.reservations"
  ].join(", ")
};

export function ChatPanel({ configured, config, selectedTarget }: ChatPanelProps) {
  const [draft, setDraft] = useState<ChatDraft>(() => createDraft(config, selectedTarget));
  const [conversation, setConversation] = useState<ConversationRecord | null>(null);
  const [recentConversations, setRecentConversations] = useState<ConversationRecord[]>([]);
  const [lastResponse, setLastResponse] = useState<AssistantResponse | null>(null);
  const [pendingExchange, setPendingExchange] = useState<PendingExchange | null>(null);
  const [thinkingFrame, setThinkingFrame] = useState(0);
  const [error, setError] = useState("");
  const [sending, setSending] = useState(false);
  const chatBottomRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const conversationId = localStorage.getItem(`vlv-ai-conversation-id:${selectedTarget}`);
    const nextDraft = createDraft(config, selectedTarget, conversationId ?? undefined);
    setDraft((current) => ({
      ...nextDraft,
      conversationId: conversationId ?? current.conversationId,
      message: current.message
    }));
  }, [config, selectedTarget]);

  useEffect(() => {
    void refreshConversations(selectedTarget);
  }, [selectedTarget]);

  useEffect(() => {
    localStorage.setItem(`vlv-ai-conversation-id:${selectedTarget}`, draft.conversationId);
    void loadConversation(draft.conversationId);
  }, [draft.conversationId, selectedTarget]);

  useEffect(() => {
    if (!pendingExchange) {
      return;
    }

    const interval = window.setInterval(() => {
      setThinkingFrame((current) => (current + 1) % 4);
    }, 480);

    return () => window.clearInterval(interval);
  }, [pendingExchange]);

  useEffect(() => {
    const frame = window.requestAnimationFrame(() => {
      chatBottomRef.current?.scrollIntoView({
        block: "end",
        behavior: pendingExchange ? "smooth" : "auto"
      });
    });

    return () => window.cancelAnimationFrame(frame);
  }, [displayMessageCount(conversation, pendingExchange), pendingExchange, lastResponse]);

  async function refreshConversations(target: AssistantTarget): Promise<void> {
    const response = await api.getConversations(12, target);
    setRecentConversations(response.conversations);
  }

  async function loadConversation(conversationId: string): Promise<void> {
    const response = await api.getConversation(conversationId);
    setConversation(response.conversation?.target === selectedTarget ? response.conversation : null);
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

    const submittedMessage = draft.message.trim();
    setPendingExchange({
      userMessage: submittedMessage,
      createdAt: new Date().toISOString()
    });
    setDraft((current) => ({
      ...current,
      message: ""
    }));

    try {
      const domain = config.domains[selectedTarget];
      const payload: AssistantRequest = {
        tenantId: config.tenantId,
        target: selectedTarget,
        companyCode: domain.assistant.companyCode,
        conversationId: draft.conversationId,
        userId: draft.userId,
        actorUserId: draft.actorUserId,
        message: submittedMessage,
        propertyCode: draft.propertyCode || undefined,
        locale: draft.locale || undefined,
        channel: draft.channel,
        roles: parseCsv(draft.rolesCsv),
        permissions: parseCsv(draft.permissionsCsv)
      };

      const response = await api.sendAssistantMessage(payload);
      setLastResponse(response);
      setPendingExchange(null);
      await loadConversation(draft.conversationId);
      await refreshConversations(selectedTarget);
    } catch (caughtError) {
      setPendingExchange(null);
      setDraft((current) => ({
        ...current,
        message: current.message || submittedMessage
      }));
      setError(caughtError instanceof Error ? caughtError.message : "Request failed.");
    } finally {
      setSending(false);
    }
  }

  function handleComposerKeyDown(event: KeyboardEvent<HTMLTextAreaElement>): void {
    if (event.key === "Enter" && event.ctrlKey) {
      event.preventDefault();

      if (sending || !configured || !draft.message.trim()) {
        return;
      }

      void handleSubmit(event as unknown as FormEvent);
    }
  }

  function handleNewConversation(): void {
    const nextId = `${selectedTarget}_${Date.now()}`;
    setDraft((current) => ({
      ...current,
      conversationId: nextId,
      message: ""
    }));
    setConversation(null);
    setLastResponse(null);
    setPendingExchange(null);
    setError("");
  }

  const displayMessages: DisplayMessage[] = [
    ...(conversation?.messages ?? []),
    ...(pendingExchange
      ? [
          {
            id: "pending-user",
            role: "user" as const,
            content: pendingExchange.userMessage,
            createdAt: pendingExchange.createdAt
          },
          {
            id: "pending-assistant",
            role: "assistant" as const,
            content: buildThinkingCopy(thinkingFrame),
            createdAt: pendingExchange.createdAt,
            pending: true
          }
        ]
      : [])
  ];
  const isEmptyChatState = displayMessages.length === 0 && !lastResponse;

  return (
    <div className="panel-grid panel-grid--chat">
      <SectionCard
        title="Test Chat"
        subtitle={`Direct console against the ${selectedTarget} runtime`}
        actions={
          <div className="button-row">
            <button className="button button--secondary" onClick={handleNewConversation} type="button">
              New conversation
            </button>
            <button
              className="button button--secondary"
              onClick={() => void refreshConversations(selectedTarget)}
              type="button"
            >
              Reload recent
            </button>
          </div>
        }
      >
        <form className="chat-layout" onSubmit={handleSubmit}>
          <div className="chat-sidebar">
            <div className="form-grid">
              <label>
                <span>Target</span>
                <input className="input" disabled value={selectedTarget} />
              </label>
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
                <span>Actor User ID</span>
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
                  rows={5}
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
                    permissionsCsv: suggestedPermissionsByTarget[selectedTarget]
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

          <div className={isEmptyChatState ? "chat-main chat-main--empty" : "chat-main"}>
            {isEmptyChatState ? (
              <div className="chat-boot-panel">
                <div className="chat-console-meta">
                  <span>{selectedTarget}</span>
                  <span>console://admin</span>
                  <span>ai_state=idle</span>
                </div>
                <div className="chat-empty__content">
                  <strong>console ready</strong>
                  <p>Send the first operational command to start the session.</p>
                  <div className="chat-empty__hints">
                    <span>estado actual de vive la vibe</span>
                    <span>reparto de trabajo por areas</span>
                    <span>prioridades activas del negocio</span>
                  </div>
                </div>
              </div>
            ) : (
              <div className="chat-messages">
                <div className="chat-console-meta">
                  <span>{selectedTarget}</span>
                  <span>console://admin</span>
                  <span>{sending ? "ai_state=thinking" : "ai_state=idle"}</span>
                </div>
                {displayMessages.map((message) => (
                  <article
                    className={
                      message.role === "user"
                        ? "chat-message chat-message--user"
                        : message.pending
                          ? "chat-message chat-message--assistant chat-message--thinking"
                          : "chat-message chat-message--assistant"
                    }
                    key={message.id}
                  >
                    <header className="chat-message__meta">
                      <strong>{message.role === "user" ? "operator" : "assistant-core"}</strong>
                      <small title={message.createdAt}>{formatMessageTimestamp(message.createdAt)}</small>
                    </header>
                    <p>{message.content}</p>
                    {message.pending ? (
                      <div className="thinking-signal" aria-hidden="true">
                        <span />
                        <span />
                        <span />
                      </div>
                    ) : null}
                  </article>
                ))}
                <div ref={chatBottomRef} />
              </div>
            )}

            <label className="chat-composer">
              <span>Command Payload</span>
              <textarea
                className="input input--multiline"
                rows={5}
                placeholder="describe la consulta o instruccion operativa..."
                value={draft.message}
                onKeyDown={handleComposerKeyDown}
                onChange={(event) =>
                  setDraft((current) => ({ ...current, message: event.target.value }))
                }
              />
            </label>

            <div className="button-row">
              <button className="button" disabled={sending || !configured} type="submit">
                Queue command
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
                      target: selectedTarget,
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

function buildThinkingCopy(frame: number): string {
  const phases = [
    "signal accepted // routing through vive_la_vibe_brain",
    "signal accepted // reading context graph",
    "signal accepted // correlating procedures and records",
    "signal accepted // generating response packet"
  ];

  return phases[frame] ?? phases[0];
}

function displayMessageCount(
  conversation: ConversationRecord | null,
  pendingExchange: PendingExchange | null
): number {
  return (conversation?.messages.length ?? 0) + (pendingExchange ? 2 : 0);
}

function createDraft(
  config: SanitizedRuntimeConfig | null,
  target: AssistantTarget,
  conversationId = `${target}_${Date.now()}`
): ChatDraft {
  const domain = config?.domains[target];

  return {
    conversationId,
    userId: "admin-console",
    actorUserId: domain?.assistant.defaultActorUserId ?? (target === "business_brain" ? 0 : 1),
    propertyCode: domain?.assistant.defaultPropertyCode ?? "",
    locale: domain?.assistant.defaultLocale ?? "es-MX",
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

function formatMessageTimestamp(value: string): string {
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("es-MX", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false
  }).format(parsed);
}

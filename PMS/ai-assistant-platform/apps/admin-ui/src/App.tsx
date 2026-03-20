import {
  ActionCatalogEntry,
  ApprovalRecord,
  ConnectionTestResult,
  DocumentDescriptor,
  LogEvent,
  RuntimeConfigInput,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";
import { startTransition, useEffect, useState } from "react";

import { api } from "./api/client";
import { AppShell } from "./components/AppShell";
import { ApprovalsPanel } from "./features/approvals/ApprovalsPanel";
import { ChatPanel } from "./features/chat/ChatPanel";
import { DashboardPanel } from "./features/dashboard/DashboardPanel";
import { DocsPanel } from "./features/docs/DocsPanel";
import { LogsPanel } from "./features/logs/LogsPanel";
import { SetupPanel } from "./features/setup/SetupPanel";

const defaultConfig: RuntimeConfigInput = {
  tenantId: "default",
  docsDirectory: "",
  assistant: {
    companyCode: "VIBE",
    defaultLocale: "es-MX",
    defaultPropertyCode: "",
    defaultActorUserId: 1,
    whatsappActorUserId: 1,
    whatsappRolesCsv: "admin",
    whatsappPermissionsCsv: ""
  },
  database: {
    host: "127.0.0.1",
    port: 3306,
    user: "",
    password: "",
    database: "",
    connectionLimit: 10,
    ssl: false
  },
  openai: {
    apiKey: "",
    model: "gpt-5",
    baseUrl: "",
    timeoutMs: 30000
  },
  whatsapp: {
    provider: "meta-cloud",
    baseUrl: "https://graph.facebook.com/v22.0/",
    phoneNumberId: "",
    businessAccountId: "",
    apiToken: "",
    appSecret: "",
    webhookVerifyToken: ""
  },
  execution: {
    mode: "hybrid",
    enableWrites: true
  }
};

type TestResultMap = Partial<Record<"database" | "openai" | "whatsapp", ConnectionTestResult>>;

export default function App() {
  const [activeView, setActiveView] = useState("chat");
  const [configured, setConfigured] = useState(false);
  const [config, setConfig] = useState<SanitizedRuntimeConfig | null>(null);
  const [form, setForm] = useState<RuntimeConfigInput>(defaultConfig);
  const [documents, setDocuments] = useState<DocumentDescriptor[]>([]);
  const [approvals, setApprovals] = useState<ApprovalRecord[]>([]);
  const [logs, setLogs] = useState<LogEvent[]>([]);
  const [actions, setActions] = useState<ActionCatalogEntry[]>([]);
  const [latestTests, setLatestTests] = useState<TestResultMap>({});
  const [approverId, setApproverId] = useState("admin-ui");
  const [feedback, setFeedback] = useState("");

  useEffect(() => {
    void refresh();
  }, []);

  async function refresh(): Promise<void> {
    const [health, configResponse, docsResponse, approvalsResponse, logsResponse, actionsResponse] =
      await Promise.all([
        api.getHealth(),
        api.getConfig(),
        api.getDocs(),
        api.getApprovals(),
        api.getLogs(50),
        api.getActions()
      ]);

    startTransition(() => {
      setConfigured(health.configured);
      setConfig(configResponse.config);
      setForm(buildFormFromConfig(configResponse.config));
      setDocuments(docsResponse.documents);
      setApprovals(approvalsResponse.approvals);
      setLogs(logsResponse.events);
      setActions(actionsResponse.actions);
    });
  }

  async function handleSave(): Promise<void> {
    const payload = buildPayload(form, config);
    const response = await api.saveConfig(payload);
    setConfig(response.config);
    setFeedback("Configuration saved.");
    await refresh();
  }

  async function handleTest(target: "database" | "openai" | "whatsapp"): Promise<void> {
    const payload = buildPayload(form, config);
    const response = await api.testConnection({
      target,
      candidateConfig: payload
    });
    setLatestTests((current) => ({
      ...current,
      [target]: response.result
    }));
    setFeedback(`${target} test completed.`);
  }

  async function handleApprove(approvalId: string): Promise<void> {
    await api.approve(approvalId, approverId);
    setFeedback(`Approved ${approvalId}.`);
    await refresh();
  }

  async function handleReject(approvalId: string): Promise<void> {
    await api.reject(approvalId, approverId);
    setFeedback(`Rejected ${approvalId}.`);
    await refresh();
  }

  return (
    <AppShell activeView={activeView} onViewChange={setActiveView}>
      <header className="page-header">
        <div>
          <p className="page-header__eyebrow">Standalone Control Plane</p>
          <h2>AI-assisted PMS backend orchestration</h2>
        </div>
        <div className="button-row">
          {feedback ? <span className="feedback">{feedback}</span> : null}
          <button className="button button--secondary" onClick={() => void refresh()} type="button">
            Refresh
          </button>
        </div>
      </header>

      {activeView === "dashboard" ? (
        <DashboardPanel
          configured={configured}
          config={config}
          documents={documents}
          approvals={approvals}
          logs={logs}
          actions={actions}
        />
      ) : null}

      {activeView === "chat" ? <ChatPanel configured={configured} config={config} /> : null}

      {activeView === "setup" ? (
        <SetupPanel
          form={form}
          savedConfig={config}
          onChange={setForm}
          onSave={handleSave}
          onTest={handleTest}
          latestTests={latestTests}
        />
      ) : null}

      {activeView === "docs" ? <DocsPanel documents={documents} /> : null}
      {activeView === "approvals" ? (
        <ApprovalsPanel
          approvals={approvals}
          approverId={approverId}
          onApproverIdChange={setApproverId}
          onApprove={handleApprove}
          onReject={handleReject}
        />
      ) : null}
      {activeView === "logs" ? <LogsPanel logs={logs} /> : null}
    </AppShell>
  );
}

function buildFormFromConfig(config: SanitizedRuntimeConfig | null): RuntimeConfigInput {
  if (!config) {
    return defaultConfig;
  }

  return {
    tenantId: config.tenantId,
    docsDirectory: config.docsDirectory,
    assistant: {
      companyCode: config.assistant.companyCode,
      defaultLocale: config.assistant.defaultLocale,
      defaultPropertyCode: config.assistant.defaultPropertyCode ?? "",
      defaultActorUserId: config.assistant.defaultActorUserId,
      whatsappActorUserId: config.assistant.whatsappActorUserId,
      whatsappRolesCsv: config.assistant.whatsappRolesCsv,
      whatsappPermissionsCsv: config.assistant.whatsappPermissionsCsv
    },
    database: {
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      password: "",
      database: config.database.database,
      connectionLimit: config.database.connectionLimit,
      ssl: config.database.ssl
    },
    openai: {
      apiKey: "",
      model: config.openai.model,
      baseUrl: config.openai.baseUrl ?? "",
      timeoutMs: config.openai.timeoutMs
    },
    whatsapp: {
      provider: "meta-cloud",
      baseUrl: config.whatsapp.baseUrl,
      phoneNumberId: config.whatsapp.phoneNumberId,
      businessAccountId: config.whatsapp.businessAccountId ?? "",
      apiToken: "",
      appSecret: "",
      webhookVerifyToken: ""
    },
    execution: config.execution
  };
}

function buildPayload(
  form: RuntimeConfigInput,
  savedConfig: SanitizedRuntimeConfig | null
): RuntimeConfigInput {
  return {
    ...form,
    docsDirectory: form.docsDirectory || undefined,
    assistant: {
      ...form.assistant,
      defaultLocale: form.assistant.defaultLocale || undefined,
      defaultPropertyCode: form.assistant.defaultPropertyCode || undefined,
      whatsappRolesCsv: form.assistant.whatsappRolesCsv || undefined,
      whatsappPermissionsCsv: form.assistant.whatsappPermissionsCsv || undefined
    },
    database: {
      ...form.database,
      password: preserveSecret(form.database.password, savedConfig?.database.hasPassword)
    },
    openai: {
      ...form.openai,
      baseUrl: form.openai.baseUrl || undefined,
      apiKey: preserveSecret(form.openai.apiKey, savedConfig?.openai.hasApiKey)
    },
    whatsapp: {
      ...form.whatsapp,
      businessAccountId: form.whatsapp.businessAccountId || undefined,
      apiToken: preserveSecret(form.whatsapp.apiToken, savedConfig?.whatsapp.hasApiToken),
      appSecret: preserveSecret(form.whatsapp.appSecret, savedConfig?.whatsapp.hasAppSecret),
      webhookVerifyToken: preserveSecret(
        form.whatsapp.webhookVerifyToken,
        savedConfig?.whatsapp.hasWebhookVerifyToken
      )
    }
  };
}

function preserveSecret(value: string | undefined, hasStoredSecret?: boolean): string | undefined {
  if (value) {
    return value;
  }

  if (hasStoredSecret) {
    return undefined;
  }

  return "";
}

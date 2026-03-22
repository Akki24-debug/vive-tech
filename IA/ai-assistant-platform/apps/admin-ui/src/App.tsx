import {
  ActionCatalogEntry,
  ApprovalRecord,
  AssistantTarget,
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
  defaultTarget: "business_brain",
  domains: {
    business_brain: {
      enabled: true,
      docsDirectory: "",
      assistant: {
        companyCode: "VLV-BRAIN",
        defaultLocale: "es-MX",
        defaultPropertyCode: "",
        defaultActorUserId: 0,
        whatsappActorUserId: 0,
        whatsappRolesCsv: "admin",
        whatsappPermissionsCsv: ""
      },
      database: {
        host: "127.0.0.1",
        port: 3307,
        user: "root",
        password: "",
        database: "vive_la_vibe_brain",
        connectionLimit: 10,
        ssl: false
      }
    },
    pms: {
      enabled: false,
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
      }
    }
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

type TestResultMap = Partial<
  Record<"database" | "openai" | "whatsapp", ConnectionTestResult>
>;

export default function App() {
  const [activeView, setActiveView] = useState("chat");
  const [selectedTarget, setSelectedTarget] = useState<AssistantTarget>("business_brain");
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

  useEffect(() => {
    void refreshTargetData(selectedTarget);
  }, [selectedTarget]);

  async function refresh(): Promise<void> {
    const [health, configResponse] = await Promise.all([api.getHealth(), api.getConfig()]);

    startTransition(() => {
      setConfigured(health.configured);
      setSelectedTarget(configResponse.config?.defaultTarget ?? health.defaultTarget ?? "business_brain");
      setConfig(configResponse.config);
      setForm(buildFormFromConfig(configResponse.config));
    });

    await refreshTargetData(configResponse.config?.defaultTarget ?? health.defaultTarget ?? "business_brain");
  }

  async function refreshTargetData(target: AssistantTarget): Promise<void> {
    const [docsResponse, approvalsResponse, logsResponse, actionsResponse] = await Promise.all([
      api.getDocs(target),
      api.getApprovals(target),
      api.getLogs(50, target),
      api.getActions(target)
    ]);

    startTransition(() => {
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
    setSelectedTarget(response.config.defaultTarget);
    await refresh();
  }

  async function handleTest(
    target: "database" | "openai" | "whatsapp",
    domainTarget?: AssistantTarget
  ): Promise<void> {
    const payload = buildPayload(form, config);
    const response = await api.testConnection({
      target,
      domainTarget,
      candidateConfig: payload
    });
    setLatestTests((current) => ({
      ...current,
      [target]: response.result
    }));
    setFeedback(
      target === "database" && domainTarget
        ? `${target} test completed for ${domainTarget}.`
        : `${target} test completed.`
    );
  }

  async function handleApprove(approvalId: string): Promise<void> {
    await api.approve(approvalId, approverId);
    setFeedback(`Approved ${approvalId}.`);
    await refreshTargetData(selectedTarget);
  }

  async function handleReject(approvalId: string): Promise<void> {
    await api.reject(approvalId, approverId);
    setFeedback(`Rejected ${approvalId}.`);
    await refreshTargetData(selectedTarget);
  }

  return (
    <AppShell activeView={activeView} onViewChange={setActiveView}>
      <header className="page-header">
        <div>
          <p className="page-header__eyebrow">Standalone Control Plane</p>
          <h2>AI backend orchestration for PMS and Business Brain</h2>
        </div>
        <div className="button-row">
          <select
            className="input"
            value={selectedTarget}
            onChange={(event) => setSelectedTarget(event.target.value as AssistantTarget)}
          >
            <option value="business_brain">business_brain</option>
            <option value="pms">pms</option>
          </select>
          {feedback ? <span className="feedback">{feedback}</span> : null}
          <button className="button button--secondary" onClick={() => void refresh()} type="button">
            Refresh
          </button>
        </div>
      </header>

      {activeView === "dashboard" ? (
        <DashboardPanel
          configured={configured}
          selectedTarget={selectedTarget}
          config={config}
          documents={documents}
          approvals={approvals}
          logs={logs}
          actions={actions}
        />
      ) : null}

      {activeView === "chat" ? (
        <ChatPanel configured={configured} config={config} selectedTarget={selectedTarget} />
      ) : null}

      {activeView === "setup" ? (
        <SetupPanel
          form={form}
          selectedTarget={selectedTarget}
          savedConfig={config}
          onChange={setForm}
          onSave={handleSave}
          onTest={handleTest}
          latestTests={latestTests}
        />
      ) : null}

      {activeView === "docs" ? <DocsPanel documents={documents} selectedTarget={selectedTarget} /> : null}
      {activeView === "approvals" ? (
        <ApprovalsPanel
          approvals={approvals}
          selectedTarget={selectedTarget}
          approverId={approverId}
          onApproverIdChange={setApproverId}
          onApprove={handleApprove}
          onReject={handleReject}
        />
      ) : null}
      {activeView === "logs" ? <LogsPanel logs={logs} selectedTarget={selectedTarget} /> : null}
    </AppShell>
  );
}

function buildFormFromConfig(config: SanitizedRuntimeConfig | null): RuntimeConfigInput {
  if (!config) {
    return defaultConfig;
  }

  return {
    tenantId: config.tenantId,
    defaultTarget: config.defaultTarget,
    domains: {
      business_brain: {
        enabled: config.domains.business_brain.enabled,
        docsDirectory: config.domains.business_brain.docsDirectory,
        assistant: {
          companyCode: config.domains.business_brain.assistant.companyCode,
          defaultLocale: config.domains.business_brain.assistant.defaultLocale,
          defaultPropertyCode:
            config.domains.business_brain.assistant.defaultPropertyCode ?? "",
          defaultActorUserId: config.domains.business_brain.assistant.defaultActorUserId,
          whatsappActorUserId: config.domains.business_brain.assistant.whatsappActorUserId,
          whatsappRolesCsv: config.domains.business_brain.assistant.whatsappRolesCsv,
          whatsappPermissionsCsv: config.domains.business_brain.assistant.whatsappPermissionsCsv
        },
        database: {
          host: config.domains.business_brain.database.host,
          port: config.domains.business_brain.database.port,
          user: config.domains.business_brain.database.user,
          password: "",
          database: config.domains.business_brain.database.database,
          connectionLimit: config.domains.business_brain.database.connectionLimit,
          ssl: config.domains.business_brain.database.ssl
        }
      },
      pms: {
        enabled: config.domains.pms.enabled,
        docsDirectory: config.domains.pms.docsDirectory,
        assistant: {
          companyCode: config.domains.pms.assistant.companyCode,
          defaultLocale: config.domains.pms.assistant.defaultLocale,
          defaultPropertyCode: config.domains.pms.assistant.defaultPropertyCode ?? "",
          defaultActorUserId: config.domains.pms.assistant.defaultActorUserId,
          whatsappActorUserId: config.domains.pms.assistant.whatsappActorUserId,
          whatsappRolesCsv: config.domains.pms.assistant.whatsappRolesCsv,
          whatsappPermissionsCsv: config.domains.pms.assistant.whatsappPermissionsCsv
        },
        database: {
          host: config.domains.pms.database.host,
          port: config.domains.pms.database.port,
          user: config.domains.pms.database.user,
          password: "",
          database: config.domains.pms.database.database,
          connectionLimit: config.domains.pms.database.connectionLimit,
          ssl: config.domains.pms.database.ssl
        }
      }
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
    defaultTarget: form.defaultTarget ?? "business_brain",
    domains: {
      business_brain: {
        ...form.domains.business_brain,
        docsDirectory: form.domains.business_brain.docsDirectory || undefined,
        assistant: {
          ...form.domains.business_brain.assistant,
          defaultLocale: form.domains.business_brain.assistant.defaultLocale || undefined,
          defaultPropertyCode:
            form.domains.business_brain.assistant.defaultPropertyCode || undefined,
          whatsappRolesCsv:
            form.domains.business_brain.assistant.whatsappRolesCsv || undefined,
          whatsappPermissionsCsv:
            form.domains.business_brain.assistant.whatsappPermissionsCsv || undefined
        },
        database: {
          ...form.domains.business_brain.database,
          password: preserveSecret(
            form.domains.business_brain.database.password,
            savedConfig?.domains.business_brain.database.hasPassword
          )
        }
      },
      pms: {
        ...form.domains.pms,
        docsDirectory: form.domains.pms.docsDirectory || undefined,
        assistant: {
          ...form.domains.pms.assistant,
          defaultLocale: form.domains.pms.assistant.defaultLocale || undefined,
          defaultPropertyCode: form.domains.pms.assistant.defaultPropertyCode || undefined,
          whatsappRolesCsv: form.domains.pms.assistant.whatsappRolesCsv || undefined,
          whatsappPermissionsCsv:
            form.domains.pms.assistant.whatsappPermissionsCsv || undefined
        },
        database: {
          ...form.domains.pms.database,
          password: preserveSecret(
            form.domains.pms.database.password,
            savedConfig?.domains.pms.database.hasPassword
          )
        }
      }
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

  return hasStoredSecret ? undefined : undefined;
}

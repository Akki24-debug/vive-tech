import { ComponentProps, FormEvent } from "react";

import { RuntimeConfigInput, SanitizedRuntimeConfig } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { ConnectionTestsPanel } from "../testing/ConnectionTestsPanel";

interface SetupPanelProps {
  form: RuntimeConfigInput;
  savedConfig: SanitizedRuntimeConfig | null;
  onChange: (next: RuntimeConfigInput) => void;
  onSave: () => Promise<void>;
  onTest: (target: "database" | "openai" | "whatsapp") => Promise<void>;
  latestTests: ComponentProps<typeof ConnectionTestsPanel>["results"];
}

export function SetupPanel({
  form,
  savedConfig,
  onChange,
  onSave,
  onTest,
  latestTests
}: SetupPanelProps) {
  const update = <
    TSection extends "assistant" | "database" | "openai" | "whatsapp" | "execution"
  >(
    section: TSection,
    patch: Partial<RuntimeConfigInput[TSection]>
  ) => {
    onChange({
      ...form,
      [section]: {
        ...form[section],
        ...patch
      }
    });
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    await onSave();
  };

  return (
    <div className="panel-grid">
      <SectionCard
        title="Runtime Configuration"
        subtitle="Credentials are entered here on first launch and stored by the backend"
        actions={
          <button className="button" onClick={onSave} type="button">
            Save Configuration
          </button>
        }
      >
        <form className="config-form" onSubmit={handleSubmit}>
          <div className="form-grid">
            <label>
              <span>Tenant ID</span>
              <input
                className="input"
                value={form.tenantId}
                onChange={(event) => onChange({ ...form, tenantId: event.target.value })}
              />
            </label>
            <label>
              <span>Docs Directory</span>
              <input
                className="input"
                value={form.docsDirectory ?? ""}
                onChange={(event) => onChange({ ...form, docsDirectory: event.target.value })}
              />
            </label>
            <label>
              <span>Execution Mode</span>
              <select
                className="input"
                value={form.execution.mode}
                onChange={(event) =>
                  update("execution", {
                    mode: event.target.value as RuntimeConfigInput["execution"]["mode"]
                  })
                }
              >
                <option value="auto">auto</option>
                <option value="manual">manual</option>
                <option value="hybrid">hybrid</option>
              </select>
            </label>
            <label className="toggle">
              <input
                checked={form.execution.enableWrites}
                onChange={(event) => update("execution", { enableWrites: event.target.checked })}
                type="checkbox"
              />
              <span>Enable write actions</span>
            </label>
          </div>

          <h3>Assistant Runtime</h3>
          <div className="form-grid">
            <label>
              <span>Company Code</span>
              <input
                className="input"
                value={form.assistant.companyCode}
                onChange={(event) => update("assistant", { companyCode: event.target.value })}
              />
            </label>
            <label>
              <span>Default Locale</span>
              <input
                className="input"
                value={form.assistant.defaultLocale ?? ""}
                onChange={(event) => update("assistant", { defaultLocale: event.target.value })}
              />
            </label>
            <label>
              <span>Default Property Code</span>
              <input
                className="input"
                value={form.assistant.defaultPropertyCode ?? ""}
                onChange={(event) => update("assistant", { defaultPropertyCode: event.target.value })}
              />
            </label>
            <label>
              <span>Default PMS Actor User ID</span>
              <input
                className="input"
                type="number"
                value={form.assistant.defaultActorUserId}
                onChange={(event) =>
                  update("assistant", { defaultActorUserId: Number(event.target.value) })
                }
              />
            </label>
            <label>
              <span>WhatsApp Actor User ID</span>
              <input
                className="input"
                type="number"
                value={form.assistant.whatsappActorUserId ?? form.assistant.defaultActorUserId}
                onChange={(event) =>
                  update("assistant", { whatsappActorUserId: Number(event.target.value) })
                }
              />
            </label>
            <label>
              <span>WhatsApp Roles CSV</span>
              <input
                className="input"
                value={form.assistant.whatsappRolesCsv ?? ""}
                onChange={(event) => update("assistant", { whatsappRolesCsv: event.target.value })}
              />
            </label>
            <label>
              <span>WhatsApp Permissions CSV</span>
              <input
                className="input"
                value={form.assistant.whatsappPermissionsCsv ?? ""}
                onChange={(event) =>
                  update("assistant", { whatsappPermissionsCsv: event.target.value })
                }
              />
            </label>
          </div>

          <h3>Database</h3>
          <div className="form-grid">
            <label>
              <span>Host</span>
              <input className="input" value={form.database.host} onChange={(event) => update("database", { host: event.target.value })} />
            </label>
            <label>
              <span>Port</span>
              <input
                className="input"
                type="number"
                value={form.database.port}
                onChange={(event) => update("database", { port: Number(event.target.value) })}
              />
            </label>
            <label>
              <span>User</span>
              <input className="input" value={form.database.user} onChange={(event) => update("database", { user: event.target.value })} />
            </label>
            <label>
              <span>Password</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.database.hasPassword ? "Stored secret preserved if left blank" : ""}
                value={form.database.password ?? ""}
                onChange={(event) => update("database", { password: event.target.value })}
              />
            </label>
            <label>
              <span>Database</span>
              <input className="input" value={form.database.database} onChange={(event) => update("database", { database: event.target.value })} />
            </label>
          </div>
          <div className="button-row">
            <button className="button button--secondary" onClick={() => onTest("database")} type="button">
              Test Database
            </button>
          </div>

          <h3>OpenAI</h3>
          <div className="form-grid">
            <label>
              <span>API Key</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.openai.hasApiKey ? "Stored secret preserved if left blank" : ""}
                value={form.openai.apiKey ?? ""}
                onChange={(event) => update("openai", { apiKey: event.target.value })}
              />
            </label>
            <label>
              <span>Model</span>
              <input className="input" value={form.openai.model} onChange={(event) => update("openai", { model: event.target.value })} />
            </label>
            <label>
              <span>Base URL</span>
              <input className="input" value={form.openai.baseUrl ?? ""} onChange={(event) => update("openai", { baseUrl: event.target.value })} />
            </label>
          </div>
          <div className="button-row">
            <button className="button button--secondary" onClick={() => onTest("openai")} type="button">
              Test OpenAI
            </button>
          </div>

          <h3>WhatsApp</h3>
          <div className="form-grid">
            <label>
              <span>Base URL</span>
              <input className="input" value={form.whatsapp.baseUrl} onChange={(event) => update("whatsapp", { baseUrl: event.target.value })} />
            </label>
            <label>
              <span>Phone Number ID</span>
              <input className="input" value={form.whatsapp.phoneNumberId} onChange={(event) => update("whatsapp", { phoneNumberId: event.target.value })} />
            </label>
            <label>
              <span>Business Account ID</span>
              <input className="input" value={form.whatsapp.businessAccountId ?? ""} onChange={(event) => update("whatsapp", { businessAccountId: event.target.value })} />
            </label>
            <label>
              <span>API Token</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasApiToken ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.apiToken ?? ""}
                onChange={(event) => update("whatsapp", { apiToken: event.target.value })}
              />
            </label>
            <label>
              <span>App Secret</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasAppSecret ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.appSecret ?? ""}
                onChange={(event) => update("whatsapp", { appSecret: event.target.value })}
              />
            </label>
            <label>
              <span>Webhook Verify Token</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasWebhookVerifyToken ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.webhookVerifyToken ?? ""}
                onChange={(event) => update("whatsapp", { webhookVerifyToken: event.target.value })}
              />
            </label>
          </div>
          <div className="button-row">
            <button className="button button--secondary" onClick={() => onTest("whatsapp")} type="button">
              Test WhatsApp
            </button>
            <button className="button" type="submit">
              Save All
            </button>
          </div>
        </form>
      </SectionCard>

      <ConnectionTestsPanel results={latestTests} />
    </div>
  );
}

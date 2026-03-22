import { ComponentProps, FormEvent } from "react";

import { AssistantTarget, RuntimeConfigInput, SanitizedRuntimeConfig } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { ConnectionTestsPanel } from "../testing/ConnectionTestsPanel";

interface SetupPanelProps {
  form: RuntimeConfigInput;
  selectedTarget: AssistantTarget;
  savedConfig: SanitizedRuntimeConfig | null;
  onChange: (next: RuntimeConfigInput) => void;
  onSave: () => Promise<void>;
  onTest: (
    target: "database" | "openai" | "whatsapp",
    domainTarget?: AssistantTarget
  ) => Promise<void>;
  latestTests: ComponentProps<typeof ConnectionTestsPanel>["results"];
}

export function SetupPanel({
  form,
  selectedTarget,
  savedConfig,
  onChange,
  onSave,
  onTest,
  latestTests
}: SetupPanelProps) {
  const updateDomain = (
    target: AssistantTarget,
    section: "assistant" | "database",
    patch: Record<string, unknown>
  ) => {
    onChange({
      ...form,
      domains: {
        ...form.domains,
        [target]: {
          ...form.domains[target],
          [section]: {
            ...form.domains[target][section],
            ...patch
          }
        }
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
        subtitle="Dual-domain runtime for PMS and Business Brain"
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
              <span>Default Target</span>
              <select
                className="input"
                value={form.defaultTarget ?? "business_brain"}
                onChange={(event) =>
                  onChange({
                    ...form,
                    defaultTarget: event.target.value as AssistantTarget
                  })
                }
              >
                <option value="business_brain">business_brain</option>
                <option value="pms">pms</option>
              </select>
            </label>
            <label>
              <span>Execution Mode</span>
              <select
                className="input"
                value={form.execution.mode}
                onChange={(event) =>
                  onChange({
                    ...form,
                    execution: {
                      ...form.execution,
                      mode: event.target.value as RuntimeConfigInput["execution"]["mode"]
                    }
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
                onChange={(event) =>
                  onChange({
                    ...form,
                    execution: {
                      ...form.execution,
                      enableWrites: event.target.checked
                    }
                  })
                }
                type="checkbox"
              />
              <span>Enable write actions</span>
            </label>
          </div>

          {(["business_brain", "pms"] as AssistantTarget[]).map((target) => {
            const domain = form.domains[target];
            const savedDomain = savedConfig?.domains[target];

            return (
              <div key={target}>
                <h3>{target}</h3>
                <div className="form-grid">
                  <label className="toggle">
                    <input
                      checked={domain.enabled}
                      onChange={(event) =>
                        onChange({
                          ...form,
                          domains: {
                            ...form.domains,
                            [target]: {
                              ...domain,
                              enabled: event.target.checked
                            }
                          }
                        })
                      }
                      type="checkbox"
                    />
                    <span>Enable {target}</span>
                  </label>
                  <label>
                    <span>Docs Directory</span>
                    <input
                      className="input"
                      value={domain.docsDirectory ?? ""}
                      onChange={(event) =>
                        onChange({
                          ...form,
                          domains: {
                            ...form.domains,
                            [target]: {
                              ...domain,
                              docsDirectory: event.target.value
                            }
                          }
                        })
                      }
                    />
                  </label>
                </div>

                <h4>Assistant Runtime</h4>
                <div className="form-grid">
                  <label>
                    <span>Company Code</span>
                    <input
                      className="input"
                      value={domain.assistant.companyCode}
                      onChange={(event) =>
                        updateDomain(target, "assistant", { companyCode: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Default Locale</span>
                    <input
                      className="input"
                      value={domain.assistant.defaultLocale ?? ""}
                      onChange={(event) =>
                        updateDomain(target, "assistant", { defaultLocale: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Default Property Code</span>
                    <input
                      className="input"
                      value={domain.assistant.defaultPropertyCode ?? ""}
                      onChange={(event) =>
                        updateDomain(target, "assistant", {
                          defaultPropertyCode: event.target.value
                        })
                      }
                    />
                  </label>
                  <label>
                    <span>Default Actor User ID</span>
                    <input
                      className="input"
                      type="number"
                      value={domain.assistant.defaultActorUserId}
                      onChange={(event) =>
                        updateDomain(target, "assistant", {
                          defaultActorUserId: Number(event.target.value)
                        })
                      }
                    />
                  </label>
                  <label>
                    <span>WhatsApp Actor User ID</span>
                    <input
                      className="input"
                      type="number"
                      value={
                        domain.assistant.whatsappActorUserId ??
                        domain.assistant.defaultActorUserId
                      }
                      onChange={(event) =>
                        updateDomain(target, "assistant", {
                          whatsappActorUserId: Number(event.target.value)
                        })
                      }
                    />
                  </label>
                  <label>
                    <span>WhatsApp Roles CSV</span>
                    <input
                      className="input"
                      value={domain.assistant.whatsappRolesCsv ?? ""}
                      onChange={(event) =>
                        updateDomain(target, "assistant", {
                          whatsappRolesCsv: event.target.value
                        })
                      }
                    />
                  </label>
                  <label>
                    <span>WhatsApp Permissions CSV</span>
                    <input
                      className="input"
                      value={domain.assistant.whatsappPermissionsCsv ?? ""}
                      onChange={(event) =>
                        updateDomain(target, "assistant", {
                          whatsappPermissionsCsv: event.target.value
                        })
                      }
                    />
                  </label>
                </div>

                <h4>Database</h4>
                <div className="form-grid">
                  <label>
                    <span>Host</span>
                    <input
                      className="input"
                      value={domain.database.host}
                      onChange={(event) =>
                        updateDomain(target, "database", { host: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Port</span>
                    <input
                      className="input"
                      type="number"
                      value={domain.database.port}
                      onChange={(event) =>
                        updateDomain(target, "database", { port: Number(event.target.value) })
                      }
                    />
                  </label>
                  <label>
                    <span>User</span>
                    <input
                      className="input"
                      value={domain.database.user}
                      onChange={(event) =>
                        updateDomain(target, "database", { user: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Password</span>
                    <input
                      className="input"
                      type="password"
                      placeholder={
                        savedDomain?.database.hasPassword
                          ? "Stored secret preserved if left blank"
                          : ""
                      }
                      value={domain.database.password ?? ""}
                      onChange={(event) =>
                        updateDomain(target, "database", { password: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Database</span>
                    <input
                      className="input"
                      value={domain.database.database}
                      onChange={(event) =>
                        updateDomain(target, "database", { database: event.target.value })
                      }
                    />
                  </label>
                  <label>
                    <span>Connection Limit</span>
                    <input
                      className="input"
                      type="number"
                      value={domain.database.connectionLimit ?? 10}
                      onChange={(event) =>
                        updateDomain(target, "database", {
                          connectionLimit: Number(event.target.value)
                        })
                      }
                    />
                  </label>
                  <label className="toggle">
                    <input
                      checked={domain.database.ssl ?? false}
                      onChange={(event) =>
                        updateDomain(target, "database", { ssl: event.target.checked })
                      }
                      type="checkbox"
                    />
                    <span>Use SSL</span>
                  </label>
                </div>
                <div className="button-row">
                  <button
                    className={`button button--secondary${selectedTarget === target ? "" : ""}`}
                    onClick={() => void onTest("database", target)}
                    type="button"
                  >
                    Test {target} Database
                  </button>
                </div>
              </div>
            );
          })}

          <h3>OpenAI</h3>
          <div className="form-grid">
            <label>
              <span>API Key</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.openai.hasApiKey ? "Stored secret preserved if left blank" : ""}
                value={form.openai.apiKey ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    openai: {
                      ...form.openai,
                      apiKey: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>Model</span>
              <input
                className="input"
                value={form.openai.model}
                onChange={(event) =>
                  onChange({
                    ...form,
                    openai: {
                      ...form.openai,
                      model: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>Base URL</span>
              <input
                className="input"
                value={form.openai.baseUrl ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    openai: {
                      ...form.openai,
                      baseUrl: event.target.value
                    }
                  })
                }
              />
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
              <input
                className="input"
                value={form.whatsapp.baseUrl}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      baseUrl: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>Phone Number ID</span>
              <input
                className="input"
                value={form.whatsapp.phoneNumberId}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      phoneNumberId: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>Business Account ID</span>
              <input
                className="input"
                value={form.whatsapp.businessAccountId ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      businessAccountId: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>API Token</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasApiToken ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.apiToken ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      apiToken: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>App Secret</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasAppSecret ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.appSecret ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      appSecret: event.target.value
                    }
                  })
                }
              />
            </label>
            <label>
              <span>Webhook Verify Token</span>
              <input
                className="input"
                type="password"
                placeholder={savedConfig?.whatsapp.hasWebhookVerifyToken ? "Stored secret preserved if left blank" : ""}
                value={form.whatsapp.webhookVerifyToken ?? ""}
                onChange={(event) =>
                  onChange({
                    ...form,
                    whatsapp: {
                      ...form.whatsapp,
                      webhookVerifyToken: event.target.value
                    }
                  })
                }
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

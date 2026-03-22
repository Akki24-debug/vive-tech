import path from "node:path";

import {
  AssistantTarget,
  DecryptedRuntimeConfig,
  RuntimeConfigInput,
  SanitizedRuntimeConfig,
  TargetAssistantRuntimeConfig,
  TargetDecryptedRuntimeConfig,
  TargetSanitizedRuntimeConfig
} from "@vlv-ai/shared";

import { paths } from "./paths";
import { runtimeConfigInputSchema } from "./config-schema";
import { decryptSecret, encryptSecret } from "./secret-crypto";
import { ConfigurationError } from "../shared/errors";
import { readJsonFile, writeJsonFile } from "../shared/json-file";
import { nowIso } from "../shared/time";

interface PersistedTargetConfig {
  enabled: boolean;
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
    passwordEncrypted?: string;
    database: string;
    connectionLimit: number;
    ssl: boolean;
  };
}

interface PersistedRuntimeConfig {
  tenantId: string;
  defaultTarget: AssistantTarget;
  domains: Record<AssistantTarget, PersistedTargetConfig>;
  openai: {
    apiKeyEncrypted?: string;
    model: string;
    baseUrl?: string;
    timeoutMs: number;
  };
  whatsapp: {
    provider: "meta-cloud";
    baseUrl: string;
    phoneNumberId: string;
    businessAccountId?: string;
    apiTokenEncrypted?: string;
    appSecretEncrypted?: string;
    webhookVerifyTokenEncrypted?: string;
  };
  execution: {
    mode: "auto" | "manual" | "hybrid";
    enableWrites: boolean;
  };
  updatedAt: string;
}

interface LegacyPersistedRuntimeConfig {
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
    passwordEncrypted?: string;
    database: string;
    connectionLimit: number;
    ssl: boolean;
  };
  openai: PersistedRuntimeConfig["openai"];
  whatsapp: PersistedRuntimeConfig["whatsapp"];
  execution: PersistedRuntimeConfig["execution"];
  updatedAt: string;
}

function defaultDocsDirectory(target: AssistantTarget): string {
  return path.join(paths.domainDocsDirectory, target);
}

function defaultTargetConfig(target: AssistantTarget): PersistedTargetConfig {
  return {
    enabled: target === "business_brain",
    docsDirectory: defaultDocsDirectory(target),
    assistant: {
      companyCode: target === "business_brain" ? "VLV-BRAIN" : "VIBE",
      defaultLocale: "es-MX",
      defaultPropertyCode: undefined,
      defaultActorUserId: target === "business_brain" ? 0 : 1,
      whatsappActorUserId: target === "business_brain" ? 0 : 1,
      whatsappRolesCsv: "admin",
      whatsappPermissionsCsv: ""
    },
    database: {
      host: "127.0.0.1",
      port: target === "business_brain" ? 3307 : 3306,
      user: target === "business_brain" ? "root" : "",
      passwordEncrypted: undefined,
      database: target === "business_brain" ? "vive_la_vibe_brain" : "",
      connectionLimit: 10,
      ssl: false
    }
  };
}

export class RuntimeConfigService {
  async getPersistedConfig(): Promise<PersistedRuntimeConfig | null> {
    const rawConfig = await readJsonFile<Record<string, unknown> | null>(paths.configFile, null);

    if (!rawConfig) {
      return null;
    }

    return this.normalizePersistedConfig(rawConfig);
  }

  async getSanitizedConfig(): Promise<SanitizedRuntimeConfig | null> {
    const config = await this.getPersistedConfig();

    if (!config) {
      return null;
    }

    return {
      tenantId: config.tenantId,
      defaultTarget: config.defaultTarget,
      domains: {
        business_brain: this.toSanitizedTargetConfig(config.domains.business_brain),
        pms: this.toSanitizedTargetConfig(config.domains.pms)
      },
      openai: {
        model: config.openai.model,
        baseUrl: config.openai.baseUrl,
        timeoutMs: config.openai.timeoutMs,
        hasApiKey: Boolean(config.openai.apiKeyEncrypted)
      },
      whatsapp: {
        provider: config.whatsapp.provider,
        baseUrl: config.whatsapp.baseUrl,
        phoneNumberId: config.whatsapp.phoneNumberId,
        businessAccountId: config.whatsapp.businessAccountId,
        hasApiToken: Boolean(config.whatsapp.apiTokenEncrypted),
        hasAppSecret: Boolean(config.whatsapp.appSecretEncrypted),
        hasWebhookVerifyToken: Boolean(config.whatsapp.webhookVerifyTokenEncrypted)
      },
      execution: config.execution,
      updatedAt: config.updatedAt
    };
  }

  async getDecryptedConfig(): Promise<DecryptedRuntimeConfig> {
    const config = await this.getPersistedConfig();

    if (!config) {
      throw new ConfigurationError(
        `Runtime configuration not found. Save settings through the admin UI first. Expected file: ${path.normalize(paths.configFile)}`
      );
    }

    return {
      tenantId: config.tenantId,
      defaultTarget: config.defaultTarget,
      domains: {
        business_brain: this.toDecryptedTargetConfig(config.domains.business_brain),
        pms: this.toDecryptedTargetConfig(config.domains.pms)
      },
      openai: {
        apiKey: decryptSecret(config.openai.apiKeyEncrypted),
        model: config.openai.model,
        baseUrl: config.openai.baseUrl,
        timeoutMs: config.openai.timeoutMs
      },
      whatsapp: {
        provider: config.whatsapp.provider,
        baseUrl: config.whatsapp.baseUrl,
        phoneNumberId: config.whatsapp.phoneNumberId,
        businessAccountId: config.whatsapp.businessAccountId,
        apiToken: decryptSecret(config.whatsapp.apiTokenEncrypted),
        appSecret: decryptSecret(config.whatsapp.appSecretEncrypted),
        webhookVerifyToken: decryptSecret(config.whatsapp.webhookVerifyTokenEncrypted)
      },
      execution: config.execution,
      updatedAt: config.updatedAt
    };
  }

  async getDecryptedTargetConfig(target: AssistantTarget): Promise<TargetDecryptedRuntimeConfig> {
    const config = await this.getDecryptedConfig();
    return config.domains[target];
  }

  async getSanitizedTargetConfig(target: AssistantTarget): Promise<TargetSanitizedRuntimeConfig> {
    const config = await this.getSanitizedConfig();

    if (!config) {
      throw new ConfigurationError("Runtime configuration not found.");
    }

    return config.domains[target];
  }

  async saveConfig(input: RuntimeConfigInput): Promise<SanitizedRuntimeConfig> {
    const parsed = runtimeConfigInputSchema.parse(input);
    const existing = await this.getPersistedConfig();

    const persisted: PersistedRuntimeConfig = {
      tenantId: parsed.tenantId,
      defaultTarget: parsed.defaultTarget ?? "business_brain",
      domains: {
        business_brain: this.buildPersistedTargetConfig(
          "business_brain",
          parsed.domains.business_brain,
          existing?.domains.business_brain
        ),
        pms: this.buildPersistedTargetConfig("pms", parsed.domains.pms, existing?.domains.pms)
      },
      openai: {
        apiKeyEncrypted:
          parsed.openai.apiKey !== undefined && parsed.openai.apiKey !== ""
            ? encryptSecret(parsed.openai.apiKey)
            : existing?.openai.apiKeyEncrypted,
        model: parsed.openai.model,
        baseUrl: parsed.openai.baseUrl,
        timeoutMs: parsed.openai.timeoutMs ?? 30_000
      },
      whatsapp: {
        provider: parsed.whatsapp.provider,
        baseUrl: parsed.whatsapp.baseUrl,
        phoneNumberId: parsed.whatsapp.phoneNumberId,
        businessAccountId: parsed.whatsapp.businessAccountId,
        apiTokenEncrypted:
          parsed.whatsapp.apiToken !== undefined && parsed.whatsapp.apiToken !== ""
            ? encryptSecret(parsed.whatsapp.apiToken)
            : existing?.whatsapp.apiTokenEncrypted,
        appSecretEncrypted:
          parsed.whatsapp.appSecret !== undefined && parsed.whatsapp.appSecret !== ""
            ? encryptSecret(parsed.whatsapp.appSecret)
            : existing?.whatsapp.appSecretEncrypted,
        webhookVerifyTokenEncrypted:
          parsed.whatsapp.webhookVerifyToken !== undefined &&
          parsed.whatsapp.webhookVerifyToken !== ""
            ? encryptSecret(parsed.whatsapp.webhookVerifyToken)
            : existing?.whatsapp.webhookVerifyTokenEncrypted
      },
      execution: parsed.execution,
      updatedAt: nowIso()
    };

    await writeJsonFile(paths.configFile, persisted);
    const sanitized = await this.getSanitizedConfig();

    if (!sanitized) {
      throw new ConfigurationError("Failed to read saved runtime configuration.");
    }

    return sanitized;
  }

  private normalizePersistedConfig(rawConfig: Record<string, unknown>): PersistedRuntimeConfig {
    if ("domains" in rawConfig) {
      const parsed = rawConfig as unknown as PersistedRuntimeConfig;
      return {
        tenantId: String(parsed.tenantId ?? "default"),
        defaultTarget: parsed.defaultTarget ?? "business_brain",
        domains: {
          business_brain: this.normalizePersistedTarget(
            "business_brain",
            parsed.domains?.business_brain
          ),
          pms: this.normalizePersistedTarget("pms", parsed.domains?.pms)
        },
        openai: {
          apiKeyEncrypted: parsed.openai?.apiKeyEncrypted,
          model: parsed.openai?.model ?? "gpt-5",
          baseUrl: parsed.openai?.baseUrl,
          timeoutMs: parsed.openai?.timeoutMs ?? 30_000
        },
        whatsapp: {
          provider: parsed.whatsapp?.provider ?? "meta-cloud",
          baseUrl: parsed.whatsapp?.baseUrl ?? "https://graph.facebook.com/v22.0/",
          phoneNumberId: parsed.whatsapp?.phoneNumberId ?? "",
          businessAccountId: parsed.whatsapp?.businessAccountId,
          apiTokenEncrypted: parsed.whatsapp?.apiTokenEncrypted,
          appSecretEncrypted: parsed.whatsapp?.appSecretEncrypted,
          webhookVerifyTokenEncrypted: parsed.whatsapp?.webhookVerifyTokenEncrypted
        },
        execution: {
          mode: parsed.execution?.mode ?? "hybrid",
          enableWrites: parsed.execution?.enableWrites ?? true
        },
        updatedAt: parsed.updatedAt ?? nowIso()
      };
    }

    return this.migrateLegacyConfig(rawConfig as unknown as LegacyPersistedRuntimeConfig);
  }

  private migrateLegacyConfig(legacy: LegacyPersistedRuntimeConfig): PersistedRuntimeConfig {
    const pmsTarget = this.normalizePersistedTarget("pms", {
      enabled: true,
      docsDirectory: legacy.docsDirectory,
      assistant: legacy.assistant,
      database: legacy.database
    });

    return {
      tenantId: legacy.tenantId ?? "default",
      defaultTarget: "business_brain",
      domains: {
        business_brain: this.normalizePersistedTarget("business_brain", undefined),
        pms: pmsTarget
      },
      openai: {
        apiKeyEncrypted: legacy.openai?.apiKeyEncrypted,
        model: legacy.openai?.model ?? "gpt-5",
        baseUrl: legacy.openai?.baseUrl,
        timeoutMs: legacy.openai?.timeoutMs ?? 30_000
      },
      whatsapp: {
        provider: legacy.whatsapp?.provider ?? "meta-cloud",
        baseUrl: legacy.whatsapp?.baseUrl ?? "https://graph.facebook.com/v22.0/",
        phoneNumberId: legacy.whatsapp?.phoneNumberId ?? "",
        businessAccountId: legacy.whatsapp?.businessAccountId,
        apiTokenEncrypted: legacy.whatsapp?.apiTokenEncrypted,
        appSecretEncrypted: legacy.whatsapp?.appSecretEncrypted,
        webhookVerifyTokenEncrypted: legacy.whatsapp?.webhookVerifyTokenEncrypted
      },
      execution: {
        mode: legacy.execution?.mode ?? "hybrid",
        enableWrites: legacy.execution?.enableWrites ?? true
      },
      updatedAt: legacy.updatedAt ?? nowIso()
    };
  }

  private normalizePersistedTarget(
    target: AssistantTarget,
    partial?: Partial<PersistedTargetConfig>
  ): PersistedTargetConfig {
    const base = defaultTargetConfig(target);
    const assistant = partial?.assistant;
    const database = partial?.database;

    return {
      enabled: partial?.enabled ?? base.enabled,
      docsDirectory: partial?.docsDirectory || base.docsDirectory,
      assistant: {
        companyCode: assistant?.companyCode || base.assistant.companyCode,
        defaultLocale: assistant?.defaultLocale || "es-MX",
        defaultPropertyCode: assistant?.defaultPropertyCode,
        defaultActorUserId: assistant?.defaultActorUserId ?? base.assistant.defaultActorUserId,
        whatsappActorUserId:
          assistant?.whatsappActorUserId ??
          assistant?.defaultActorUserId ??
          base.assistant.whatsappActorUserId,
        whatsappRolesCsv: assistant?.whatsappRolesCsv || "admin",
        whatsappPermissionsCsv: assistant?.whatsappPermissionsCsv || ""
      },
      database: {
        host: database?.host || base.database.host,
        port: database?.port ?? base.database.port,
        user: database?.user || base.database.user,
        passwordEncrypted: database?.passwordEncrypted,
        database: database?.database || base.database.database,
        connectionLimit: database?.connectionLimit ?? 10,
        ssl: database?.ssl ?? false
      }
    };
  }

  private buildPersistedTargetConfig(
    target: AssistantTarget,
    input: RuntimeConfigInput["domains"]["business_brain"],
    existing?: PersistedTargetConfig
  ): PersistedTargetConfig {
    const base = existing ?? defaultTargetConfig(target);

    return {
      enabled: input.enabled,
      docsDirectory: input.docsDirectory ?? defaultDocsDirectory(target),
      assistant: {
        companyCode: input.assistant.companyCode,
        defaultLocale: input.assistant.defaultLocale ?? "es-MX",
        defaultPropertyCode: input.assistant.defaultPropertyCode,
        defaultActorUserId: input.assistant.defaultActorUserId,
        whatsappActorUserId:
          input.assistant.whatsappActorUserId ?? input.assistant.defaultActorUserId,
        whatsappRolesCsv: input.assistant.whatsappRolesCsv?.trim() ?? "",
        whatsappPermissionsCsv: input.assistant.whatsappPermissionsCsv?.trim() ?? ""
      },
      database: {
        host: input.database.host,
        port: input.database.port,
        user: input.database.user,
        passwordEncrypted:
          input.database.password !== undefined && input.database.password !== ""
            ? encryptSecret(input.database.password)
            : base.database.passwordEncrypted,
        database: input.database.database,
        connectionLimit: input.database.connectionLimit ?? 10,
        ssl: input.database.ssl ?? false
      }
    };
  }

  private toSanitizedTargetConfig(target: PersistedTargetConfig): TargetSanitizedRuntimeConfig {
    return {
      enabled: target.enabled,
      docsDirectory: target.docsDirectory,
      assistant: this.normalizeAssistantConfig(target.assistant),
      database: {
        host: target.database.host,
        port: target.database.port,
        user: target.database.user,
        database: target.database.database,
        connectionLimit: target.database.connectionLimit,
        ssl: target.database.ssl,
        hasPassword: Boolean(target.database.passwordEncrypted)
      }
    };
  }

  private toDecryptedTargetConfig(target: PersistedTargetConfig): TargetDecryptedRuntimeConfig {
    return {
      enabled: target.enabled,
      docsDirectory: target.docsDirectory,
      assistant: this.normalizeAssistantConfig(target.assistant),
      database: {
        host: target.database.host,
        port: target.database.port,
        user: target.database.user,
        password: decryptSecret(target.database.passwordEncrypted),
        database: target.database.database,
        connectionLimit: target.database.connectionLimit,
        ssl: target.database.ssl
      }
    };
  }

  private normalizeAssistantConfig(
    assistant: PersistedTargetConfig["assistant"]
  ): TargetAssistantRuntimeConfig {
    return {
      companyCode: assistant.companyCode,
      defaultLocale: assistant.defaultLocale || "es-MX",
      defaultPropertyCode: assistant.defaultPropertyCode,
      defaultActorUserId: assistant.defaultActorUserId ?? 0,
      whatsappActorUserId: assistant.whatsappActorUserId ?? assistant.defaultActorUserId ?? 0,
      whatsappRolesCsv: assistant.whatsappRolesCsv || "admin",
      whatsappPermissionsCsv: assistant.whatsappPermissionsCsv || ""
    };
  }
}

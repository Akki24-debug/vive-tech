import path from "node:path";

import {
  DecryptedRuntimeConfig,
  RuntimeConfigInput,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";

import { paths } from "./paths";
import { runtimeConfigInputSchema } from "./config-schema";
import { decryptSecret, encryptSecret } from "./secret-crypto";
import { ConfigurationError } from "../shared/errors";
import { readJsonFile, writeJsonFile } from "../shared/json-file";
import { nowIso } from "../shared/time";

interface PersistedRuntimeConfig {
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

export class RuntimeConfigService {
  async getPersistedConfig(): Promise<PersistedRuntimeConfig | null> {
    const config = await readJsonFile<PersistedRuntimeConfig | null>(paths.configFile, null);
    return config;
  }

  async getSanitizedConfig(): Promise<SanitizedRuntimeConfig | null> {
    const config = await this.getPersistedConfig();

    if (!config) {
      return null;
    }

    const assistant = this.normalizeAssistantConfig(config);

    return {
      tenantId: config.tenantId,
      docsDirectory: config.docsDirectory,
      assistant,
      database: {
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        database: config.database.database,
        connectionLimit: config.database.connectionLimit,
        ssl: config.database.ssl,
        hasPassword: Boolean(config.database.passwordEncrypted)
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

    const assistant = this.normalizeAssistantConfig(config);

    return {
      tenantId: config.tenantId,
      docsDirectory: config.docsDirectory,
      assistant,
      database: {
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        password: decryptSecret(config.database.passwordEncrypted),
        database: config.database.database,
        connectionLimit: config.database.connectionLimit,
        ssl: config.database.ssl
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

  async saveConfig(input: RuntimeConfigInput): Promise<SanitizedRuntimeConfig> {
    const parsed = runtimeConfigInputSchema.parse(input);
    const existing = await this.getPersistedConfig();
    const persisted: PersistedRuntimeConfig = {
      tenantId: parsed.tenantId,
      docsDirectory: parsed.docsDirectory ?? paths.docsDirectory,
      assistant: {
        companyCode: parsed.assistant.companyCode,
        defaultLocale: parsed.assistant.defaultLocale ?? "es-MX",
        defaultPropertyCode: parsed.assistant.defaultPropertyCode,
        defaultActorUserId: parsed.assistant.defaultActorUserId,
        whatsappActorUserId:
          parsed.assistant.whatsappActorUserId ?? parsed.assistant.defaultActorUserId,
        whatsappRolesCsv: parsed.assistant.whatsappRolesCsv?.trim() ?? "",
        whatsappPermissionsCsv: parsed.assistant.whatsappPermissionsCsv?.trim() ?? ""
      },
      database: {
        host: parsed.database.host,
        port: parsed.database.port,
        user: parsed.database.user,
        passwordEncrypted:
          parsed.database.password !== undefined
            ? encryptSecret(parsed.database.password)
            : existing?.database.passwordEncrypted,
        database: parsed.database.database,
        connectionLimit: parsed.database.connectionLimit ?? 10,
        ssl: parsed.database.ssl ?? false
      },
      openai: {
        apiKeyEncrypted:
          parsed.openai.apiKey !== undefined
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
          parsed.whatsapp.apiToken !== undefined
            ? encryptSecret(parsed.whatsapp.apiToken)
            : existing?.whatsapp.apiTokenEncrypted,
        appSecretEncrypted:
          parsed.whatsapp.appSecret !== undefined
            ? encryptSecret(parsed.whatsapp.appSecret)
            : existing?.whatsapp.appSecretEncrypted,
        webhookVerifyTokenEncrypted:
          parsed.whatsapp.webhookVerifyToken !== undefined
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

  private normalizeAssistantConfig(
    config: PersistedRuntimeConfig
  ): PersistedRuntimeConfig["assistant"] {
    const assistant = config.assistant;

    if (assistant) {
      return {
        companyCode: assistant.companyCode,
        defaultLocale: assistant.defaultLocale || "es-MX",
        defaultPropertyCode: assistant.defaultPropertyCode,
        defaultActorUserId: assistant.defaultActorUserId || 1,
        whatsappActorUserId: assistant.whatsappActorUserId || assistant.defaultActorUserId || 1,
        whatsappRolesCsv: assistant.whatsappRolesCsv || "admin",
        whatsappPermissionsCsv: assistant.whatsappPermissionsCsv || ""
      };
    }

    return {
      companyCode: config.tenantId || "VIBE",
      defaultLocale: "es-MX",
      defaultPropertyCode: undefined,
      defaultActorUserId: 1,
      whatsappActorUserId: 1,
      whatsappRolesCsv: "admin",
      whatsappPermissionsCsv: ""
    };
  }
}

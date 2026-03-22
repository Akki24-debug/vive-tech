import mysql from "mysql2/promise";
import OpenAI from "openai";
import {
  AssistantTarget,
  ConnectionTestRequest,
  ConnectionTestResult,
  DecryptedRuntimeConfig,
  RuntimeConfigInput
} from "@vlv-ai/shared";

import { RuntimeConfigService } from "./runtime-config-service";
import { runtimeConfigInputSchema } from "./config-schema";
import { ConfigurationError } from "../shared/errors";
import { MetaCloudWhatsAppClient } from "../channels/whatsapp/meta-cloud-client";

const DEFAULT_DOMAIN_TARGET: AssistantTarget = "business_brain";

export class ConnectionTestService {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly metaCloudClient: MetaCloudWhatsAppClient
  ) {}

  async testConnection(request: ConnectionTestRequest): Promise<ConnectionTestResult> {
    const startedAt = Date.now();
    const config = await this.resolveConfig(request.candidateConfig);
    const domainTarget = request.domainTarget ?? config.defaultTarget ?? DEFAULT_DOMAIN_TARGET;

    switch (request.target) {
      case "database":
        await this.testDatabase(config, domainTarget);
        break;
      case "openai":
        await this.testOpenAI(config);
        break;
      case "whatsapp":
        await this.metaCloudClient.testConnection(config.whatsapp);
        break;
      default:
        throw new ConfigurationError("Unsupported connection test target.", {
          target: request.target
        });
    }

    return {
      target: request.target,
      domainTarget: request.target === "database" ? domainTarget : undefined,
      success: true,
      details:
        request.target === "database"
          ? `${request.target} connection test succeeded for ${domainTarget}.`
          : `${request.target} connection test succeeded.`,
      durationMs: Date.now() - startedAt
    };
  }

  private async resolveConfig(
    candidateConfig?: Partial<RuntimeConfigInput>
  ): Promise<DecryptedRuntimeConfig> {
    const existing = await this.runtimeConfigService.getDecryptedConfig().catch(() => null);

    if (!candidateConfig) {
      if (!existing) {
        throw new ConfigurationError("No saved runtime configuration exists yet.");
      }

      return existing;
    }

    const mergedInput = runtimeConfigInputSchema.parse({
      tenantId: candidateConfig.tenantId ?? existing?.tenantId ?? "default",
      defaultTarget: candidateConfig.defaultTarget ?? existing?.defaultTarget ?? DEFAULT_DOMAIN_TARGET,
      domains: {
        business_brain: {
          enabled:
            candidateConfig.domains?.business_brain?.enabled ??
            existing?.domains.business_brain.enabled ??
            true,
          docsDirectory:
            candidateConfig.domains?.business_brain?.docsDirectory ??
            existing?.domains.business_brain.docsDirectory,
          assistant: {
            companyCode:
              candidateConfig.domains?.business_brain?.assistant.companyCode ??
              existing?.domains.business_brain.assistant.companyCode ??
              "VLV-BRAIN",
            defaultLocale:
              candidateConfig.domains?.business_brain?.assistant.defaultLocale ??
              existing?.domains.business_brain.assistant.defaultLocale ??
              "es-MX",
            defaultPropertyCode:
              candidateConfig.domains?.business_brain?.assistant.defaultPropertyCode ??
              existing?.domains.business_brain.assistant.defaultPropertyCode,
            defaultActorUserId:
              candidateConfig.domains?.business_brain?.assistant.defaultActorUserId ??
              existing?.domains.business_brain.assistant.defaultActorUserId ??
              0,
            whatsappActorUserId:
              candidateConfig.domains?.business_brain?.assistant.whatsappActorUserId ??
              existing?.domains.business_brain.assistant.whatsappActorUserId ??
              0,
            whatsappRolesCsv:
              candidateConfig.domains?.business_brain?.assistant.whatsappRolesCsv ??
              existing?.domains.business_brain.assistant.whatsappRolesCsv ??
              "admin",
            whatsappPermissionsCsv:
              candidateConfig.domains?.business_brain?.assistant.whatsappPermissionsCsv ??
              existing?.domains.business_brain.assistant.whatsappPermissionsCsv ??
              ""
          },
          database: {
            host:
              candidateConfig.domains?.business_brain?.database.host ??
              existing?.domains.business_brain.database.host ??
              "127.0.0.1",
            port:
              candidateConfig.domains?.business_brain?.database.port ??
              existing?.domains.business_brain.database.port ??
              3307,
            user:
              candidateConfig.domains?.business_brain?.database.user ??
              existing?.domains.business_brain.database.user ??
              "root",
            password:
              candidateConfig.domains?.business_brain?.database.password ??
              existing?.domains.business_brain.database.password ??
              "",
            database:
              candidateConfig.domains?.business_brain?.database.database ??
              existing?.domains.business_brain.database.database ??
              "vive_la_vibe_brain",
            connectionLimit:
              candidateConfig.domains?.business_brain?.database.connectionLimit ??
              existing?.domains.business_brain.database.connectionLimit ??
              10,
            ssl:
              candidateConfig.domains?.business_brain?.database.ssl ??
              existing?.domains.business_brain.database.ssl ??
              false
          }
        },
        pms: {
          enabled:
            candidateConfig.domains?.pms?.enabled ?? existing?.domains.pms.enabled ?? false,
          docsDirectory:
            candidateConfig.domains?.pms?.docsDirectory ?? existing?.domains.pms.docsDirectory,
          assistant: {
            companyCode:
              candidateConfig.domains?.pms?.assistant.companyCode ??
              existing?.domains.pms.assistant.companyCode ??
              "VIBE",
            defaultLocale:
              candidateConfig.domains?.pms?.assistant.defaultLocale ??
              existing?.domains.pms.assistant.defaultLocale ??
              "es-MX",
            defaultPropertyCode:
              candidateConfig.domains?.pms?.assistant.defaultPropertyCode ??
              existing?.domains.pms.assistant.defaultPropertyCode,
            defaultActorUserId:
              candidateConfig.domains?.pms?.assistant.defaultActorUserId ??
              existing?.domains.pms.assistant.defaultActorUserId ??
              1,
            whatsappActorUserId:
              candidateConfig.domains?.pms?.assistant.whatsappActorUserId ??
              existing?.domains.pms.assistant.whatsappActorUserId ??
              1,
            whatsappRolesCsv:
              candidateConfig.domains?.pms?.assistant.whatsappRolesCsv ??
              existing?.domains.pms.assistant.whatsappRolesCsv ??
              "admin",
            whatsappPermissionsCsv:
              candidateConfig.domains?.pms?.assistant.whatsappPermissionsCsv ??
              existing?.domains.pms.assistant.whatsappPermissionsCsv ??
              ""
          },
          database: {
            host:
              candidateConfig.domains?.pms?.database.host ??
              existing?.domains.pms.database.host ??
              "127.0.0.1",
            port:
              candidateConfig.domains?.pms?.database.port ??
              existing?.domains.pms.database.port ??
              3306,
            user:
              candidateConfig.domains?.pms?.database.user ??
              existing?.domains.pms.database.user ??
              "",
            password:
              candidateConfig.domains?.pms?.database.password ??
              existing?.domains.pms.database.password ??
              "",
            database:
              candidateConfig.domains?.pms?.database.database ??
              existing?.domains.pms.database.database ??
              "",
            connectionLimit:
              candidateConfig.domains?.pms?.database.connectionLimit ??
              existing?.domains.pms.database.connectionLimit ??
              10,
            ssl:
              candidateConfig.domains?.pms?.database.ssl ??
              existing?.domains.pms.database.ssl ??
              false
          }
        }
      },
      openai: {
        apiKey: candidateConfig.openai?.apiKey ?? existing?.openai.apiKey ?? "",
        model: candidateConfig.openai?.model ?? existing?.openai.model ?? "gpt-5",
        baseUrl: candidateConfig.openai?.baseUrl ?? existing?.openai.baseUrl,
        timeoutMs: candidateConfig.openai?.timeoutMs ?? existing?.openai.timeoutMs ?? 120_000
      },
      whatsapp: {
        provider: candidateConfig.whatsapp?.provider ?? existing?.whatsapp.provider ?? "meta-cloud",
        baseUrl:
          candidateConfig.whatsapp?.baseUrl ??
          existing?.whatsapp.baseUrl ??
          "https://graph.facebook.com/v22.0/",
        phoneNumberId:
          candidateConfig.whatsapp?.phoneNumberId ?? existing?.whatsapp.phoneNumberId ?? "",
        businessAccountId:
          candidateConfig.whatsapp?.businessAccountId ?? existing?.whatsapp.businessAccountId,
        apiToken: candidateConfig.whatsapp?.apiToken ?? existing?.whatsapp.apiToken ?? "",
        appSecret: candidateConfig.whatsapp?.appSecret ?? existing?.whatsapp.appSecret,
        webhookVerifyToken:
          candidateConfig.whatsapp?.webhookVerifyToken ?? existing?.whatsapp.webhookVerifyToken
      },
      execution: {
        mode: candidateConfig.execution?.mode ?? existing?.execution.mode ?? "hybrid",
        enableWrites:
          candidateConfig.execution?.enableWrites ?? existing?.execution.enableWrites ?? true
      }
    });

    return {
      tenantId: mergedInput.tenantId,
      defaultTarget: mergedInput.defaultTarget ?? DEFAULT_DOMAIN_TARGET,
      domains: {
        business_brain: {
          enabled: mergedInput.domains.business_brain.enabled,
          docsDirectory: mergedInput.domains.business_brain.docsDirectory ?? "",
          assistant: {
            companyCode: mergedInput.domains.business_brain.assistant.companyCode,
            defaultLocale:
              mergedInput.domains.business_brain.assistant.defaultLocale ?? "es-MX",
            defaultPropertyCode:
              mergedInput.domains.business_brain.assistant.defaultPropertyCode,
            defaultActorUserId:
              mergedInput.domains.business_brain.assistant.defaultActorUserId,
            whatsappActorUserId:
              mergedInput.domains.business_brain.assistant.whatsappActorUserId ??
              mergedInput.domains.business_brain.assistant.defaultActorUserId,
            whatsappRolesCsv:
              mergedInput.domains.business_brain.assistant.whatsappRolesCsv ?? "admin",
            whatsappPermissionsCsv:
              mergedInput.domains.business_brain.assistant.whatsappPermissionsCsv ?? ""
          },
          database: {
            host: mergedInput.domains.business_brain.database.host,
            port: mergedInput.domains.business_brain.database.port,
            user: mergedInput.domains.business_brain.database.user,
            password: mergedInput.domains.business_brain.database.password ?? "",
            database: mergedInput.domains.business_brain.database.database,
            connectionLimit:
              mergedInput.domains.business_brain.database.connectionLimit ?? 10,
            ssl: mergedInput.domains.business_brain.database.ssl ?? false
          }
        },
        pms: {
          enabled: mergedInput.domains.pms.enabled,
          docsDirectory: mergedInput.domains.pms.docsDirectory ?? "",
          assistant: {
            companyCode: mergedInput.domains.pms.assistant.companyCode,
            defaultLocale: mergedInput.domains.pms.assistant.defaultLocale ?? "es-MX",
            defaultPropertyCode: mergedInput.domains.pms.assistant.defaultPropertyCode,
            defaultActorUserId: mergedInput.domains.pms.assistant.defaultActorUserId,
            whatsappActorUserId:
              mergedInput.domains.pms.assistant.whatsappActorUserId ??
              mergedInput.domains.pms.assistant.defaultActorUserId,
            whatsappRolesCsv: mergedInput.domains.pms.assistant.whatsappRolesCsv ?? "admin",
            whatsappPermissionsCsv:
              mergedInput.domains.pms.assistant.whatsappPermissionsCsv ?? ""
          },
          database: {
            host: mergedInput.domains.pms.database.host,
            port: mergedInput.domains.pms.database.port,
            user: mergedInput.domains.pms.database.user,
            password: mergedInput.domains.pms.database.password ?? "",
            database: mergedInput.domains.pms.database.database,
            connectionLimit: mergedInput.domains.pms.database.connectionLimit ?? 10,
            ssl: mergedInput.domains.pms.database.ssl ?? false
          }
        }
      },
      openai: {
        apiKey: mergedInput.openai.apiKey ?? "",
        model: mergedInput.openai.model,
        baseUrl: mergedInput.openai.baseUrl,
        timeoutMs: mergedInput.openai.timeoutMs ?? 120_000
      },
      whatsapp: {
        provider: mergedInput.whatsapp.provider,
        baseUrl: mergedInput.whatsapp.baseUrl,
        phoneNumberId: mergedInput.whatsapp.phoneNumberId,
        businessAccountId: mergedInput.whatsapp.businessAccountId,
        apiToken: mergedInput.whatsapp.apiToken ?? "",
        appSecret: mergedInput.whatsapp.appSecret,
        webhookVerifyToken: mergedInput.whatsapp.webhookVerifyToken
      },
      execution: {
        mode: mergedInput.execution.mode,
        enableWrites: mergedInput.execution.enableWrites
      },
      updatedAt: new Date().toISOString()
    };
  }

  private async testDatabase(
    config: DecryptedRuntimeConfig,
    domainTarget: AssistantTarget
  ): Promise<void> {
    const domain = config.domains[domainTarget];

    if (!domain.enabled) {
      throw new ConfigurationError(`Domain ${domainTarget} is disabled.`);
    }

    const connection = await mysql.createConnection({
      host: domain.database.host,
      port: domain.database.port,
      user: domain.database.user,
      password: domain.database.password,
      database: domain.database.database,
      ssl: domain.database.ssl ? {} : undefined
    });

    try {
      await connection.query("SELECT 1 AS ok");
    } finally {
      await connection.end();
    }
  }

  private async testOpenAI(config: DecryptedRuntimeConfig): Promise<void> {
    const client = new OpenAI({
      apiKey: config.openai.apiKey,
      baseURL: config.openai.baseUrl,
      timeout: config.openai.timeoutMs
    });

    await client.responses.create({
      model: config.openai.model,
      input: "Connection test. Reply with OK.",
      max_output_tokens: 16
    });
  }
}

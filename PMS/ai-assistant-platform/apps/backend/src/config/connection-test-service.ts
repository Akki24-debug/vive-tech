import mysql from "mysql2/promise";
import OpenAI from "openai";
import { ConnectionTestRequest, ConnectionTestResult, DecryptedRuntimeConfig, RuntimeConfigInput } from "@vlv-ai/shared";

import { RuntimeConfigService } from "./runtime-config-service";
import { runtimeConfigInputSchema } from "./config-schema";
import { ConfigurationError } from "../shared/errors";
import { MetaCloudWhatsAppClient } from "../channels/whatsapp/meta-cloud-client";

export class ConnectionTestService {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly metaCloudClient: MetaCloudWhatsAppClient
  ) {}

  async testConnection(request: ConnectionTestRequest): Promise<ConnectionTestResult> {
    const startedAt = Date.now();
    const config = await this.resolveConfig(request.candidateConfig);

    switch (request.target) {
      case "database":
        await this.testDatabase(config);
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
      success: true,
      details: `${request.target} connection test succeeded.`,
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
      tenantId: candidateConfig.tenantId ?? existing?.tenantId ?? "",
      docsDirectory: candidateConfig.docsDirectory ?? existing?.docsDirectory,
      assistant: {
        companyCode: candidateConfig.assistant?.companyCode ?? existing?.assistant.companyCode ?? "",
        defaultLocale:
          candidateConfig.assistant?.defaultLocale ?? existing?.assistant.defaultLocale ?? "es-MX",
        defaultPropertyCode:
          candidateConfig.assistant?.defaultPropertyCode ?? existing?.assistant.defaultPropertyCode,
        defaultActorUserId:
          candidateConfig.assistant?.defaultActorUserId ?? existing?.assistant.defaultActorUserId ?? 1,
        whatsappActorUserId:
          candidateConfig.assistant?.whatsappActorUserId ??
          existing?.assistant.whatsappActorUserId ??
          candidateConfig.assistant?.defaultActorUserId ??
          existing?.assistant.defaultActorUserId ??
          1,
        whatsappRolesCsv:
          candidateConfig.assistant?.whatsappRolesCsv ??
          existing?.assistant.whatsappRolesCsv ??
          "admin",
        whatsappPermissionsCsv:
          candidateConfig.assistant?.whatsappPermissionsCsv ??
          existing?.assistant.whatsappPermissionsCsv ??
          ""
      },
      database: {
        host: candidateConfig.database?.host ?? existing?.database.host ?? "",
        port: candidateConfig.database?.port ?? existing?.database.port ?? 3306,
        user: candidateConfig.database?.user ?? existing?.database.user ?? "",
        password: candidateConfig.database?.password ?? existing?.database.password ?? "",
        database: candidateConfig.database?.database ?? existing?.database.database ?? "",
        connectionLimit:
          candidateConfig.database?.connectionLimit ?? existing?.database.connectionLimit ?? 10,
        ssl: candidateConfig.database?.ssl ?? existing?.database.ssl ?? false
      },
      openai: {
        apiKey: candidateConfig.openai?.apiKey ?? existing?.openai.apiKey ?? "",
        model: candidateConfig.openai?.model ?? existing?.openai.model ?? "gpt-5",
        baseUrl: candidateConfig.openai?.baseUrl ?? existing?.openai.baseUrl,
        timeoutMs: candidateConfig.openai?.timeoutMs ?? existing?.openai.timeoutMs ?? 30_000
      },
      whatsapp: {
        provider: candidateConfig.whatsapp?.provider ?? existing?.whatsapp.provider ?? "meta-cloud",
        baseUrl: candidateConfig.whatsapp?.baseUrl ?? existing?.whatsapp.baseUrl ?? "https://graph.facebook.com/v22.0/",
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
        enableWrites: candidateConfig.execution?.enableWrites ?? existing?.execution.enableWrites ?? true
      }
    });

    return {
      tenantId: mergedInput.tenantId,
      docsDirectory: mergedInput.docsDirectory ?? "",
      assistant: {
        companyCode: mergedInput.assistant.companyCode,
        defaultLocale: mergedInput.assistant.defaultLocale ?? "es-MX",
        defaultPropertyCode: mergedInput.assistant.defaultPropertyCode,
        defaultActorUserId: mergedInput.assistant.defaultActorUserId,
        whatsappActorUserId:
          mergedInput.assistant.whatsappActorUserId ?? mergedInput.assistant.defaultActorUserId,
        whatsappRolesCsv: mergedInput.assistant.whatsappRolesCsv ?? "",
        whatsappPermissionsCsv: mergedInput.assistant.whatsappPermissionsCsv ?? ""
      },
      database: {
        host: mergedInput.database.host,
        port: mergedInput.database.port,
        user: mergedInput.database.user,
        password: mergedInput.database.password ?? "",
        database: mergedInput.database.database,
        connectionLimit: mergedInput.database.connectionLimit ?? 10,
        ssl: mergedInput.database.ssl ?? false
      },
      openai: {
        apiKey: mergedInput.openai.apiKey ?? "",
        model: mergedInput.openai.model,
        baseUrl: mergedInput.openai.baseUrl,
        timeoutMs: mergedInput.openai.timeoutMs ?? 30_000
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

  private async testDatabase(config: DecryptedRuntimeConfig): Promise<void> {
    const connection = await mysql.createConnection({
      host: config.database.host,
      port: config.database.port,
      user: config.database.user,
      password: config.database.password,
      database: config.database.database,
      ssl: config.database.ssl ? {} : undefined
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
      max_output_tokens: 8
    });
  }
}

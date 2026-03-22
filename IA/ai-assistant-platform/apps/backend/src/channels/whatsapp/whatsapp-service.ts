import { AssistantRequest } from "@vlv-ai/shared";

import { AssistantOrchestrator } from "../../ai/assistant-orchestrator";
import { RuntimeConfigService } from "../../config/runtime-config-service";
import { ActivityLogService } from "../../logging/activity-log-service";
import { MetaCloudWhatsAppClient } from "./meta-cloud-client";

interface MetaWebhookMessage {
  from: string;
  text: {
    body: string;
  };
}

export class WhatsAppService {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly metaCloudClient: MetaCloudWhatsAppClient,
    private readonly assistantOrchestrator: AssistantOrchestrator,
    private readonly activityLogService: ActivityLogService
  ) {}

  async testConnection(): Promise<string> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    return this.metaCloudClient.testConnection(config.whatsapp);
  }

  async sendTextMessage(to: string, body: string): Promise<void> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    await this.metaCloudClient.sendTextMessage(config.whatsapp, { to, body });
  }

  async verifyWebhook(mode?: string, token?: string, challenge?: string): Promise<string | null> {
    if (mode !== "subscribe") {
      return null;
    }

    const config = await this.runtimeConfigService.getDecryptedConfig();
    const verified = this.metaCloudClient.verifyWebhookToken(
      config.whatsapp.webhookVerifyToken,
      token
    );

    return verified ? challenge ?? null : null;
  }

  async processInboundWebhook(payload: unknown): Promise<void> {
    const message = this.extractInboundMessage(payload);

    if (!message) {
      await this.activityLogService.info(
        "whatsapp.webhook.ignored",
        "Ignored webhook payload without an inbound text message."
      );
      return;
    }

    const config = await this.runtimeConfigService.getDecryptedConfig();
    const target = config.defaultTarget;
    const domain = config.domains[target];
    const request: AssistantRequest = {
      tenantId: config.tenantId,
      target,
      companyCode: domain.assistant.companyCode,
      conversationId: `whatsapp_${message.from}`,
      userId: message.from,
      actorUserId: domain.assistant.whatsappActorUserId,
      message: message.text.body,
      propertyCode: domain.assistant.defaultPropertyCode,
      channel: "whatsapp",
      roles: this.parseCsv(domain.assistant.whatsappRolesCsv),
      permissions: this.parseCsv(domain.assistant.whatsappPermissionsCsv),
      locale: domain.assistant.defaultLocale
    };

    await this.activityLogService.info("whatsapp.webhook.received", "Processing inbound WhatsApp message.", {
      from: message.from,
      conversationId: request.conversationId
    }, target);

    const response = await this.assistantOrchestrator.handleUserMessage(request);

    if (response.answer) {
      await this.sendTextMessage(message.from, response.answer);
    }
  }

  private extractInboundMessage(payload: unknown): MetaWebhookMessage | null {
    const entry = (payload as { entry?: Array<{ changes?: Array<{ value?: { messages?: MetaWebhookMessage[] } }> }> })
      ?.entry?.[0];
    const change = entry?.changes?.[0];
    const message = change?.value?.messages?.[0];

    if (!message?.from || !message?.text?.body) {
      return null;
    }

    return message;
  }

  private parseCsv(value: string): string[] {
    return value
      .split(",")
      .map((entry) => entry.trim())
      .filter(Boolean);
  }
}

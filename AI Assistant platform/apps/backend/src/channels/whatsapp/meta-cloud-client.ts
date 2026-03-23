import { DecryptedRuntimeConfig } from "@vlv-ai/shared";

import { ConfigurationError } from "../../shared/errors";

export interface SendTextMessageInput {
  to: string;
  body: string;
}

export class MetaCloudWhatsAppClient {
  async testConnection(config: DecryptedRuntimeConfig["whatsapp"]): Promise<string> {
    if (!config.apiToken) {
      throw new ConfigurationError("WhatsApp API token is not configured.");
    }

    const url = new URL(`${config.phoneNumberId}?fields=id,display_phone_number`, this.normalizeBaseUrl(config.baseUrl));
    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${config.apiToken}`
      }
    });

    if (!response.ok) {
      const body = await response.text();
      throw new ConfigurationError("WhatsApp connection test failed.", {
        status: response.status,
        body
      });
    }

    const payload = (await response.json()) as { display_phone_number?: string };
    return `Connected to WhatsApp phone number ${payload.display_phone_number ?? config.phoneNumberId}.`;
  }

  async sendTextMessage(
    config: DecryptedRuntimeConfig["whatsapp"],
    input: SendTextMessageInput
  ): Promise<void> {
    if (!config.apiToken) {
      throw new ConfigurationError("WhatsApp API token is not configured.");
    }

    const url = new URL(`${config.phoneNumberId}/messages`, this.normalizeBaseUrl(config.baseUrl));
    const response = await fetch(url, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${config.apiToken}`,
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        messaging_product: "whatsapp",
        to: input.to,
        type: "text",
        text: {
          body: input.body
        }
      })
    });

    if (!response.ok) {
      const body = await response.text();
      throw new ConfigurationError("WhatsApp send failed.", {
        status: response.status,
        body
      });
    }
  }

  verifyWebhookToken(expectedToken: string | undefined, receivedToken: string | undefined): boolean {
    return Boolean(expectedToken) && expectedToken === receivedToken;
  }

  private normalizeBaseUrl(baseUrl: string): string {
    return baseUrl.endsWith("/") ? baseUrl : `${baseUrl}/`;
  }
}

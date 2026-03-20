import OpenAI from "openai";

import { RuntimeConfigService } from "../config/runtime-config-service";
import { ConfigurationError } from "../shared/errors";

export class OpenAIClientFactory {
  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async createClient(): Promise<OpenAI> {
    const config = await this.runtimeConfigService.getDecryptedConfig();

    if (!config.openai.apiKey) {
      throw new ConfigurationError("OpenAI API key is not configured.");
    }

    return new OpenAI({
      apiKey: config.openai.apiKey,
      baseURL: config.openai.baseUrl,
      timeout: config.openai.timeoutMs
    });
  }

  async getModel(): Promise<string> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    return config.openai.model;
  }
}

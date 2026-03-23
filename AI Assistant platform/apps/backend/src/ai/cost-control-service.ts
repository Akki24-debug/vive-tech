import { AssistantTarget, DecryptedRuntimeConfig, OptimizationRuntimeConfigInput } from "@vlv-ai/shared";

import { RuntimeConfigService } from "../config/runtime-config-service";

export interface ResolvedCostControl extends Required<OptimizationRuntimeConfigInput> {
  target: AssistantTarget;
  effectiveModel: string;
  source: {
    cheapModeEnabled: "env" | "runtime";
    effectiveModel: "env" | "runtime";
  };
}

function parseBoolean(value: string | undefined): boolean | undefined {
  if (value === undefined) {
    return undefined;
  }

  const normalized = value.trim().toLowerCase();
  if (["1", "true", "yes", "on"].includes(normalized)) {
    return true;
  }

  if (["0", "false", "no", "off"].includes(normalized)) {
    return false;
  }

  return undefined;
}

function parsePositiveInt(value: string | undefined): number | undefined {
  if (!value) {
    return undefined;
  }

  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
}

export class CostControlService {
  constructor(private readonly runtimeConfigService: RuntimeConfigService) {}

  async getResolvedConfig(
    target: AssistantTarget,
    runtimeConfig?: DecryptedRuntimeConfig
  ): Promise<ResolvedCostControl> {
    const config = runtimeConfig ?? (await this.runtimeConfigService.getDecryptedConfig());
    const runtime = config.optimization;
    const envCheapMode = parseBoolean(process.env.AI_DEBUG_CHEAP_MODE);
    const envDebugModel = process.env.AI_MODEL_DEBUG?.trim() || undefined;

    const cheapModeEnabled = envCheapMode ?? runtime.cheapModeEnabled;
    const effectiveModel =
      cheapModeEnabled && envDebugModel
        ? envDebugModel
        : cheapModeEnabled && runtime.debugModelOverride
          ? runtime.debugModelOverride
          : config.openai.model;

    return {
      target,
      cheapModeEnabled,
      debugModelOverride: envDebugModel ?? runtime.debugModelOverride,
      disableBroadBrainSnapshots:
        parseBoolean(process.env.AI_DISABLE_BRAIN_SNAPSHOT) ?? runtime.disableBroadBrainSnapshots,
      skipFinalLlmForSimpleReads:
        parseBoolean(process.env.AI_SKIP_FINAL_RESPONSE_LLM_ON_SIMPLE_READS) ??
        runtime.skipFinalLlmForSimpleReads,
      logEstimatedCost:
        parseBoolean(process.env.AI_LOG_ESTIMATED_COST) ?? runtime.logEstimatedCost,
      maxRecentConversationMessages:
        parsePositiveInt(process.env.AI_MAX_RECENT_CONVERSATION_MESSAGES) ??
        runtime.maxRecentConversationMessages,
      maxDocs: parsePositiveInt(process.env.AI_MAX_DOCS) ?? runtime.maxDocs,
      maxDocsBundleBytes:
        parsePositiveInt(process.env.AI_MAX_DOCS_BUNDLE_BYTES) ?? runtime.maxDocsBundleBytes,
      effectiveModel,
      source: {
        cheapModeEnabled: envCheapMode !== undefined ? "env" : "runtime",
        effectiveModel: envDebugModel ? "env" : cheapModeEnabled ? "runtime" : "runtime"
      }
    };
  }
}

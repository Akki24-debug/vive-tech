import crypto from "node:crypto";

import { AssistantTarget } from "@vlv-ai/shared";

import { ActivityLogService } from "../logging/activity-log-service";

interface TelemetryPayload {
  requestId: string;
  conversationId: string;
  stage: "proposal" | "final_response" | "preflight" | "execution";
  target: AssistantTarget;
  model?: string;
  cheapModeEnabled?: boolean;
  selectedAction?: string;
  docs?: {
    keys: string[];
    totalChars: number;
    totalBytes: number;
  };
  recentConversationMessages?: number;
  backendPayload?: {
    chars: number;
    bytes: number;
    hash: string;
    preview?: string;
  };
  promptPreview?: string;
  latencyMs?: number;
  usage?: {
    promptTokens: number | null;
    completionTokens: number | null;
    totalTokens: number | null;
  };
  estimatedUsdCost?: number | null;
  finalLlmSkipped?: boolean;
  templateUsed?: boolean;
  details?: Record<string, unknown>;
}

const MODEL_PRICING: Record<string, { inputPerMillion: number; outputPerMillion: number }> = {
  "gpt-5-mini": { inputPerMillion: 0.25, outputPerMillion: 2.0 },
  "gpt-5-nano": { inputPerMillion: 0.05, outputPerMillion: 0.4 },
  "gpt-4.1": { inputPerMillion: 2.0, outputPerMillion: 8.0 },
  "gpt-4.1-mini": { inputPerMillion: 0.4, outputPerMillion: 1.6 }
};

function sha256(value: string): string {
  return crypto.createHash("sha256").update(value).digest("hex");
}

function redactValue(value: unknown): unknown {
  if (typeof value === "string") {
    if (/sk-[a-z0-9]/i.test(value) || /bearer\s+/i.test(value)) {
      return "[REDACTED]";
    }

    if (value.length > 800) {
      return `${value.slice(0, 800)}…[truncated ${value.length - 800} chars]`;
    }

    return value;
  }

  if (Array.isArray(value)) {
    return value.map((entry) => redactValue(entry));
  }

  if (!value || typeof value !== "object") {
    return value;
  }

  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).map(([key, entry]) => {
      if (/(api.?key|token|secret|password|authorization)/i.test(key)) {
        return [key, "[REDACTED]"];
      }

      return [key, redactValue(entry)];
    })
  );
}

function extractUsage(response: unknown): {
  promptTokens: number | null;
  completionTokens: number | null;
  totalTokens: number | null;
} {
  const usage = (response as { usage?: Record<string, unknown> } | null)?.usage;
  if (!usage) {
    return {
      promptTokens: null,
      completionTokens: null,
      totalTokens: null
    };
  }

  const promptTokens =
    typeof usage.input_tokens === "number"
      ? usage.input_tokens
      : typeof usage.prompt_tokens === "number"
        ? usage.prompt_tokens
        : null;
  const completionTokens =
    typeof usage.output_tokens === "number"
      ? usage.output_tokens
      : typeof usage.completion_tokens === "number"
        ? usage.completion_tokens
        : null;
  const totalTokens =
    typeof usage.total_tokens === "number"
      ? usage.total_tokens
      : promptTokens !== null && completionTokens !== null
        ? promptTokens + completionTokens
        : null;

  return {
    promptTokens,
    completionTokens,
    totalTokens
  };
}

function estimateCostUsd(
  model: string | undefined,
  usage: { promptTokens: number | null; completionTokens: number | null }
): number | null {
  if (!model) {
    return null;
  }

  const pricing = MODEL_PRICING[model];
  if (!pricing) {
    return null;
  }

  const inputCost = usage.promptTokens ? (usage.promptTokens / 1_000_000) * pricing.inputPerMillion : 0;
  const outputCost = usage.completionTokens
    ? (usage.completionTokens / 1_000_000) * pricing.outputPerMillion
    : 0;

  return Number((inputCost + outputCost).toFixed(6));
}

export class AiTelemetryService {
  constructor(private readonly activityLogService: ActivityLogService) {}

  async logStarted(payload: TelemetryPayload): Promise<void> {
    await this.activityLogService.info(
      "ai.request.started",
      `Started ${payload.stage} request.`,
      redactValue(payload),
      payload.target
    );
  }

  async logCompleted(payload: TelemetryPayload, response?: unknown): Promise<void> {
    const usage = extractUsage(response);
    const estimatedUsdCost = payload.estimatedUsdCost ?? estimateCostUsd(payload.model, usage);

    const basePayload = redactValue({
      ...payload,
      usage,
      estimatedUsdCost
    });

    await this.activityLogService.info(
      "ai.request.completed",
      `Completed ${payload.stage} request.`,
      basePayload,
      payload.target
    );

    await this.activityLogService.info(
      "ai.request.costed",
      `Costed ${payload.stage} request.`,
      basePayload,
      payload.target
    );
  }

  async logPreflightFailed(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    details: Record<string, unknown>
  ): Promise<void> {
    await this.activityLogService.warn(
      "ai.preflight.failed",
      "Rejected assistant request before spending tokens.",
      redactValue({
        requestId,
        conversationId,
        target,
        details
      }),
      target
    );
  }

  async logDocsBundleBuilt(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    details: {
      stage: "proposal" | "final_response";
      keys: string[];
      totalChars: number;
      totalBytes: number;
    }
  ): Promise<void> {
    await this.activityLogService.info(
      "assistant.docs.bundle_built",
      "Built documentation bundle for assistant request.",
      redactValue({
        requestId,
        conversationId,
        target,
        ...details
      }),
      target
    );
  }

  async logRoutingSelected(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    action: string,
    reason: string
  ): Promise<void> {
    await this.activityLogService.info(
      "assistant.routing.selected",
      "Selected assistant action route.",
      {
        requestId,
        conversationId,
        target,
        action,
        reason
      },
      target
    );
  }

  async logBlockedBroadRead(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    reason: string
  ): Promise<void> {
    await this.activityLogService.warn(
      "assistant.routing.blocked_broad_read",
      "Blocked a broad Brain read.",
      {
        requestId,
        conversationId,
        target,
        reason
      },
      target
    );
  }

  async logTemplatedResponse(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    action: string,
    backendResult: unknown
  ): Promise<void> {
    const serialized = JSON.stringify(redactValue(backendResult));
    await this.activityLogService.info(
      "assistant.response.templated",
      "Served a templated assistant response.",
      {
        requestId,
        conversationId,
        target,
        action,
        backendPayload: {
          chars: serialized.length,
          bytes: Buffer.byteLength(serialized, "utf8"),
          hash: sha256(serialized),
          preview: serialized.slice(0, 800)
        }
      },
      target
    );
  }

  async logSkippedFinalLlm(
    target: AssistantTarget,
    requestId: string,
    conversationId: string,
    action: string
  ): Promise<void> {
    await this.activityLogService.info(
      "assistant.response.llm_skipped",
      "Skipped final LLM response generation.",
      {
        requestId,
        conversationId,
        target,
        action
      },
      target
    );
  }

  createPayloadSnapshot(value: unknown): {
    chars: number;
    bytes: number;
    hash: string;
    preview: string;
  } {
    const serialized = JSON.stringify(redactValue(value));
    return {
      chars: serialized.length,
      bytes: Buffer.byteLength(serialized, "utf8"),
      hash: sha256(serialized),
      preview: serialized.slice(0, 800)
    };
  }
}

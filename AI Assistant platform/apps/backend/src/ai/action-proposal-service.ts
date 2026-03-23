import { ActionProposal, AssistantRequest, ConversationRecord } from "@vlv-ai/shared";

import { listActionCatalog } from "../actions/action-registry";
import { ActivityLogService } from "../logging/activity-log-service";
import { ValidationError } from "../shared/errors";
import { AiTelemetryService } from "./ai-telemetry-service";
import { AiWorkflowPreflightService } from "./ai-workflow-preflight-service";
import { routeBrainCheapIntent } from "./brain-cheap-router";
import { CostControlService } from "./cost-control-service";
import { DomainHealthService } from "./domain-health-service";
import { OpenAIClientFactory } from "./openai-client";

const actionProposalSchema = {
  type: "json_schema",
  name: "action_proposal",
  schema: {
    type: "object",
    additionalProperties: false,
    required: [
      "intent",
      "confidence",
      "action",
      "argumentsJson",
      "summary",
      "needsHumanApproval"
    ],
    properties: {
      intent: {
        type: "string"
      },
      confidence: {
        type: "number",
        minimum: 0,
        maximum: 1
      },
      action: {
        type: "string"
      },
      argumentsJson: {
        type: "string"
      },
      summary: {
        type: "string"
      },
      needsHumanApproval: {
        type: "boolean"
      }
    }
  },
  strict: true
} as const;

export class ActionProposalService {
  constructor(
    private readonly openAIClientFactory: OpenAIClientFactory,
    private readonly activityLogService: ActivityLogService,
    private readonly costControlService: CostControlService,
    private readonly preflightService: AiWorkflowPreflightService,
    private readonly domainHealthService: DomainHealthService,
    private readonly aiTelemetryService: AiTelemetryService
  ) {}

  async createProposal(
    request: AssistantRequest,
    conversation: ConversationRecord
  ): Promise<ActionProposal> {
    const requestId = request.requestId ?? "unknown";
    const control = await this.costControlService.getResolvedConfig(request.target);
    const health = this.domainHealthService.getHealth(request.target);
    const cheapRoute = routeBrainCheapIntent(
      request,
      control,
      this.domainHealthService.shouldBlockBroadReads(request.target)
    );

    if (cheapRoute) {
      if (cheapRoute.proposal.action === "conversation.clarify" && /broad_snapshot/.test(cheapRoute.reason)) {
        await this.aiTelemetryService.logBlockedBroadRead(
          request.target,
          requestId,
          request.conversationId,
          cheapRoute.reason
        );
      }

      await this.aiTelemetryService.logRoutingSelected(
        request.target,
        requestId,
        request.conversationId,
        cheapRoute.proposal.action,
        cheapRoute.reason
      );
      return cheapRoute.proposal;
    }

    const preflight = await this.preflightService.ensureBeforeProposal(request, {
      maxDocs: control.maxDocs,
      maxDocsBundleBytes: control.maxDocsBundleBytes
    });

    if (!preflight.ok || !preflight.bundle) {
      this.domainHealthService.markFailure(
        request.target,
        "preflight",
        preflight.reasons.join(" | ") || "unknown preflight failure"
      );
      await this.aiTelemetryService.logPreflightFailed(
        request.target,
        requestId,
        request.conversationId,
        {
          stage: "proposal",
          reasons: preflight.reasons
        }
      );

      return {
        intent: "Detener la solicitud por falla local de preflight",
        confidence: 0.99,
        action: "conversation.clarify",
        arguments: {
          question:
            "La consola detectó un problema local de configuración o conectividad y detuvo la consulta antes de gastar tokens. Revisa docs, OpenAI y la conexión a la base antes de volver a intentar."
        },
        summary: "La solicitud fue detenida por un preflight local fallido.",
        needsHumanApproval: false
      };
    }

    this.domainHealthService.clearFailures(request.target, "preflight");
    this.domainHealthService.clearFailures(request.target, "db");
    this.domainHealthService.clearFailures(request.target, "docs");

    await this.aiTelemetryService.logDocsBundleBuilt(request.target, requestId, request.conversationId, {
      stage: "proposal",
      keys: preflight.bundle.documents.map((document) => String(document.key)),
      totalChars: preflight.bundle.totalChars,
      totalBytes: preflight.bundle.totalBytes
    });

    const actionCatalog = listActionCatalog(request.target)
      .map((action) => {
        return `- ${action.name}: ${action.description}. Target: ${action.target}. Executable: ${action.executable}. Procedure: ${action.procedureName ?? "none"}. Required args: ${action.requiredArguments.join(", ") || "none"}. Required permissions: ${action.requiredPermissions.join(", ") || "none"}.`;
      })
      .join("\n");
    const recentMessages = conversation.messages.slice(
      -Math.max(1, control.maxRecentConversationMessages)
    );
    const client = await this.openAIClientFactory.createClient();
    const model = control.effectiveModel;
    const startedAt = Date.now();

    await this.aiTelemetryService.logStarted({
      requestId,
      conversationId: request.conversationId,
      stage: "proposal",
      target: request.target,
      model,
      cheapModeEnabled: control.cheapModeEnabled,
      docs: {
        keys: preflight.bundle.documents.map((document) => String(document.key)),
        totalChars: preflight.bundle.totalChars,
        totalBytes: preflight.bundle.totalBytes
      },
      recentConversationMessages: recentMessages.length,
      promptPreview: request.message
    });

    let response;
    try {
      response = await client.responses.create({
        model,
        input: [
          {
            role: "system",
            content: [
              {
                type: "input_text",
                text: [
                  "You are the action proposal layer for a multi-domain AI backend.",
                  "You must pick exactly one action from the provided action catalog.",
                  "You must never write SQL, never invent a stored procedure, and never call actions outside the catalog.",
                  "You must never browse the web or rely on any source outside the backend docs bundle and request context.",
                  "If the user request lacks required information, choose conversation.clarify.",
                  "Only use actions belonging to the request target.",
                  "Prefer the narrowest read action that can answer the request. Use a composite snapshot action only when the user is asking for a broad state question.",
                  "Return action arguments as a JSON string in argumentsJson. If no arguments are needed, return '{}'.",
                  "This admin console is permanently scoped to Vive la Vibe as a single organization.",
                  "Never ask the user for organizationId.",
                  "Never mention other organizations, multi-organization choices, or organization scope selection.",
                  "For business_brain, if an action supports organizationId, assume the backend will resolve the single organization automatically.",
                  "Only choose conversation.clarify when a truly required input is missing for the selected action.",
                  "",
                  `## Active Target\n${request.target}`,
                  "",
                  "## Action Catalog",
                  actionCatalog,
                  "",
                  "## Documentation Context",
                  preflight.bundle.text
                ].join("\n")
              }
            ]
          },
          {
            role: "user",
            content: [
              {
                type: "input_text",
                text: [
                  `Tenant: ${request.tenantId}`,
                  `Target: ${request.target}`,
                  `Company code: ${request.companyCode}`,
                  `Channel: ${request.channel}`,
                  `Channel user ID: ${request.userId}`,
                  `Actor user ID: ${request.actorUserId}`,
                  `Roles: ${request.roles.join(", ") || "none"}`,
                  `Permissions: ${request.permissions.join(", ") || "none"}`,
                  `Property scope: ${request.propertyCode ?? "none"}`,
                  `Conversation summary: ${conversation.summary || "none"}`,
                  "",
                  "Recent conversation messages:",
                  ...recentMessages.map(
                    (message: ConversationRecord["messages"][number]) =>
                      `${message.role}: ${message.content}`
                  ),
                  "",
                  `Latest user message: ${request.message}`
                ].join("\n")
              }
            ]
          }
        ],
        text: {
          format: actionProposalSchema
        }
      });
    } catch (error) {
      const reason = error instanceof Error ? error.message : "unknown OpenAI proposal error";
      this.domainHealthService.markFailure(
        request.target,
        /timeout/i.test(reason) ? "timeout" : "preflight",
        reason
      );
      throw error;
    }

    const payload = response.output_text;

    if (!payload) {
      throw new ValidationError("OpenAI returned an empty action proposal.");
    }

    let parsed: ActionProposal & { argumentsJson?: string };

    try {
      parsed = JSON.parse(payload) as ActionProposal & { argumentsJson?: string };
    } catch (error) {
      await this.activityLogService.error("ai.action_proposal.parse_failed", "Failed to parse AI action proposal.", {
        payload,
        error: error instanceof Error ? error.message : "unknown"
      }, request.target);
      throw new ValidationError("The action proposal response could not be parsed.", {
        payload
      });
    }

    if (typeof parsed.argumentsJson === "string") {
      try {
        parsed.arguments = parsed.argumentsJson.trim() ? JSON.parse(parsed.argumentsJson) : {};
      } catch (error) {
        await this.activityLogService.error(
          "ai.action_proposal.arguments_parse_failed",
          "Failed to parse action proposal arguments JSON.",
          {
            argumentsJson: parsed.argumentsJson,
            error: error instanceof Error ? error.message : "unknown"
          },
          request.target
        );
        throw new ValidationError("The action proposal arguments could not be parsed.", {
          argumentsJson: parsed.argumentsJson
        });
      }
    }

    if (!parsed.arguments || typeof parsed.arguments !== "object" || Array.isArray(parsed.arguments)) {
      parsed.arguments = {};
    }

    await this.activityLogService.info("ai.action_proposal.created", "Created structured action proposal.", {
      requestId,
      conversationId: request.conversationId,
      action: parsed.action,
      intent: parsed.intent,
      confidence: parsed.confidence
    }, request.target);

    await this.aiTelemetryService.logCompleted(
      {
        requestId,
        conversationId: request.conversationId,
        stage: "proposal",
        target: request.target,
        model,
        cheapModeEnabled: control.cheapModeEnabled,
        selectedAction: parsed.action,
        docs: {
          keys: preflight.bundle.documents.map((document) => String(document.key)),
          totalChars: preflight.bundle.totalChars,
          totalBytes: preflight.bundle.totalBytes
        },
        recentConversationMessages: recentMessages.length,
        latencyMs: Date.now() - startedAt,
        promptPreview: request.message,
        estimatedUsdCost: control.logEstimatedCost ? undefined : null
      },
      response
    );

    await this.aiTelemetryService.logRoutingSelected(
      request.target,
      requestId,
      request.conversationId,
      parsed.action,
      health.stable ? "llm_selected_action" : "llm_selected_action_under_recovered_health"
    );

    return parsed;
  }
}

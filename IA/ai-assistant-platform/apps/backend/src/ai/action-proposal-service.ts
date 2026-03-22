import { ActionProposal, AssistantRequest, ConversationRecord } from "@vlv-ai/shared";

import { listActionCatalog } from "../actions/action-registry";
import { DocumentationService } from "../docs/documentation-service";
import { ActivityLogService } from "../logging/activity-log-service";
import { ValidationError } from "../shared/errors";
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
    private readonly documentationService: DocumentationService,
    private readonly activityLogService: ActivityLogService
  ) {}

  async createProposal(
    request: AssistantRequest,
    conversation: ConversationRecord
  ): Promise<ActionProposal> {
    const client = await this.openAIClientFactory.createClient();
    const model = await this.openAIClientFactory.getModel();
    const docsBundle = await this.documentationService.buildPromptBundle(request.target);
    const actionCatalog = listActionCatalog(request.target)
      .map((action) => {
        return `- ${action.name}: ${action.description}. Target: ${action.target}. Executable: ${action.executable}. Procedure: ${action.procedureName ?? "none"}. Required args: ${action.requiredArguments.join(", ") || "none"}. Required permissions: ${action.requiredPermissions.join(", ") || "none"}.`;
      })
      .join("\n");

    const response = await client.responses.create({
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
                docsBundle
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
                ...conversation.messages.map(
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
      action: parsed.action,
      intent: parsed.intent,
      confidence: parsed.confidence
    }, request.target);

    return parsed;
  }
}

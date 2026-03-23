import { AssistantRequest } from "@vlv-ai/shared";

import { ActionExecutionResult } from "../actions/action-execution-service";
import { AiTelemetryService } from "./ai-telemetry-service";
import { AiWorkflowPreflightService } from "./ai-workflow-preflight-service";
import { CostControlService } from "./cost-control-service";
import { DomainHealthService } from "./domain-health-service";
import { OpenAIClientFactory } from "./openai-client";
import { ResponseTemplateService } from "./response-template-service";

export class FinalResponseService {
  constructor(
    private readonly openAIClientFactory: OpenAIClientFactory,
    private readonly costControlService: CostControlService,
    private readonly preflightService: AiWorkflowPreflightService,
    private readonly domainHealthService: DomainHealthService,
    private readonly aiTelemetryService: AiTelemetryService,
    private readonly responseTemplateService: ResponseTemplateService
  ) {}

  async createFinalAnswer(
    request: AssistantRequest,
    actionName: string,
    backendResult: unknown
  ): Promise<string> {
    const requestId = request.requestId ?? "unknown";
    const control = await this.costControlService.getResolvedConfig(request.target);
    const execution = backendResult as ActionExecutionResult;
    const summarizedPayload = this.summarizeBackendResult(execution);

    if (
      control.skipFinalLlmForSimpleReads &&
      this.responseTemplateService.canTemplate(actionName)
    ) {
      const answer = this.responseTemplateService.render(actionName, execution, request.message);
      await this.aiTelemetryService.logSkippedFinalLlm(
        request.target,
        requestId,
        request.conversationId,
        actionName
      );
      await this.aiTelemetryService.logTemplatedResponse(
        request.target,
        requestId,
        request.conversationId,
        actionName,
        summarizedPayload
      );
      return answer;
    }

    const preflight = await this.preflightService.ensureBeforeFinalResponse(request, {
      maxDocs: Math.max(2, control.maxDocs - 1),
      maxDocsBundleBytes: Math.max(8_000, Math.floor(control.maxDocsBundleBytes * 0.75))
    });

    if (!preflight.ok || !preflight.bundle) {
      this.domainHealthService.markFailure(
        request.target,
        "preflight",
        preflight.reasons.join(" | ") || "unknown final-response preflight failure"
      );
      await this.aiTelemetryService.logPreflightFailed(
        request.target,
        requestId,
        request.conversationId,
        {
          stage: "final_response",
          reasons: preflight.reasons
        }
      );

      if (this.responseTemplateService.canTemplate(actionName)) {
        await this.aiTelemetryService.logSkippedFinalLlm(
          request.target,
          requestId,
          request.conversationId,
          actionName
        );
        await this.aiTelemetryService.logTemplatedResponse(
          request.target,
          requestId,
          request.conversationId,
          actionName,
          summarizedPayload
        );
        return this.responseTemplateService.render(actionName, execution, request.message);
      }

      return "La consola detectó un problema local antes de generar la respuesta final. Revisa configuración, docs y conectividad del dominio antes de volver a intentar.";
    }

    this.domainHealthService.clearFailures(request.target, "preflight");
    this.domainHealthService.clearFailures(request.target, "db");
    this.domainHealthService.clearFailures(request.target, "docs");

    await this.aiTelemetryService.logDocsBundleBuilt(request.target, requestId, request.conversationId, {
      stage: "final_response",
      keys: preflight.bundle.documents.map((document) => String(document.key)),
      totalChars: preflight.bundle.totalChars,
      totalBytes: preflight.bundle.totalBytes
    });

    const client = await this.openAIClientFactory.createClient();
    const model = control.effectiveModel;
    const startedAt = Date.now();

    await this.aiTelemetryService.logStarted({
      requestId,
      conversationId: request.conversationId,
      stage: "final_response",
      target: request.target,
      model,
      cheapModeEnabled: control.cheapModeEnabled,
      selectedAction: actionName,
      docs: {
        keys: preflight.bundle.documents.map((document) => String(document.key)),
        totalChars: preflight.bundle.totalChars,
        totalBytes: preflight.bundle.totalBytes
      },
      backendPayload: this.aiTelemetryService.createPayloadSnapshot(summarizedPayload),
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
                  "You are the response layer for a multi-domain operations assistant.",
                  "Answer only with information supported by the backend result.",
                  "Do not expose internal procedure names or technical implementation details unless asked.",
                  "If the result is empty, say that no matching information was found.",
                  "Respond in Spanish unless the request clearly asked for another language.",
                  "If the active target is PMS and the backend result contains quote or pricing data, answer in a Markdown table and always include a total or estimated total.",
                  "If the active target is Business Brain, prefer concise operational summaries with clear sections for entities, counts, and important statuses.",
                  "When describing PMS prices, prefer the labels 'precio normal' and 'precio especial con descuento por pagar en mostrador'.",
                  "When describing PMS payments, use the explicit payments block from the backend result and do not confuse payments with sale items.",
                  "",
                  `## Active Target\n${request.target}`,
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
                  `Latest user message: ${request.message}`,
                  `Approved action: ${actionName}`,
                  `Backend result summary: ${JSON.stringify(summarizedPayload, null, 2)}`
                ].join("\n\n")
              }
            ]
          }
        ]
      });
    } catch (error) {
      const reason = error instanceof Error ? error.message : "unknown OpenAI final response error";
      this.domainHealthService.markFailure(
        request.target,
        /timeout/i.test(reason) ? "timeout" : "preflight",
        reason
      );
      throw error;
    }
    await this.aiTelemetryService.logCompleted(
      {
        requestId,
        conversationId: request.conversationId,
        stage: "final_response",
        target: request.target,
        model,
        cheapModeEnabled: control.cheapModeEnabled,
        selectedAction: actionName,
        docs: {
          keys: preflight.bundle.documents.map((document) => String(document.key)),
          totalChars: preflight.bundle.totalChars,
          totalBytes: preflight.bundle.totalBytes
        },
        backendPayload: this.aiTelemetryService.createPayloadSnapshot(summarizedPayload),
        latencyMs: Date.now() - startedAt,
        promptPreview: request.message,
        estimatedUsdCost: control.logEstimatedCost ? undefined : null
      },
      response
    );

    return response.output_text?.trim() || "No response was generated.";
  }

  private summarizeBackendResult(result: ActionExecutionResult): Record<string, unknown> {
    const data = result.data as Record<string, unknown> | undefined;
    const rows =
      Array.isArray((data?.rows as unknown[] | undefined)) &&
      (data?.rows as unknown[]).every((row) => typeof row === "object" && row !== null)
        ? (data?.rows as Record<string, unknown>[])
        : [];

    if (rows.length > 0) {
      return {
        actionName: result.actionName,
        target: result.target,
        mode: result.mode,
        rowCount: rows.length,
        sample: rows.slice(0, 6),
        filtersApplied: data?.filtersApplied ?? null,
        sources: result.sources
      };
    }

    return {
      actionName: result.actionName,
      target: result.target,
      mode: result.mode,
      data: result.data,
      sources: result.sources
    };
  }
}

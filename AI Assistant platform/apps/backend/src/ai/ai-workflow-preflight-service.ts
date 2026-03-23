import { AssistantRequest, AssistantTarget } from "@vlv-ai/shared";

import { RuntimeConfigService } from "../config/runtime-config-service";
import { MariaDbPool } from "../db/mariadb-pool";
import { DocumentationService, PromptBundle } from "../docs/documentation-service";

export interface PreflightResult {
  ok: boolean;
  bundle?: PromptBundle;
  reasons: string[];
}

export class AiWorkflowPreflightService {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly documentationService: DocumentationService,
    private readonly mariaDbPool: MariaDbPool
  ) {}

  async ensureBeforeProposal(
    request: AssistantRequest,
    options?: {
      maxDocs?: number;
      maxDocsBundleBytes?: number;
    }
  ): Promise<PreflightResult> {
    return this.runChecks(request.target, "proposal", options);
  }

  async ensureBeforeFinalResponse(
    request: AssistantRequest,
    options?: {
      maxDocs?: number;
      maxDocsBundleBytes?: number;
    }
  ): Promise<PreflightResult> {
    return this.runChecks(request.target, "final_response", options);
  }

  private async runChecks(
    target: AssistantTarget,
    purpose: "proposal" | "final_response",
    options?: {
      maxDocs?: number;
      maxDocsBundleBytes?: number;
    }
  ): Promise<PreflightResult> {
    const reasons: string[] = [];

    try {
      const config = await this.runtimeConfigService.getDecryptedConfig();

      if (!config.openai.apiKey) {
        reasons.push("OpenAI API key is missing.");
      }

      if (!config.openai.timeoutMs || config.openai.timeoutMs < 30_000) {
        reasons.push("OpenAI timeout budget is too low.");
      }

      if (!config.domains[target]?.enabled) {
        reasons.push(`Target domain ${target} is disabled.`);
      }
    } catch (error) {
      reasons.push(error instanceof Error ? error.message : "Runtime config unavailable.");
      return { ok: false, reasons };
    }

    let bundle: PromptBundle | undefined;

    try {
      bundle = await this.documentationService.buildBundle(target, purpose, {
        maxDocs: options?.maxDocs,
        maxBytes: options?.maxDocsBundleBytes
      });
      if (!bundle.text.trim() || bundle.documents.length === 0) {
        reasons.push("Documentation bundle is empty.");
      }
    } catch (error) {
      reasons.push(error instanceof Error ? error.message : "Documentation bundle could not be built.");
    }

    try {
      const pool = await this.mariaDbPool.getPool(target);
      await pool.query("SELECT 1 AS ok");
    } catch (error) {
      reasons.push(error instanceof Error ? error.message : "Database is unreachable.");
    }

    return {
      ok: reasons.length === 0,
      bundle,
      reasons
    };
  }
}

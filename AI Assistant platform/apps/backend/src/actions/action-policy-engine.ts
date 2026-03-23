import { ActionProposal, AssistantRequest } from "@vlv-ai/shared";
import { z } from "zod";

import { RuntimeConfigService } from "../config/runtime-config-service";
import { AuthorizationService } from "../auth/authorization-service";
import { ConfigurationError, ValidationError } from "../shared/errors";
import { CostControlService } from "../ai/cost-control-service";
import { DomainHealthService } from "../ai/domain-health-service";
import { ActionDefinition, getActionDefinition } from "./action-registry";
import { BusinessBrainScopeResolver } from "./business-brain-scope-resolver";

export interface ValidatedAction {
  definition: ActionDefinition;
  parsedArguments: Record<string, unknown>;
  requiresApproval: boolean;
}

export class ActionPolicyEngine {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly authorizationService: AuthorizationService,
    private readonly businessBrainScopeResolver: BusinessBrainScopeResolver,
    private readonly costControlService: CostControlService,
    private readonly domainHealthService: DomainHealthService
  ) {}

  async validateProposal(
    request: AssistantRequest,
    proposal: ActionProposal
  ): Promise<ValidatedAction> {
    const runtimeConfig = await this.runtimeConfigService.getDecryptedConfig();

    if (runtimeConfig.tenantId !== request.tenantId) {
      throw new ConfigurationError("The request tenant does not match the configured tenant.", {
        expectedTenantId: runtimeConfig.tenantId,
        receivedTenantId: request.tenantId
      });
    }

    const domainConfig = runtimeConfig.domains[request.target];

    if (!domainConfig.enabled) {
      throw new ConfigurationError(`The target domain ${request.target} is disabled.`, {
        target: request.target
      });
    }

    const definition = getActionDefinition(request.target, proposal.action);

    if (!definition) {
      throw new ValidationError("The proposed action is not registered in the backend catalog.", {
        action: proposal.action,
        target: request.target
      });
    }

    this.authorizationService.assertActionAllowed(request, definition.requiredPermissions);

    if (request.target === "business_brain" && definition.name === "brain.current_context") {
      const control = await this.costControlService.getResolvedConfig(request.target, runtimeConfig);
      if (control.disableBroadBrainSnapshots) {
        throw new ValidationError(
          "Broad Business Brain snapshots are disabled in cheap/debug mode."
        );
      }

      if (this.domainHealthService.shouldBlockBroadReads(request.target)) {
        throw new ValidationError(
          "Broad Business Brain snapshots are temporarily blocked while the domain is unstable."
        );
      }
    }

    const preparedArguments = await this.prepareArguments(request, definition, proposal.arguments);
    const coercedArguments = this.coerceArguments(definition.argsSchema.shape, preparedArguments);
    const parsedArguments = definition.argsSchema.parse(coercedArguments);

    if (request.target === "pms" && request.actorUserId <= 0 && definition.executable) {
      throw new ValidationError("PMS actions require a positive actor user id.");
    }

    if (
      definition.name === "reservation.create_hold" &&
      !request.propertyCode &&
      !("propertyCode" in parsedArguments && parsedArguments.propertyCode)
    ) {
      throw new ValidationError(
        "Creating a hold requires a property code in the request scope or action arguments."
      );
    }

    const requiresApproval = this.resolveApprovalRequirement(
      definition,
      runtimeConfig.execution.mode,
      proposal.needsHumanApproval
    );

    if (definition.mode === "write" && !runtimeConfig.execution.enableWrites) {
      throw new ValidationError("Write actions are disabled in the runtime configuration.");
    }

    return {
      definition,
      parsedArguments,
      requiresApproval
    };
  }

  private resolveApprovalRequirement(
    definition: ActionDefinition,
    executionMode: "auto" | "manual" | "hybrid",
    proposalNeedsHumanApproval: boolean
  ): boolean {
    if (!definition.executable) {
      return false;
    }

    if (proposalNeedsHumanApproval) {
      return true;
    }

    if (executionMode === "manual") {
      return true;
    }

    if (executionMode === "hybrid" && definition.mode === "write") {
      return true;
    }

    return false;
  }

  private async prepareArguments(
    request: AssistantRequest,
    definition: ActionDefinition,
    value: unknown
  ): Promise<unknown> {
    const normalized = this.normalizeNullishArguments(value);

    if (
      request.target !== "business_brain" ||
      !normalized ||
      typeof normalized !== "object" ||
      Array.isArray(normalized)
    ) {
      return normalized;
    }

    if (!("organizationId" in definition.argsSchema.shape)) {
      return normalized;
    }

    const current = normalized as Record<string, unknown>;

    if (typeof current.organizationId === "number" && Number.isFinite(current.organizationId)) {
      return current;
    }

    const organizationId = await this.businessBrainScopeResolver.resolveDefaultOrganizationId();

    return {
      ...current,
      organizationId
    };
  }

  private normalizeNullishArguments(value: unknown): unknown {
    if (Array.isArray(value)) {
      return value.map((entry) => this.normalizeNullishArguments(entry));
    }

    if (!value || typeof value !== "object") {
      return value === null ? undefined : value;
    }

    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>)
        .filter(([, entry]) => entry !== null)
        .map(([key, entry]) => [key, this.normalizeNullishArguments(entry)])
    );
  }

  private coerceArguments(
    shape: Record<string, z.ZodTypeAny>,
    value: unknown
  ): unknown {
    if (!value || typeof value !== "object" || Array.isArray(value)) {
      return value;
    }

    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>).map(([key, entry]) => {
        const fieldSchema = shape[key];
        if (!fieldSchema) {
          return [key, entry];
        }

        if (typeof entry === "string" && this.isNumberLikeSchema(fieldSchema)) {
          const trimmed = entry.trim();
          if (/^-?\d+(\.\d+)?$/.test(trimmed)) {
            return [key, Number(trimmed)];
          }
        }

        if (typeof entry === "string" && this.isBooleanLikeSchema(fieldSchema)) {
          const trimmed = entry.trim().toLowerCase();
          if (trimmed === "true") {
            return [key, true];
          }

          if (trimmed === "false") {
            return [key, false];
          }
        }

        return [key, entry];
      })
    );
  }

  private isNumberLikeSchema(schema: z.ZodTypeAny): boolean {
    if (schema instanceof z.ZodNumber) {
      return true;
    }

    const inner = this.unwrapSchema(schema);
    if (inner) {
      return this.isNumberLikeSchema(inner);
    }

    return false;
  }

  private isBooleanLikeSchema(schema: z.ZodTypeAny): boolean {
    if (schema instanceof z.ZodBoolean) {
      return true;
    }

    const inner = this.unwrapSchema(schema);
    if (inner) {
      return this.isBooleanLikeSchema(inner);
    }

    return false;
  }

  private unwrapSchema(schema: z.ZodTypeAny): z.ZodTypeAny | null {
    if (schema instanceof z.ZodOptional || schema instanceof z.ZodNullable) {
      return schema.unwrap();
    }

    if (schema instanceof z.ZodDefault) {
      return schema.removeDefault();
    }

    return null;
  }
}

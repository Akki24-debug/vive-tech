import { ActionProposal, AssistantRequest } from "@vlv-ai/shared";

import { RuntimeConfigService } from "../config/runtime-config-service";
import { AuthorizationService } from "../auth/authorization-service";
import { ConfigurationError, ValidationError } from "../shared/errors";
import { ActionDefinition, getActionDefinition } from "./action-registry";

export interface ValidatedAction {
  definition: ActionDefinition;
  parsedArguments: Record<string, unknown>;
  requiresApproval: boolean;
}

export class ActionPolicyEngine {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly authorizationService: AuthorizationService
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

    const parsedArguments = definition.argsSchema.parse(proposal.arguments);

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
}

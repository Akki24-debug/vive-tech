import { ApprovalRecord, AssistantRequest, AssistantResponse } from "@vlv-ai/shared";

import { ActionExecutionService } from "../actions/action-execution-service";
import { ActionPolicyEngine } from "../actions/action-policy-engine";
import { ApprovalService } from "../approvals/approval-service";
import { ConversationStore } from "../conversations/conversation-store";
import { ActivityLogService } from "../logging/activity-log-service";
import { createId } from "../shared/ids";
import { ActionProposalService } from "./action-proposal-service";
import { FinalResponseService } from "./final-response-service";

export class AssistantOrchestrator {
  constructor(
    private readonly conversationStore: ConversationStore,
    private readonly actionProposalService: ActionProposalService,
    private readonly actionPolicyEngine: ActionPolicyEngine,
    private readonly approvalService: ApprovalService,
    private readonly actionExecutionService: ActionExecutionService,
    private readonly finalResponseService: FinalResponseService,
    private readonly activityLogService: ActivityLogService
  ) {}

  async handleUserMessage(request: AssistantRequest): Promise<AssistantResponse> {
    const normalizedRequest: AssistantRequest = {
      ...request,
      requestId: request.requestId ?? createId("req")
    };
    await this.conversationStore.appendMessage(
      normalizedRequest.tenantId,
      normalizedRequest.target,
      normalizedRequest.conversationId,
      normalizedRequest.channel,
      normalizedRequest.userId,
      "user",
      normalizedRequest.message
    );
    const conversation = await this.conversationStore.getOrCreate(
      normalizedRequest.tenantId,
      normalizedRequest.target,
      normalizedRequest.conversationId,
      normalizedRequest.channel,
      normalizedRequest.userId
    );
    const actionProposal = await this.actionProposalService.createProposal(normalizedRequest, conversation);
    const validation = await this.actionPolicyEngine.validateProposal(normalizedRequest, actionProposal);

    await this.activityLogService.info("assistant.request.validated", "Validated assistant action proposal.", {
      requestId: normalizedRequest.requestId,
      conversationId: normalizedRequest.conversationId,
      action: actionProposal.action,
      channel: normalizedRequest.channel,
      actorUserId: normalizedRequest.actorUserId
    }, normalizedRequest.target);

    if (!validation.definition.executable) {
      const answer =
        typeof validation.parsedArguments.question === "string"
          ? validation.parsedArguments.question
          : "I need more information before I can continue.";

      await this.conversationStore.appendMessage(
        normalizedRequest.tenantId,
        normalizedRequest.target,
        normalizedRequest.conversationId,
        normalizedRequest.channel,
        normalizedRequest.userId,
        "assistant",
        answer
      );

      return {
        status: "clarification",
        answer,
        requestId: normalizedRequest.requestId,
        actionProposal
      };
    }

    if (validation.requiresApproval) {
      const approval = await this.approvalService.createPendingApproval(normalizedRequest, actionProposal, {
        target: normalizedRequest.target,
        procedureName: validation.definition.procedure?.name,
        arguments: validation.parsedArguments,
        mode: validation.definition.mode
      });

      await this.activityLogService.info(
        "approval.created",
        "Queued action for manual approval.",
        {
          approvalId: approval.id,
          requestId: normalizedRequest.requestId,
          action: actionProposal.action
        },
        normalizedRequest.target
      );

      const answer = `The request was interpreted as ${actionProposal.action} and is waiting for manual approval.`;

      await this.conversationStore.appendMessage(
        normalizedRequest.tenantId,
        normalizedRequest.target,
        normalizedRequest.conversationId,
        normalizedRequest.channel,
        normalizedRequest.userId,
        "assistant",
        answer
      );

      return {
        status: "pending_approval",
        answer,
        requestId: normalizedRequest.requestId,
        approvalId: approval.id,
        actionProposal
      };
    }

    const execution = await this.actionExecutionService.execute(
      validation.definition,
      validation.parsedArguments,
      normalizedRequest
    );
    await this.activityLogService.info("assistant.action.executed", "Executed assistant action.", {
      requestId: normalizedRequest.requestId,
      conversationId: normalizedRequest.conversationId,
      action: actionProposal.action,
      sources: execution.sources
    }, normalizedRequest.target);
    const answer = await this.finalResponseService.createFinalAnswer(normalizedRequest, actionProposal.action, execution);

    await this.conversationStore.appendMessage(
      normalizedRequest.tenantId,
      normalizedRequest.target,
      normalizedRequest.conversationId,
      normalizedRequest.channel,
      normalizedRequest.userId,
      "assistant",
      answer
    );

    return {
      status: "completed",
      answer,
      requestId: normalizedRequest.requestId,
      actionProposal,
      result: execution
    };
  }

  async approveAndExecute(approvalId: string, approverId: string): Promise<AssistantResponse> {
    const approved = await this.approvalService.markApproved(approvalId, approverId);
    const request = this.rehydrateRequest(approved);
    const validation = await this.actionPolicyEngine.validateProposal(request, approved.actionProposal);
    const execution = await this.actionExecutionService.execute(
      validation.definition,
      validation.parsedArguments,
      request
    );
    await this.approvalService.markExecuted(approvalId, execution);
    await this.activityLogService.info("approval.executed", "Executed approved assistant action.", {
      approvalId,
      requestId: request.requestId,
      action: approved.actionProposal.action,
      sources: execution.sources
    }, approved.target);

    const answer = await this.finalResponseService.createFinalAnswer(
      {
        ...request,
        message: approved.actionProposal.summary
      },
      approved.actionProposal.action,
      execution
    );

    await this.conversationStore.appendMessage(
      approved.tenantId,
      approved.target,
      approved.conversationId,
      approved.requestContext.channel,
      approved.requestedBy,
      "assistant",
      answer
    );

    return {
      status: "completed",
      answer,
      requestId: request.requestId,
      actionProposal: approved.actionProposal,
      result: execution
    };
  }

  async rejectApproval(approvalId: string, approverId: string): Promise<ApprovalRecord> {
    const approval = await this.approvalService.markRejected(approvalId, approverId);
    await this.activityLogService.warn("approval.rejected", "Rejected pending assistant action.", {
      approvalId,
      action: approval.actionProposal.action
    }, approval.target);
    await this.conversationStore.appendMessage(
      approval.tenantId,
      approval.target,
      approval.conversationId,
      approval.requestContext.channel,
      approval.requestedBy,
      "assistant",
      "The requested action was rejected during manual review."
    );
    return approval;
  }

  private rehydrateRequest(approval: ApprovalRecord): AssistantRequest {
    return {
      ...approval.requestContext,
      message: approval.actionProposal.summary
    };
  }
}

import fs from "node:fs/promises";
import path from "node:path";

import { ApprovalRecord, AssistantRequest } from "@vlv-ai/shared";

import { paths } from "../config/paths";
import { createId } from "../shared/ids";
import { NotFoundError, ValidationError } from "../shared/errors";
import { readJsonFile, writeJsonFile } from "../shared/json-file";
import { nowIso } from "../shared/time";

export class ApprovalService {
  async createPendingApproval(
    request: AssistantRequest,
    actionProposal: ApprovalRecord["actionProposal"],
    executionPreview: ApprovalRecord["executionPreview"]
  ): Promise<ApprovalRecord> {
    const approval: ApprovalRecord = {
      id: createId("approval"),
      tenantId: request.tenantId,
      target: request.target,
      conversationId: request.conversationId,
      status: "pending",
      requestContext: {
        tenantId: request.tenantId,
        target: request.target,
        companyCode: request.companyCode,
        conversationId: request.conversationId,
        userId: request.userId,
        actorUserId: request.actorUserId,
        propertyCode: request.propertyCode,
        locale: request.locale,
        channel: request.channel,
        roles: request.roles,
        permissions: request.permissions
      },
      requestedBy: request.userId,
      requestedAt: nowIso(),
      procedureName: executionPreview.procedureName,
      actionProposal,
      executionPreview
    };

    await writeJsonFile(this.getFilePath(approval.id), approval);
    return approval;
  }

  async listApprovals(): Promise<ApprovalRecord[]> {
    try {
      const entries = await fs.readdir(paths.approvalsDirectory);
      const records = await Promise.all(
        entries
          .filter((entry) => entry.endsWith(".json"))
          .map((entry) =>
            readJsonFile<ApprovalRecord | null>(path.join(paths.approvalsDirectory, entry), null)
          )
      );

      return records
        .filter(Boolean)
        .map((record) => this.normalizeApproval(record!))
        .sort((a: ApprovalRecord | null, b: ApprovalRecord | null) => {
          return b!.requestedAt.localeCompare(a!.requestedAt);
        }) as ApprovalRecord[];
    } catch {
      return [];
    }
  }

  async getApproval(approvalId: string): Promise<ApprovalRecord> {
    const approval = await readJsonFile<ApprovalRecord | null>(this.getFilePath(approvalId), null);

    if (!approval) {
      throw new NotFoundError(`Approval ${approvalId} was not found.`);
    }

    return this.normalizeApproval(approval);
  }

  async markApproved(approvalId: string, approverId: string): Promise<ApprovalRecord> {
    const approval = await this.getApproval(approvalId);

    if (approval.status !== "pending") {
      throw new ValidationError("Only pending approvals can be approved.", {
        approvalId,
        status: approval.status
      });
    }

    const updated: ApprovalRecord = {
      ...approval,
      status: "approved",
      decidedBy: approverId,
      decidedAt: nowIso()
    };

    await writeJsonFile(this.getFilePath(approvalId), updated);
    return updated;
  }

  async markRejected(approvalId: string, approverId: string): Promise<ApprovalRecord> {
    const approval = await this.getApproval(approvalId);

    if (approval.status !== "pending") {
      throw new ValidationError("Only pending approvals can be rejected.", {
        approvalId,
        status: approval.status
      });
    }

    const updated: ApprovalRecord = {
      ...approval,
      status: "rejected",
      decidedBy: approverId,
      decidedAt: nowIso()
    };

    await writeJsonFile(this.getFilePath(approvalId), updated);
    return updated;
  }

  async markExecuted(approvalId: string, result: unknown): Promise<ApprovalRecord> {
    const approval = await this.getApproval(approvalId);
    const updated: ApprovalRecord = {
      ...approval,
      status: "executed",
      result
    };

    await writeJsonFile(this.getFilePath(approvalId), updated);
    return updated;
  }

  private getFilePath(approvalId: string): string {
    return path.join(paths.approvalsDirectory, `${approvalId}.json`);
  }

  private normalizeApproval(approval: ApprovalRecord): ApprovalRecord {
    return {
      ...approval,
      target: approval.target ?? approval.requestContext.target ?? "pms",
      requestContext: {
        ...approval.requestContext,
        target: approval.requestContext.target ?? approval.target ?? "pms"
      },
      executionPreview: {
        ...approval.executionPreview,
        target: approval.executionPreview.target ?? approval.target ?? "pms"
      }
    };
  }
}

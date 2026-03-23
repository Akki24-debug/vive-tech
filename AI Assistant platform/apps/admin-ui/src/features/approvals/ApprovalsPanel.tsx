import { ApprovalRecord, AssistantTarget } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface ApprovalsPanelProps {
  approvals: ApprovalRecord[];
  selectedTarget: AssistantTarget;
  approverId: string;
  onApproverIdChange: (value: string) => void;
  onApprove: (approvalId: string) => Promise<void>;
  onReject: (approvalId: string) => Promise<void>;
}

export function ApprovalsPanel({
  approvals,
  selectedTarget,
  approverId,
  onApproverIdChange,
  onApprove,
  onReject
}: ApprovalsPanelProps) {
  return (
    <SectionCard
      title="Manual Approval Queue"
      subtitle={`Pending actions for ${selectedTarget}`}
      actions={
        <input
          className="input"
          placeholder="Approver ID"
          value={approverId}
          onChange={(event) => onApproverIdChange(event.target.value)}
        />
      }
    >
      <div className="table-list">
        {approvals.map((approval) => (
          <article className="table-list__row table-list__row--stacked" key={approval.id}>
            <div className="approval-row">
              <div>
                <div className="inline-row">
                  <h3>{approval.actionProposal.action}</h3>
                  <StatusBadge
                    tone={
                      approval.status === "pending"
                        ? "warning"
                        : approval.status === "rejected"
                          ? "danger"
                          : "success"
                    }
                    label={approval.status}
                  />
                </div>
                <p>{approval.actionProposal.summary}</p>
                <small>
                  {approval.target} • {approval.procedureName ?? "No procedure"} • {approval.requestedAt}
                </small>
              </div>
              {approval.status === "pending" ? (
                <div className="button-row">
                  <button
                    className="button button--secondary"
                    onClick={() => onReject(approval.id)}
                    type="button"
                  >
                    Reject
                  </button>
                  <button className="button" onClick={() => onApprove(approval.id)} type="button">
                    Approve
                  </button>
                </div>
              ) : null}
            </div>
            <pre className="code-block">{JSON.stringify(approval.executionPreview, null, 2)}</pre>
          </article>
        ))}
        {approvals.length === 0 ? <p>No approvals have been recorded.</p> : null}
      </div>
    </SectionCard>
  );
}

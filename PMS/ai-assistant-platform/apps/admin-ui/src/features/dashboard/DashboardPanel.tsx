import {
  ActionCatalogEntry,
  ApprovalRecord,
  DocumentDescriptor,
  LogEvent,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface DashboardPanelProps {
  configured: boolean;
  config: SanitizedRuntimeConfig | null;
  documents: DocumentDescriptor[];
  approvals: ApprovalRecord[];
  logs: LogEvent[];
  actions: ActionCatalogEntry[];
}

export function DashboardPanel({
  configured,
  config,
  documents,
  approvals,
  logs,
  actions
}: DashboardPanelProps) {
  const existingDocuments = documents.filter((document) => document.exists).length;
  const pendingApprovals = approvals.filter((approval) => approval.status === "pending").length;

  return (
    <div className="panel-grid">
      <SectionCard title="Platform Status" subtitle="Quick health and readiness summary">
        <div className="stats-grid">
          <div className="stat-card">
            <span>Configuration</span>
            <strong>{configured ? "Ready" : "Missing"}</strong>
          </div>
          <div className="stat-card">
            <span>Docs Loaded</span>
            <strong>
              {existingDocuments}/{documents.length}
            </strong>
          </div>
          <div className="stat-card">
            <span>Pending Approvals</span>
            <strong>{pendingApprovals}</strong>
          </div>
          <div className="stat-card">
            <span>Registered Actions</span>
            <strong>{actions.length}</strong>
          </div>
        </div>
      </SectionCard>

      <SectionCard title="Execution Mode" subtitle="How the backend handles validated actions">
        {config ? (
          <div className="stack-list">
            <div className="inline-row">
              <span>Mode</span>
              <StatusBadge
                tone={config.execution.mode === "auto" ? "success" : "warning"}
                label={config.execution.mode}
              />
            </div>
            <div className="inline-row">
              <span>Writes</span>
              <StatusBadge
                tone={config.execution.enableWrites ? "success" : "danger"}
                label={config.execution.enableWrites ? "enabled" : "disabled"}
              />
            </div>
            <div className="inline-row">
              <span>Tenant</span>
              <strong>{config.tenantId}</strong>
            </div>
          </div>
        ) : (
          <p>No runtime configuration has been saved yet.</p>
        )}
      </SectionCard>

      <SectionCard title="Latest Activity" subtitle="Recent operational log entries">
        <div className="stack-list">
          {logs.slice(0, 6).map((event) => (
            <div className="log-snippet" key={event.id}>
              <div className="inline-row">
                <StatusBadge
                  tone={event.level === "error" ? "danger" : event.level === "warn" ? "warning" : "neutral"}
                  label={event.level}
                />
                <span>{event.type}</span>
              </div>
              <p>{event.message}</p>
            </div>
          ))}
          {logs.length === 0 ? <p>No logs recorded yet.</p> : null}
        </div>
      </SectionCard>
    </div>
  );
}

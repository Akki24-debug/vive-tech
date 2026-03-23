import { AssistantTarget, LogEvent } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface LogsPanelProps {
  logs: LogEvent[];
  selectedTarget: AssistantTarget;
}

export function LogsPanel({ logs, selectedTarget }: LogsPanelProps) {
  return (
    <SectionCard
      title="Operational Logs"
      subtitle={`Recent structured events captured for ${selectedTarget}`}
    >
      <div className="table-list">
        {logs.map((event) => (
          <article className="table-list__row table-list__row--stacked" key={event.id}>
            <div className="inline-row">
              <StatusBadge
                tone={event.level === "error" ? "danger" : event.level === "warn" ? "warning" : "neutral"}
                label={event.level}
              />
              <strong>{event.type}</strong>
              <span>{event.target ?? "shared"}</span>
              <span>{event.timestamp}</span>
            </div>
            <p>{event.message}</p>
            {event.payload ? <pre className="code-block">{JSON.stringify(event.payload, null, 2)}</pre> : null}
          </article>
        ))}
        {logs.length === 0 ? <p>No logs are available yet.</p> : null}
      </div>
    </SectionCard>
  );
}

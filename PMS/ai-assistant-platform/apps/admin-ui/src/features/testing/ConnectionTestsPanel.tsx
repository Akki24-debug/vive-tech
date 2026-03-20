import { ConnectionTestResult } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface ConnectionTestsPanelProps {
  results: Partial<Record<"database" | "openai" | "whatsapp", ConnectionTestResult>>;
}

export function ConnectionTestsPanel({ results }: ConnectionTestsPanelProps) {
  return (
    <SectionCard title="Connection Tests" subtitle="Latest verification results from the setup panel">
      <div className="stats-grid">
        {(["database", "openai", "whatsapp"] as const).map((target) => {
          const result = results[target];
          return (
            <div className="stat-card" key={target}>
              <span>{target}</span>
              {result ? (
                <>
                  <StatusBadge tone={result.success ? "success" : "danger"} label={result.success ? "ok" : "failed"} />
                  <p>{result.details}</p>
                </>
              ) : (
                <p>No test executed yet.</p>
              )}
            </div>
          );
        })}
      </div>
    </SectionCard>
  );
}

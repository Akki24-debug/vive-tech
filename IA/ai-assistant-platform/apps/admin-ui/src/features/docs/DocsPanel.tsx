import { AssistantTarget, DocumentDescriptor } from "@vlv-ai/shared";

import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";

interface DocsPanelProps {
  documents: DocumentDescriptor[];
  selectedTarget: AssistantTarget;
}

export function DocsPanel({ documents, selectedTarget }: DocsPanelProps) {
  return (
    <SectionCard
      title="Documentation Context"
      subtitle={`Markdown files loaded automatically into the ${selectedTarget} prompt bundle`}
    >
      <div className="table-list">
        {documents.map((document) => (
          <article className="table-list__row" key={document.key}>
            <div>
              <h3>{document.title}</h3>
              <p>{document.path}</p>
              <small>{document.target}</small>
            </div>
            <div className="table-list__meta">
              <StatusBadge
                tone={document.exists ? "success" : "danger"}
                label={document.exists ? "available" : "missing"}
              />
              <span>{document.size} bytes</span>
            </div>
          </article>
        ))}
      </div>
    </SectionCard>
  );
}

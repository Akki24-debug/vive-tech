import { PropsWithChildren } from "react";

interface AppShellProps extends PropsWithChildren {
  activeView: string;
  onViewChange: (view: string) => void;
}

const views = [
  ["chat", "Test Chat"],
  ["dashboard", "Dashboard"],
  ["setup", "Setup"],
  ["docs", "Docs"],
  ["approvals", "Approvals"],
  ["logs", "Logs"]
] as const;

export function AppShell({ activeView, onViewChange, children }: AppShellProps) {
  return (
    <div className="app-shell">
      <aside className="app-shell__sidebar">
        <div className="brand-block">
          <p className="brand-block__eyebrow">AI Orchestration Layer</p>
          <h1>VLV Assistant Console</h1>
          <p>Stored-procedure gateway, docs-driven prompts, approvals, dual-target routing, and channel operations.</p>
          <div className="brand-block__chips">
            <span>dual-target</span>
            <span>sp-gateway</span>
            <span>ops-console</span>
          </div>
        </div>
        <nav className="nav-list">
          {views.map(([key, label]) => (
            <button
              key={key}
              className={key === activeView ? "nav-list__item nav-list__item--active" : "nav-list__item"}
              onClick={() => onViewChange(key)}
              type="button"
            >
              {label}
            </button>
          ))}
        </nav>
      </aside>
      <main className="app-shell__main">{children}</main>
    </div>
  );
}

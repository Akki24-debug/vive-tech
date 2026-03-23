interface StatusBadgeProps {
  tone: "success" | "warning" | "danger" | "neutral";
  label: string;
}

export function StatusBadge({ tone, label }: StatusBadgeProps) {
  return <span className={`status-badge status-badge--${tone}`}>{label}</span>;
}

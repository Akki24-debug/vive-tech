export function formatDateLabel(value: string | null): string {
  if (!value) {
    return "Sin fecha";
  }

  const date = new Date(`${value}T00:00:00`);
  return new Intl.DateTimeFormat("es-MX", {
    weekday: "short",
    day: "numeric",
    month: "short"
  }).format(date);
}

export function formatFullDate(value: string): string {
  const date = new Date(`${value}T00:00:00`);
  return new Intl.DateTimeFormat("es-MX", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  }).format(date);
}

export function addNights(date: string, nights: number): string {
  const next = new Date(`${date}T00:00:00`);
  next.setDate(next.getDate() + nights);
  return next.toISOString().slice(0, 10);
}

export function diffNights(start: string, end: string): number {
  const startDate = new Date(`${start}T00:00:00`);
  const endDate = new Date(`${end}T00:00:00`);
  return Math.round((endDate.getTime() - startDate.getTime()) / 86_400_000);
}

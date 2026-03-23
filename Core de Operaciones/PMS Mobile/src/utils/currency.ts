export function formatCurrency(value: number | null, currency: string) {
  if (typeof value !== "number") {
    return "No disponible";
  }

  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency,
    maximumFractionDigits: 0
  }).format(value / 100);
}

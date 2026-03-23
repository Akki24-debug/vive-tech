import {
  ScrollView,
  StyleSheet,
  Text,
  View
} from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { useReservationPreview } from "../providers/reservation-preview-provider";
import { colors, radii, spacing } from "../theme/tokens";
import { formatCurrency } from "../utils/currency";
import { formatFullDate } from "../utils/dates";

export function PreviewScreen() {
  const { draft } = useReservationPreview();

  if (!draft) {
    return (
      <SafeAreaView style={styles.safeArea} edges={["bottom", "left", "right"]}>
        <View style={styles.empty}>
          <Text style={styles.emptyTitle}>Sin payload seleccionado</Text>
          <Text style={styles.emptyText}>
            Regresa a disponibilidad y elige una accion de reservacion.
          </Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea} edges={["bottom", "left", "right"]}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          <Text style={styles.eyebrow}>Preview de payload</Text>
          <Text style={styles.title}>{draft.roomName}</Text>
          <Text style={styles.subtitle}>{draft.propertyName}</Text>
        </View>

        <View style={styles.summaryCard}>
          <SummaryRow label="Propiedad" value={`${draft.propertyName} (${draft.propertyCode})`} />
          <SummaryRow label="Habitacion" value={`${draft.roomName} (${draft.roomCode})`} />
          <SummaryRow label="Categoria" value={`${draft.categoryName} (${draft.categoryCode})`} />
          <SummaryRow label="Check in" value={formatFullDate(draft.checkIn)} />
          <SummaryRow label="Check out" value={formatFullDate(draft.checkOut)} />
          <SummaryRow label="Noches" value={String(draft.nights)} />
          <SummaryRow label="Personas" value={draft.people === null ? "Todas" : String(draft.people)} />
          <SummaryRow label="Total" value={formatCurrency(draft.totalCents, draft.currency)} />
        </View>

        <View style={styles.payloadCard}>
          <Text style={styles.payloadTitle}>Payload que se enviaria despues</Text>
          <Text style={styles.codeBlock}>{JSON.stringify(draft, null, 2)}</Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.summaryRow}>
      <Text style={styles.summaryLabel}>{label}</Text>
      <Text style={styles.summaryValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.canvas
  },
  content: {
    padding: spacing.lg,
    gap: spacing.lg
  },
  hero: {
    gap: spacing.xs
  },
  eyebrow: {
    color: colors.accentDeep,
    textTransform: "uppercase",
    letterSpacing: 1.2,
    fontWeight: "700"
  },
  title: {
    fontSize: 30,
    fontWeight: "800",
    color: colors.ink
  },
  subtitle: {
    color: colors.inkSoft,
    fontSize: 16
  },
  summaryCard: {
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.surface,
    padding: spacing.lg,
    gap: spacing.md
  },
  summaryRow: {
    gap: spacing.xs
  },
  summaryLabel: {
    color: colors.inkSoft,
    textTransform: "uppercase",
    fontSize: 12,
    letterSpacing: 1.1
  },
  summaryValue: {
    color: colors.ink,
    fontSize: 16,
    fontWeight: "700"
  },
  payloadCard: {
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    padding: spacing.lg,
    gap: spacing.md
  },
  payloadTitle: {
    color: colors.ink,
    fontSize: 18,
    fontWeight: "800"
  },
  codeBlock: {
    fontFamily: "monospace",
    color: colors.ink,
    lineHeight: 21
  },
  empty: {
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    padding: spacing.xl,
    gap: spacing.sm
  },
  emptyTitle: {
    color: colors.ink,
    fontSize: 24,
    fontWeight: "800"
  },
  emptyText: {
    color: colors.inkSoft,
    textAlign: "center",
    lineHeight: 22
  }
});

import {
  Pressable,
  StyleSheet,
  Text,
  View
} from "react-native";

import { AvailabilityRoomCard, ReservationDraftPreview } from "@vlv-ai/shared";

import { colors, radii, spacing } from "../theme/tokens";
import { formatCurrency } from "../utils/currency";

interface RoomCardProps {
  card: AvailabilityRoomCard;
  onReserve: (draft: ReservationDraftPreview) => void;
}

export function RoomCard({ card, onReserve }: RoomCardProps) {
  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text style={styles.roomName}>{card.roomName}</Text>
          <Text style={styles.roomMeta}>
            {card.categoryName} · {card.roomCode}
          </Text>
        </View>
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{card.visibleContinuousNights} noches</Text>
        </View>
      </View>

      <View style={styles.pricingRow}>
        <View style={styles.pricingBlock}>
          <Text style={styles.pricingLabel}>1 noche</Text>
          <Text style={styles.pricingValue}>
            {formatCurrency(card.nightlyPriceCents, card.currency)}
          </Text>
        </View>
        <View style={styles.pricingBlock}>
          <Text style={styles.pricingLabel}>
            {card.requestedNights !== null ? "Estancia solicitada" : "Continuidad visible"}
          </Text>
          <Text style={styles.pricingValue}>
            {formatCurrency(
              card.requestedNights !== null
                ? card.requestedStayTotalCents
                : card.continuousStayTotalCents,
              card.currency
            )}
          </Text>
        </View>
      </View>

      <Text style={styles.capacityText}>
        Pax: {card.people ?? "Todas"} · Capacidad: {card.capacityTotal ?? "N/D"}
      </Text>

      <View style={styles.actions}>
        {card.actions.map((action) => (
          <Pressable
            key={`${card.roomCode}-${action.kind}`}
            style={[
              styles.button,
              action.kind === "book_continuous_stay" ? styles.buttonSecondary : styles.buttonPrimary
            ]}
            onPress={() => onReserve(action.draft)}
          >
            <Text
              style={[
                styles.buttonText,
                action.kind === "book_continuous_stay" ? styles.buttonTextSecondary : null
              ]}
            >
              {action.label}
            </Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    padding: spacing.lg,
    gap: spacing.md
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    gap: spacing.md
  },
  headerText: {
    flex: 1,
    gap: spacing.xs
  },
  roomName: {
    fontSize: 20,
    fontWeight: "800",
    color: colors.ink
  },
  roomMeta: {
    fontSize: 14,
    color: colors.inkSoft
  },
  badge: {
    borderRadius: 999,
    backgroundColor: colors.surfaceStrong,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs
  },
  badgeText: {
    color: colors.accentDeep,
    fontWeight: "700"
  },
  pricingRow: {
    flexDirection: "row",
    gap: spacing.sm
  },
  pricingBlock: {
    flex: 1,
    borderRadius: radii.md,
    backgroundColor: colors.surface,
    padding: spacing.md,
    gap: spacing.xs
  },
  pricingLabel: {
    fontSize: 13,
    color: colors.inkSoft,
    textTransform: "uppercase"
  },
  pricingValue: {
    fontSize: 18,
    fontWeight: "800",
    color: colors.ink
  },
  capacityText: {
    color: colors.inkSoft,
    fontSize: 13
  },
  actions: {
    gap: spacing.sm
  },
  button: {
    borderRadius: radii.md,
    paddingVertical: spacing.md,
    alignItems: "center",
    justifyContent: "center"
  },
  buttonPrimary: {
    backgroundColor: colors.accentDeep
  },
  buttonSecondary: {
    backgroundColor: colors.accentSoft,
    borderWidth: 1,
    borderColor: colors.accentDeep
  },
  buttonText: {
    color: "#ffffff",
    fontWeight: "800"
  },
  buttonTextSecondary: {
    color: colors.accentDeep
  }
});

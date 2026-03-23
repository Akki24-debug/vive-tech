import { useEffect, useState } from "react";
import {
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View
} from "react-native";
import { Calendar } from "react-native-calendars";

import { colors, radii, spacing } from "../theme/tokens";

interface DateRangeModalProps {
  visible: boolean;
  initialStartDate: string;
  initialEndDate: string | null;
  onClose: () => void;
  onApply: (payload: { startDate: string; endDate: string | null }) => void;
}

export function DateRangeModal({
  visible,
  initialStartDate,
  initialEndDate,
  onClose,
  onApply
}: DateRangeModalProps) {
  const [startDate, setStartDate] = useState(initialStartDate);
  const [endDate, setEndDate] = useState<string | null>(initialEndDate);

  useEffect(() => {
    if (!visible) {
      return;
    }
    setStartDate(initialStartDate);
    setEndDate(initialEndDate);
  }, [initialEndDate, initialStartDate, visible]);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>Rango de fechas</Text>
            <Pressable onPress={onClose}>
              <Text style={styles.close}>Cerrar</Text>
            </Pressable>
          </View>
          <Calendar
            current={startDate}
            onDayPress={(day) => {
              if (!startDate || endDate) {
                setStartDate(day.dateString);
                setEndDate(null);
                return;
              }
              if (day.dateString <= startDate) {
                setStartDate(day.dateString);
                setEndDate(null);
                return;
              }
              setEndDate(day.dateString);
            }}
            markedDates={buildMarkedDates(startDate, endDate)}
            markingType="period"
            theme={{
              todayTextColor: colors.accentDeep,
              arrowColor: colors.accentDeep
            }}
          />
          <View style={styles.actions}>
            <Pressable
              style={[styles.button, styles.buttonGhost]}
              onPress={() => {
                onApply({ startDate, endDate: null });
                onClose();
              }}
            >
              <Text style={styles.buttonGhostText}>Solo fecha inicio</Text>
            </Pressable>
            <Pressable
              style={[styles.button, styles.buttonSolid]}
              onPress={() => {
                onApply({ startDate, endDate });
                onClose();
              }}
            >
              <Text style={styles.buttonSolidText}>Aplicar</Text>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

function buildMarkedDates(startDate: string, endDate: string | null) {
  const marked: Record<string, Record<string, boolean | string>> = {};

  if (!startDate) {
    return marked;
  }

  if (!endDate) {
    marked[startDate] = {
      selected: true,
      startingDay: true,
      endingDay: true,
      color: colors.accentDeep,
      textColor: "#ffffff"
    };
    return marked;
  }

  const cursor = new Date(`${startDate}T00:00:00`);
  const target = new Date(`${endDate}T00:00:00`);
  while (cursor <= target) {
    const dateKey = cursor.toISOString().slice(0, 10);
    marked[dateKey] = {
      selected: true,
      color: colors.accentDeep,
      textColor: "#ffffff",
      startingDay: dateKey === startDate,
      endingDay: dateKey === endDate
    };
    cursor.setDate(cursor.getDate() + 1);
  }

  return marked;
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "rgba(23,33,43,0.34)",
    justifyContent: "flex-end"
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.xl
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    paddingHorizontal: spacing.lg,
    marginBottom: spacing.md
  },
  title: {
    fontSize: 22,
    fontWeight: "700",
    color: colors.ink
  },
  close: {
    color: colors.accent,
    fontWeight: "700"
  },
  actions: {
    flexDirection: "row",
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg
  },
  button: {
    flex: 1,
    borderRadius: radii.md,
    paddingVertical: spacing.md,
    alignItems: "center",
    justifyContent: "center"
  },
  buttonGhost: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card
  },
  buttonSolid: {
    backgroundColor: colors.accentDeep
  },
  buttonGhostText: {
    color: colors.ink,
    fontWeight: "700"
  },
  buttonSolidText: {
    color: "#ffffff",
    fontWeight: "700"
  }
});

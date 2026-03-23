import {
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View
} from "react-native";

import { colors, radii, spacing } from "../theme/tokens";

export interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

interface SelectModalProps {
  visible: boolean;
  title: string;
  options: SelectOption[];
  selectedValue: string;
  onClose: () => void;
  onSelect: (value: string) => void;
}

export function SelectModal({
  visible,
  title,
  options,
  selectedValue,
  onClose,
  onSelect
}: SelectModalProps) {
  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>{title}</Text>
            <Pressable onPress={onClose}>
              <Text style={styles.close}>Cerrar</Text>
            </Pressable>
          </View>
          <ScrollView contentContainerStyle={styles.options}>
            {options.map((option) => {
              if (option.disabled) {
                return (
                  <View key={`separator-${option.label}`} style={styles.separator}>
                    <Text style={styles.separatorText}>{option.label}</Text>
                  </View>
                );
              }

              const selected = option.value === selectedValue;
              return (
                <Pressable
                  key={option.value}
                  style={[styles.option, selected ? styles.optionSelected : null]}
                  onPress={() => {
                    onSelect(option.value);
                    onClose();
                  }}
                >
                  <Text style={[styles.optionLabel, selected ? styles.optionLabelSelected : null]}>
                    {option.label}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: "rgba(23,33,43,0.34)",
    justifyContent: "flex-end"
  },
  sheet: {
    maxHeight: "78%",
    backgroundColor: colors.surface,
    borderTopLeftRadius: radii.lg,
    borderTopRightRadius: radii.lg,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.xl
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
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
  options: {
    gap: spacing.sm
  },
  option: {
    borderRadius: radii.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md
  },
  optionSelected: {
    borderColor: colors.accentDeep,
    backgroundColor: colors.accentSoft
  },
  optionLabel: {
    color: colors.ink,
    fontSize: 16
  },
  optionLabelSelected: {
    fontWeight: "700"
  },
  separator: {
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm
  },
  separatorText: {
    fontSize: 12,
    fontWeight: "700",
    letterSpacing: 1.2,
    color: colors.inkSoft,
    textTransform: "uppercase"
  }
});

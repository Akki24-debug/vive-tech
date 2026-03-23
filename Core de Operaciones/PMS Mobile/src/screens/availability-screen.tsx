import { useDeferredValue, useEffect, useState, startTransition } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View
} from "react-native";
import { useRouter } from "expo-router";
import { useQuery } from "@tanstack/react-query";
import { SafeAreaView } from "react-native-safe-area-context";

import {
  AvailabilityFilters,
  MobilePmsBootstrapResponse
} from "@vlv-ai/shared";

import { DateRangeModal } from "../components/date-range-modal";
import { RoomCard } from "../components/room-card";
import { SelectModal, SelectOption } from "../components/select-modal";
import { mobileApi } from "../lib/api";
import { useReservationPreview } from "../providers/reservation-preview-provider";
import { colors, radii, spacing } from "../theme/tokens";
import { diffNights, formatDateLabel } from "../utils/dates";

const EMPTY_FILTERS: AvailabilityFilters = {
  propertyCode: null,
  dateStart: todayYmd(),
  dateEnd: null,
  nights: null,
  people: null,
  visibleWindowDays: 30
};

export function AvailabilityScreen() {
  const router = useRouter();
  const { setDraft } = useReservationPreview();
  const [filters, setFilters] = useState<AvailabilityFilters>(EMPTY_FILTERS);
  const [initialized, setInitialized] = useState(false);
  const [propertyModalOpen, setPropertyModalOpen] = useState(false);
  const [nightsModalOpen, setNightsModalOpen] = useState(false);
  const [peopleModalOpen, setPeopleModalOpen] = useState(false);
  const [dateModalOpen, setDateModalOpen] = useState(false);
  const deferredFilters = useDeferredValue(filters);

  const bootstrapQuery = useQuery({
    queryKey: ["mobile-bootstrap"],
    queryFn: mobileApi.getBootstrap
  });

  useEffect(() => {
    if (!bootstrapQuery.data || initialized) {
      return;
    }

    startTransition(() => {
      setFilters(bootstrapQuery.data.defaults);
      setInitialized(true);
    });
  }, [bootstrapQuery.data, initialized]);

  const availabilityQuery = useQuery({
    queryKey: ["mobile-availability", deferredFilters],
    queryFn: () => mobileApi.searchAvailability(deferredFilters),
    enabled: initialized
  });

  const propertyOptions = buildPropertyOptions(bootstrapQuery.data, filters.propertyCode);

  return (
    <SafeAreaView style={styles.safeArea} edges={["top", "left", "right"]}>
      <ScrollView stickyHeaderIndices={[0]} contentContainerStyle={styles.scrollContent}>
        <View style={styles.filterShell}>
          <View style={styles.filterHeader}>
            <Text style={styles.eyebrow}>Fase 1 · Interno</Text>
            <Text style={styles.title}>Consulta de disponibilidad</Text>
            <Text style={styles.subtitle}>
              Disponibilidad en tiempo real, cotizacion por noche y preview de reservacion.
            </Text>
          </View>

          <View style={styles.filterGrid}>
            <FilterTile
              label="Propiedad"
              value={selectedPropertyLabel(bootstrapQuery.data, filters.propertyCode)}
              onPress={() => setPropertyModalOpen(true)}
            />
            <FilterTile
              label="Fechas"
              value={dateSummary(filters)}
              onPress={() => setDateModalOpen(true)}
            />
            <FilterTile
              label="Noches"
              value={filters.nights === null ? "Vacio" : String(filters.nights)}
              onPress={() => {
                if (!filters.dateEnd) {
                  setNightsModalOpen(true);
                }
              }}
              disabled={Boolean(filters.dateEnd)}
            />
            <FilterTile
              label="Personas"
              value={filters.people === null ? "Todas" : String(filters.people)}
              onPress={() => setPeopleModalOpen(true)}
            />
          </View>
        </View>

        <View style={styles.resultsShell}>
          {bootstrapQuery.isLoading ? (
            <LoadingCard message="Cargando catalogo operativo..." />
          ) : bootstrapQuery.error ? (
            <ErrorCard message={errorMessage(bootstrapQuery.error)} />
          ) : availabilityQuery.isLoading ? (
            <LoadingCard message="Consultando disponibilidad..." />
          ) : availabilityQuery.error ? (
            <ErrorCard message={errorMessage(availabilityQuery.error)} />
          ) : availabilityQuery.data && availabilityQuery.data.groups.length > 0 ? (
            availabilityQuery.data.groups.map((group) => (
              <View key={group.propertyCode} style={styles.group}>
                <View style={styles.groupHeader}>
                  <Text style={styles.groupTitle}>{group.propertyName}</Text>
                  <Text style={styles.groupMeta}>{group.roomCount} opciones</Text>
                </View>
                <View style={styles.groupCards}>
                  {group.rooms.map((card) => (
                    <RoomCard
                      key={`${card.propertyCode}-${card.roomCode}`}
                      card={card}
                      onReserve={(draft) => {
                        setDraft(draft);
                        router.push("/preview");
                      }}
                    />
                  ))}
                </View>
              </View>
            ))
          ) : (
            <EmptyCard />
          )}
        </View>
      </ScrollView>

      <SelectModal
        visible={propertyModalOpen}
        title="Propiedades"
        options={propertyOptions}
        selectedValue={filters.propertyCode ?? "__ALL__"}
        onClose={() => setPropertyModalOpen(false)}
        onSelect={(value) => {
          startTransition(() => {
            setFilters((current) => ({
              ...current,
              propertyCode: value === "__ALL__" ? null : value
            }));
          });
        }}
      />

      <DateRangeModal
        visible={dateModalOpen}
        initialStartDate={filters.dateStart}
        initialEndDate={filters.dateEnd}
        onClose={() => setDateModalOpen(false)}
        onApply={({ startDate, endDate }) => {
          startTransition(() => {
            setFilters((current) => ({
              ...current,
              dateStart: startDate,
              dateEnd: endDate,
              nights: endDate ? diffNights(startDate, endDate) : current.nights
            }));
          });
        }}
      />

      <SelectModal
        visible={nightsModalOpen}
        title="Noches"
        options={buildNumericOptions("Vacio", 30)}
        selectedValue={filters.nights === null ? "__EMPTY__" : String(filters.nights)}
        onClose={() => setNightsModalOpen(false)}
        onSelect={(value) => {
          startTransition(() => {
            setFilters((current) => ({
              ...current,
              nights: value === "__EMPTY__" ? null : Number(value)
            }));
          });
        }}
      />

      <SelectModal
        visible={peopleModalOpen}
        title="Cantidad de personas"
        options={buildNumericOptions("Todas", 10)}
        selectedValue={filters.people === null ? "__EMPTY__" : String(filters.people)}
        onClose={() => setPeopleModalOpen(false)}
        onSelect={(value) => {
          startTransition(() => {
            setFilters((current) => ({
              ...current,
              people: value === "__EMPTY__" ? null : Number(value)
            }));
          });
        }}
      />
    </SafeAreaView>
  );
}

function buildPropertyOptions(
  bootstrap: MobilePmsBootstrapResponse | undefined,
  selectedPropertyCode: string | null
): SelectOption[] {
  if (!bootstrap) {
    return [{ value: "__ALL__", label: "Todas" }];
  }

  const allOption = { value: "__ALL__", label: "Todas" };
  const base = bootstrap.properties.map((property) => ({
    value: property.code,
    label: property.name
  }));

  if (!selectedPropertyCode) {
    return [allOption, ...base];
  }

  const selected = base.find((option) => option.value === selectedPropertyCode);
  const rest = base.filter((option) => option.value !== selectedPropertyCode);

  return [
    allOption,
    ...(selected
      ? [selected, { value: "__SEP__", label: "Resto de propiedades", disabled: true }]
      : []),
    ...rest
  ];
}

function selectedPropertyLabel(
  bootstrap: MobilePmsBootstrapResponse | undefined,
  propertyCode: string | null
) {
  if (!propertyCode || !bootstrap) {
    return "Todas";
  }
  return (
    bootstrap.properties.find((property) => property.code === propertyCode)?.name ?? propertyCode
  );
}

function buildNumericOptions(emptyLabel: string, max: number): SelectOption[] {
  const options: SelectOption[] = [{ value: "__EMPTY__", label: emptyLabel }];
  for (let value = 1; value <= max; value += 1) {
    options.push({ value: String(value), label: String(value) });
  }
  return options;
}

function dateSummary(filters: AvailabilityFilters) {
  if (!filters.dateEnd) {
    return formatDateLabel(filters.dateStart);
  }
  return `${formatDateLabel(filters.dateStart)} - ${formatDateLabel(filters.dateEnd)}`;
}

function FilterTile({
  label,
  value,
  onPress,
  disabled = false
}: {
  label: string;
  value: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  return (
    <Pressable
      style={[styles.filterTile, disabled ? styles.filterTileDisabled : null]}
      onPress={onPress}
      disabled={disabled}
    >
      <Text style={styles.filterLabel}>{label}</Text>
      <Text style={styles.filterValue}>{value}</Text>
    </Pressable>
  );
}

function LoadingCard({ message }: { message: string }) {
  return (
    <View style={styles.feedbackCard}>
      <ActivityIndicator color={colors.accentDeep} />
      <Text style={styles.feedbackText}>{message}</Text>
    </View>
  );
}

function ErrorCard({ message }: { message: string }) {
  return (
    <View style={styles.feedbackCard}>
      <Text style={[styles.feedbackText, { color: colors.danger }]}>{message}</Text>
    </View>
  );
}

function EmptyCard() {
  return (
    <View style={styles.feedbackCard}>
      <Text style={styles.feedbackTitle}>Sin resultados</Text>
      <Text style={styles.feedbackText}>
        No hay habitaciones disponibles para los filtros actuales.
      </Text>
    </View>
  );
}

function errorMessage(error: unknown) {
  return error instanceof Error ? error.message : "No se pudo completar la solicitud.";
}

function todayYmd() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, "0");
  const day = String(now.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.canvas
  },
  scrollContent: {
    paddingBottom: spacing.xl
  },
  filterShell: {
    backgroundColor: colors.canvas,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.lg,
    gap: spacing.lg
  },
  filterHeader: {
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
    fontSize: 15,
    lineHeight: 22
  },
  filterGrid: {
    gap: spacing.sm
  },
  filterTile: {
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.xs
  },
  filterTileDisabled: {
    opacity: 0.64
  },
  filterLabel: {
    color: colors.inkSoft,
    textTransform: "uppercase",
    fontSize: 12,
    letterSpacing: 1.1
  },
  filterValue: {
    color: colors.ink,
    fontSize: 18,
    fontWeight: "700"
  },
  resultsShell: {
    paddingHorizontal: spacing.lg,
    gap: spacing.lg
  },
  group: {
    gap: spacing.md
  },
  groupHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "baseline"
  },
  groupTitle: {
    color: colors.ink,
    fontSize: 24,
    fontWeight: "800"
  },
  groupMeta: {
    color: colors.inkSoft
  },
  groupCards: {
    gap: spacing.md
  },
  feedbackCard: {
    borderRadius: radii.lg,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.surface,
    padding: spacing.xl,
    alignItems: "center",
    justifyContent: "center",
    gap: spacing.sm,
    minHeight: 180
  },
  feedbackTitle: {
    color: colors.ink,
    fontSize: 20,
    fontWeight: "800"
  },
  feedbackText: {
    color: colors.inkSoft,
    textAlign: "center",
    lineHeight: 22
  }
});

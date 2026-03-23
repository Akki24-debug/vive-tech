import {
  BrainAdminBootstrap,
  BrainAdminDetailResponse,
  BrainAdminModuleKey,
  BrainAdminReferenceOption,
  BrainAdminReferenceOptions,
  BrainAdminResourceKey,
  BrainAdminSummary,
  SanitizedRuntimeConfig
} from "@vlv-ai/shared";
import { useEffect, useState, type ReactNode } from "react";

import { api } from "../../api/client";
import { SectionCard } from "../../components/SectionCard";
import { StatusBadge } from "../../components/StatusBadge";
import {
  genericResourceConfigs,
  peopleColumns,
  peopleFilters,
  peopleProfileFields,
  type BrainControlColumnConfig,
  type BrainControlFieldConfig,
  type BrainControlFilterConfig,
  type BrainControlOptionSource,
  type BrainControlResourceConfig
} from "./brain-control-config";

interface BrainControlPanelProps {
  configured: boolean;
  config: SanitizedRuntimeConfig | null;
  selectedTarget: "business_brain" | "pms";
}

interface ResourceViewState {
  query: Record<string, string>;
  page: number;
  pageSize: number;
  sortBy: string;
  sortDir: "asc" | "desc";
  visibleColumns: string[];
  items: Array<Record<string, unknown>>;
  total: number;
  totalPages: number;
  loading: boolean;
}

interface DrawerState {
  resource: BrainAdminResourceKey;
  mode: "view" | "edit" | "create";
  id: number | null;
  open: boolean;
  loading: boolean;
  item: Record<string, unknown> | null;
  related: Record<string, unknown> | null;
  formValues: Record<string, unknown>;
  initialFormValues: Record<string, unknown>;
}

const moduleToResource: Record<
  Exclude<BrainAdminModuleKey, "overview" | "people">,
  BrainAdminResourceKey
> = {
  organization: "organization",
  roles_access: "role",
  business_areas: "business_area",
  business_lines: "business_line",
  priorities: "business_priority",
  objectives: "objective_record",
  external_systems: "external_system",
  knowledge_documents: "knowledge_document"
};

const actorStorageKey = "vlv:brain-control:actor";
const pageSizeOptions = [10, 25, 50, 100];

export function BrainControlPanel({
  configured,
  config,
  selectedTarget
}: BrainControlPanelProps) {
  const [bootstrap, setBootstrap] = useState<BrainAdminBootstrap | null>(null);
  const [summary, setSummary] = useState<BrainAdminSummary | null>(null);
  const [options, setOptions] = useState<BrainAdminReferenceOptions | null>(null);
  const [activeModule, setActiveModule] = useState<BrainAdminModuleKey>("overview");
  const [actorInput, setActorInput] = useState(() => readStoredActorValue());
  const [actorOverride, setActorOverride] = useState<number | null>(() => {
    const stored = readStoredActorValue();
    return stored ? Number(stored) : null;
  });
  const [feedback, setFeedback] = useState("");
  const [resourceView, setResourceView] = useState<ResourceViewState | null>(null);
  const [drawer, setDrawer] = useState<DrawerState | null>(null);
  const [pendingRoleIds, setPendingRoleIds] = useState<number[]>([]);
  const [assignmentDraft, setAssignmentDraft] = useState<Record<string, unknown>>({
    businessAreaId: "",
    responsibilityLevel: "member",
    isPrimary: false,
    startDate: "",
    endDate: ""
  });
  const [capacityDraft, setCapacityDraft] = useState<Record<string, unknown>>({
    id: null,
    weeklyCapacityHours: "",
    maxParallelProjects: "",
    maxParallelTasks: "",
    notes: ""
  });
  const [showColumnEditor, setShowColumnEditor] = useState(false);

  useEffect(() => {
    if (selectedTarget !== "business_brain") {
      return;
    }

    void refreshShellData();
  }, [selectedTarget, actorOverride]);

  useEffect(() => {
    if (selectedTarget !== "business_brain" || activeModule === "overview") {
      return;
    }

    void loadModule(activeModule);
  }, [activeModule, selectedTarget, actorOverride]);

  async function refreshShellData(): Promise<void> {
    try {
      const [nextBootstrap, nextSummary, nextOptions] = await Promise.all([
        api.getBrainAdminBootstrap(actorOverride),
        api.getBrainAdminSummary(actorOverride),
        api.getBrainAdminOptions(actorOverride)
      ]);
      setBootstrap(nextBootstrap);
      setSummary(nextSummary);
      setOptions(nextOptions);
      setFeedback("");
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : "No se pudo cargar Brain Control."
      );
    }
  }

  async function loadModule(moduleKey: BrainAdminModuleKey): Promise<void> {
    const resource = resolveModuleResource(moduleKey);

    if (!resource) {
      return;
    }

    const persisted = readResourceState(resource, moduleKey);
    setResourceView({
      ...persisted,
      loading: true,
      items: [],
      total: 0,
      totalPages: 1
    });

    try {
      const response = await api.getBrainAdminResource(
        resource,
        {
          ...persisted.query,
          page: persisted.page,
          pageSize: persisted.pageSize,
          sortBy: persisted.sortBy,
          sortDir: persisted.sortDir
        },
        actorOverride
      );

      const nextState: ResourceViewState = {
        query: persisted.query,
        page: response.page,
        pageSize: response.pageSize,
        sortBy: response.sortBy ?? persisted.sortBy,
        sortDir: response.sortDir,
        visibleColumns: persisted.visibleColumns,
        items: response.items,
        total: response.total,
        totalPages: response.totalPages,
        loading: false
      };

      persistResourceState(resource, moduleKey, nextState);
      setResourceView(nextState);
      setFeedback("");
    } catch (caughtError) {
      setResourceView((current) =>
        current
          ? {
              ...current,
              loading: false
            }
          : null
      );
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : `No se pudo cargar ${moduleKey}.`
      );
    }
  }

  function applyActorOverride(): void {
    const normalized = actorInput.trim();

    if (!normalized) {
      window.localStorage.removeItem(actorStorageKey);
      setActorOverride(null);
      setActorInput("");
      setFeedback("");
      return;
    }

    const parsed = Number(normalized);

    if (!Number.isInteger(parsed) || parsed < 0) {
      setFeedback("El actor override debe ser un entero no negativo.");
      return;
    }

    window.localStorage.setItem(actorStorageKey, String(parsed));
    setActorOverride(parsed);
    setFeedback("");
  }

  function resetActorOverride(): void {
    window.localStorage.removeItem(actorStorageKey);
    setActorOverride(null);
    setActorInput("");
    setFeedback("");
  }

  function changeQuery(key: string, value: string): void {
    setResourceView((current) =>
      current
        ? {
            ...current,
            query: {
              ...current.query,
              [key]: value
            },
            page: 1
          }
        : current
    );
  }

  async function submitFilters(): Promise<void> {
    if (activeModule === "overview") {
      return;
    }

    const resource = resolveModuleResource(activeModule);

    if (!resource || !resourceView) {
      return;
    }

    persistResourceState(resource, activeModule, resourceView);
    await loadModule(activeModule);
  }

  function resetFilters(): void {
    const resource = resolveModuleResource(activeModule);

    if (!resource) {
      return;
    }

    const resetState = createDefaultResourceState(resource, activeModule);
    persistResourceState(resource, activeModule, resetState);
    setResourceView(resetState);
    void loadModule(activeModule);
  }

  function changePage(page: number): void {
    const resource = resolveModuleResource(activeModule);

    if (!resource || !resourceView) {
      return;
    }

    const next = {
      ...resourceView,
      page
    };
    setResourceView(next);
    persistResourceState(resource, activeModule, next);
    void loadModule(activeModule);
  }

  function changePageSize(pageSize: number): void {
    const resource = resolveModuleResource(activeModule);

    if (!resource || !resourceView) {
      return;
    }

    const next = {
      ...resourceView,
      page: 1,
      pageSize
    };
    setResourceView(next);
    persistResourceState(resource, activeModule, next);
    void loadModule(activeModule);
  }

  function toggleSort(columnKey: string): void {
    const resource = resolveModuleResource(activeModule);

    if (!resource || !resourceView) {
      return;
    }

    const nextSortDir: "asc" | "desc" =
      resourceView.sortBy === columnKey && resourceView.sortDir === "asc" ? "desc" : "asc";
    const next = {
      ...resourceView,
      sortBy: columnKey,
      sortDir: nextSortDir,
      page: 1
    };
    setResourceView(next);
    persistResourceState(resource, activeModule, next);
    void loadModule(activeModule);
  }

  function toggleVisibleColumn(columnKey: string): void {
    const resource = resolveModuleResource(activeModule);

    if (!resource || !resourceView) {
      return;
    }

    const nextColumns = resourceView.visibleColumns.includes(columnKey)
      ? resourceView.visibleColumns.filter((key) => key !== columnKey)
      : [...resourceView.visibleColumns, columnKey];

    const next = {
      ...resourceView,
      visibleColumns: nextColumns.length ? nextColumns : [columnKey]
    };
    setResourceView(next);
    persistResourceState(resource, activeModule, next);
  }

  async function openDrawer(
    resource: BrainAdminResourceKey,
    mode: "view" | "edit" | "create",
    id?: number | null
  ): Promise<void> {
    const emptyValues = createEmptyFormValues(resource);

    setDrawer({
      resource,
      mode,
      id: id ?? null,
      open: true,
      loading: mode !== "create",
      item: null,
      related: null,
      formValues: emptyValues,
      initialFormValues: emptyValues
    });
    setPendingRoleIds([]);
    setAssignmentDraft({
      businessAreaId: "",
      responsibilityLevel: "member",
      isPrimary: false,
      startDate: "",
      endDate: ""
    });
    setCapacityDraft({
      id: null,
      weeklyCapacityHours: "",
      maxParallelProjects: "",
      maxParallelTasks: "",
      notes: ""
    });

    if (mode === "create" || !id) {
      return;
    }

    try {
      const detail =
        resource === "user_account"
          ? await api.getBrainAdminUserContext(id, actorOverride)
          : await api.getBrainAdminDetail(resource, id, actorOverride);
      hydrateDrawer(detail, mode);
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : "No se pudo abrir el detalle."
      );
      setDrawer((current) =>
        current
          ? {
              ...current,
              loading: false
            }
          : null
      );
    }
  }

  function hydrateDrawer(
    detail: BrainAdminDetailResponse,
    mode: "view" | "edit" | "create"
  ): void {
    const item = detail.item ?? null;
    const related = detail.related ?? null;
    const nextFormValues = mapRecordToFormValues(detail.resource, item);

    setDrawer({
      resource: detail.resource,
      mode,
      id: Number(item?.id ?? 0) || null,
      open: true,
      loading: false,
      item,
      related,
      formValues: nextFormValues,
      initialFormValues: nextFormValues
    });

    if (detail.resource === "user_account") {
      setPendingRoleIds(
        Array.isArray(detail.related?.roles)
          ? (detail.related.roles as Array<Record<string, unknown>>).map((row) =>
              Number(row.role_id)
            )
          : []
      );

      const capacity = (detail.related?.capacityProfile as Record<string, unknown> | null) ?? null;
      setCapacityDraft({
        id: capacity?.id ?? null,
        weeklyCapacityHours: capacity?.weekly_capacity_hours ?? "",
        maxParallelProjects: capacity?.max_parallel_projects ?? "",
        maxParallelTasks: capacity?.max_parallel_tasks ?? "",
        notes: capacity?.notes ?? ""
      });
    }
  }

  function closeDrawer(): void {
    if (drawer && hasUnsavedChanges(drawer) && !window.confirm("Descartar cambios sin guardar?")) {
      return;
    }

    setDrawer(null);
    setShowColumnEditor(false);
  }

  function switchDrawerMode(mode: "view" | "edit" | "create"): void {
    setDrawer((current) => (current ? { ...current, mode } : current));
  }

  function updateDrawerField(key: string, value: unknown): void {
    setDrawer((current) =>
      current
        ? {
            ...current,
            formValues: {
              ...current.formValues,
              [key]: value
            }
          }
        : current
    );
  }

  async function saveDrawer(): Promise<void> {
    if (!drawer) {
      return;
    }

    if (
      !window.confirm(drawer.mode === "create" ? "Crear este registro?" : "Guardar cambios?")
    ) {
      return;
    }

    try {
      const result = await api.saveBrainAdminResource(
        drawer.resource,
        {
          values: drawer.formValues
        },
        drawer.id,
        actorOverride
      );
      setFeedback("Registro guardado.");
      await refreshShellData();
      if (activeModule !== "overview") {
        await loadModule(activeModule);
      }
      hydrateDrawer(result, "edit");
    } catch (caughtError) {
      setFeedback(caughtError instanceof Error ? caughtError.message : "No se pudo guardar.");
    }
  }

  async function publishKnowledgeDocument(): Promise<void> {
    if (!drawer || drawer.resource !== "knowledge_document" || !drawer.id) {
      return;
    }

    if (!window.confirm("Publicar este documento?")) {
      return;
    }

    try {
      const result = await api.publishBrainAdminKnowledgeDocument(drawer.id, actorOverride);
      setFeedback("Documento publicado.");
      await refreshShellData();
      if (activeModule !== "overview") {
        await loadModule(activeModule);
      }
      hydrateDrawer(result, "edit");
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error ? caughtError.message : "No se pudo publicar."
      );
    }
  }

  async function savePeopleRoles(): Promise<void> {
    if (!drawer?.id) {
      return;
    }

    try {
      const result = await api.syncBrainAdminUserRoles(
        drawer.id,
        pendingRoleIds,
        actorOverride
      );
      setFeedback("Roles sincronizados.");
      await refreshShellData();
      await loadModule(activeModule);
      hydrateDrawer(result, "edit");
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : "No se pudieron sincronizar los roles."
      );
    }
  }

  async function saveAreaAssignment(): Promise<void> {
    if (!drawer?.id) {
      return;
    }

    try {
      await api.saveBrainAdminResource(
        "user_area_assignment",
        {
          values: {
            id: null,
            userId: drawer.id,
            businessAreaId: assignmentDraft.businessAreaId,
            responsibilityLevel: assignmentDraft.responsibilityLevel,
            isPrimary: assignmentDraft.isPrimary,
            startDate: assignmentDraft.startDate,
            endDate: assignmentDraft.endDate
          }
        },
        null,
        actorOverride
      );
      setFeedback("Asignacion guardada.");
      const detail = await api.getBrainAdminUserContext(drawer.id, actorOverride);
      hydrateDrawer(detail, "edit");
      await loadModule(activeModule);
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : "No se pudo guardar la asignacion."
      );
    }
  }

  async function deleteAreaAssignment(id: number): Promise<void> {
    if (!drawer?.id) {
      return;
    }

    if (!window.confirm("Eliminar esta asignacion de area?")) {
      return;
    }

    try {
      await api.deleteBrainAdminResource(
        "user_area_assignment",
        id,
        { reason: "Deleted from Brain Control" },
        actorOverride
      );
      setFeedback("Asignacion eliminada.");
      const detail = await api.getBrainAdminUserContext(drawer.id, actorOverride);
      hydrateDrawer(detail, "edit");
      await loadModule(activeModule);
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error
          ? caughtError.message
          : "No se pudo eliminar la asignacion."
      );
    }
  }

  async function saveCapacityProfile(): Promise<void> {
    if (!drawer?.id) {
      return;
    }

    try {
      await api.saveBrainAdminResource(
        "user_capacity_profile",
        {
          values: {
            id: capacityDraft.id,
            userId: drawer.id,
            weeklyCapacityHours: capacityDraft.weeklyCapacityHours,
            maxParallelProjects: capacityDraft.maxParallelProjects,
            maxParallelTasks: capacityDraft.maxParallelTasks,
            notes: capacityDraft.notes
          }
        },
        Number(capacityDraft.id ?? 0) || null,
        actorOverride
      );
      setFeedback("Capacidad guardada.");
      const detail = await api.getBrainAdminUserContext(drawer.id, actorOverride);
      hydrateDrawer(detail, "edit");
      await loadModule(activeModule);
    } catch (caughtError) {
      setFeedback(
        caughtError instanceof Error ? caughtError.message : "No se pudo guardar la capacidad."
      );
    }
  }

  if (selectedTarget !== "business_brain") {
    return (
      <SectionCard title="Brain Control" subtitle="Disponible solo para business_brain">
        <p>
          Este panel manual no esta habilitado para `pms`. Cambia el target a
          `business_brain` para usarlo.
        </p>
      </SectionCard>
    );
  }

  if (!configured || !config) {
    return (
      <SectionCard title="Brain Control" subtitle="Configuracion requerida">
        <p>Guarda la configuracion del runtime antes de usar el panel operativo del Brain.</p>
      </SectionCard>
    );
  }

  const actorContext = bootstrap?.actorContext ?? summary?.actorContext ?? null;
  const activeResource = resolveModuleResource(activeModule);
  const activeResourceConfig = activeResource ? getResourceConfig(activeResource) : null;
  const canWriteActiveResource =
    !!activeResource && !!actorContext?.writableResources.includes(activeResource);
  const currentFilters = activeResourceConfig ? activeResourceConfig.filters : [];
  const currentColumns = activeResourceConfig ? activeResourceConfig.columns : [];
  const drawerResourceConfig = drawer ? getResourceConfig(drawer.resource) : null;

  return (
    <div className="brain-control">
      <SectionCard
        title="Brain Control"
        subtitle="Backoffice manual para el core operativo de Vive la Vibe"
        actions={
          <div className="brain-control__header-actions">
            <input
              className="input brain-control__actor-input"
              placeholder="Actor override"
              value={actorInput}
              onChange={(event) => setActorInput(event.target.value)}
            />
            <button
              className="button button--secondary"
              onClick={applyActorOverride}
              type="button"
            >
              Apply actor
            </button>
            <button className="button button--ghost" onClick={resetActorOverride} type="button">
              Use default
            </button>
            <button
              className="button button--secondary"
              onClick={() => void refreshShellData()}
              type="button"
            >
              Refresh brain
            </button>
          </div>
        }
      >
        <div className="brain-control__status-grid">
          <StatusCard
            label="Organization"
            value={
              bootstrap?.organization?.name
                ? String(bootstrap.organization.name)
                : "Not resolved"
            }
          />
          <StatusCard
            label="Actor"
            value={actorContext ? actorContext.effectiveActorUserId : "..."}
          />
          <StatusCard
            label="Write mode"
            value={
              actorContext ? (
                <StatusBadge
                  tone={toneFromWriteMode(actorContext.writeMode)}
                  label={actorContext.writeMode}
                />
              ) : (
                "..."
              )
            }
          />
          <StatusCard label="Panel state" value={feedback || "Ready"} emphasis={!!feedback} />
        </div>
        {actorContext?.reason ? (
          <p className="brain-control__banner">{actorContext.reason}</p>
        ) : null}
      </SectionCard>

      <div className="brain-control__module-nav">
        {bootstrap?.modules.map((module) => (
          <button
            key={module.key}
            className={
              module.key === activeModule
                ? "brain-control__module-pill brain-control__module-pill--active"
                : "brain-control__module-pill"
            }
            onClick={() => setActiveModule(module.key)}
            type="button"
          >
            <span>{module.title}</span>
            <small>{module.description}</small>
          </button>
        ))}
      </div>

      {activeModule === "overview" ? (
        <div className="brain-control__overview">
          <SectionCard
            title="Overview"
            subtitle="Estado actual del core operativo y accesos rapidos"
          >
            <div className="brain-control__summary-grid">
              <OverviewMetric
                label="Usuarios"
                value={summary?.counts.user_account ?? 0}
                helper="personas registradas"
              />
              <OverviewMetric
                label="Areas"
                value={summary?.counts.business_area ?? 0}
                helper="frentes funcionales"
              />
              <OverviewMetric
                label="Lineas"
                value={summary?.counts.business_line ?? 0}
                helper="lineas de negocio"
              />
              <OverviewMetric
                label="Prioridades"
                value={summary?.counts.business_priority ?? 0}
                helper="focos activos"
              />
              <OverviewMetric
                label="Objetivos"
                value={summary?.counts.objective_record ?? 0}
                helper="objetivos rastreados"
              />
              <OverviewMetric
                label="Documentos"
                value={summary?.counts.knowledge_document ?? 0}
                helper="knowledge docs"
              />
            </div>
          </SectionCard>

          <div className="brain-control__overview-grid">
            <SectionCard
              title="Quick actions"
              subtitle="Entradas directas a modulos operativos"
            >
              <div className="brain-control__quick-actions">
                {bootstrap?.modules
                  .filter((module) => module.key !== "overview")
                  .map((module) => (
                    <button
                      key={module.key}
                      className="brain-control__quick-action"
                      onClick={() => setActiveModule(module.key)}
                      type="button"
                    >
                      <strong>{module.title}</strong>
                      <span>{module.description}</span>
                    </button>
                  ))}
              </div>
            </SectionCard>

            <SectionCard
              title="Recent changes"
              subtitle="Ultimos cambios auditados en el Business Brain"
            >
              <div className="brain-control__recent-list">
                {summary?.recentChanges.length ? (
                  summary.recentChanges.map((change) => (
                    <div
                      key={`${change.id ?? "change"}-${change.created_at ?? ""}`}
                      className="brain-control__recent-item"
                    >
                      <div>
                        <strong>{String(change.entity_type ?? "entity")}</strong>
                        <p>{String(change.change_summary ?? change.action_type ?? "Change")}</p>
                      </div>
                      <div className="brain-control__recent-meta">
                        <span>{String(change.actor_display_name ?? "system")}</span>
                        <small>{formatDateTime(change.created_at)}</small>
                      </div>
                    </div>
                  ))
                ) : (
                  <EmptyState
                    title="Sin cambios recientes"
                    description="Todavia no hay actividad auditada en el rango cargado."
                  />
                )}
              </div>
            </SectionCard>
          </div>
        </div>
      ) : null}

      {activeModule !== "overview" && activeResource && activeResourceConfig && resourceView ? (
        <SectionCard
          title={activeResourceConfig.title}
          subtitle={activeResourceConfig.subtitle}
          actions={
            <div className="brain-control__toolbar-actions">
              <button
                className="button button--secondary"
                onClick={() => void loadModule(activeModule)}
                type="button"
              >
                Reload
              </button>
              <button
                className="button button--secondary"
                onClick={() => setShowColumnEditor((current) => !current)}
                type="button"
              >
                Columns
              </button>
              <button
                className="button"
                disabled={!canWriteActiveResource}
                onClick={() => void openDrawer(activeResource, "create")}
                type="button"
              >
                {activeResourceConfig.createLabel}
              </button>
            </div>
          }
        >
          <div className="brain-control__toolbar">
            <div className="brain-control__toolbar-head">
              <div className="brain-control__toolbar-kpis">
                <span>{resourceView.total} registros</span>
                <span>
                  Pagina {resourceView.page} / {resourceView.totalPages}
                </span>
                <span>
                  Sort {resourceView.sortBy}:{resourceView.sortDir}
                </span>
              </div>
              <label className="brain-control__field brain-control__field--compact">
                <span>Page size</span>
                <select
                  className="input"
                  value={resourceView.pageSize}
                  onChange={(event) => changePageSize(Number(event.target.value))}
                >
                  {pageSizeOptions.map((pageSize) => (
                    <option key={pageSize} value={pageSize}>
                      {pageSize}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="brain-control__filters">
              {currentFilters.map((filter) => (
                <FilterField
                  key={filter.key}
                  filter={filter}
                  value={resourceView.query[filter.key] ?? ""}
                  options={resolveFilterOptions(filter, options, drawer)}
                  onChange={(value) => changeQuery(filter.key, value)}
                />
              ))}
            </div>

            <div className="brain-control__toolbar-actions-row">
              <button
                className="button button--secondary"
                onClick={() => void submitFilters()}
                type="button"
              >
                Apply filters
              </button>
              <button className="button button--ghost" onClick={resetFilters} type="button">
                Reset filters
              </button>
            </div>

            <FilterChipBar filters={currentFilters} query={resourceView.query} options={options} />

            {showColumnEditor ? (
              <div className="brain-control__column-editor">
                {currentColumns.map((column) => (
                  <label key={column.key} className="brain-control__check-row">
                    <input
                      checked={resourceView.visibleColumns.includes(column.key)}
                      onChange={() => toggleVisibleColumn(column.key)}
                      type="checkbox"
                    />
                    <span>{column.label}</span>
                  </label>
                ))}
              </div>
            ) : null}
          </div>

          <div className="brain-control__table-shell">
            <table className="brain-control__table">
              <thead>
                <tr>
                  {currentColumns
                    .filter((column) => resourceView.visibleColumns.includes(column.key))
                    .map((column) => (
                      <th key={column.key}>
                        <button
                          className="brain-control__sort-button"
                          onClick={() => toggleSort(column.key)}
                          type="button"
                        >
                          <span>{column.label}</span>
                          <small>
                            {resourceView.sortBy === column.key
                              ? resourceView.sortDir === "asc"
                                ? "ASC"
                                : "DESC"
                              : "SORT"}
                          </small>
                        </button>
                      </th>
                    ))}
                </tr>
              </thead>
              <tbody>
                {resourceView.loading ? (
                  <tr>
                    <td colSpan={Math.max(1, resourceView.visibleColumns.length)}>
                      <EmptyState
                        title="Cargando registros"
                        description="Consultando stored procedures y construyendo la vista del modulo."
                      />
                    </td>
                  </tr>
                ) : resourceView.items.length ? (
                  resourceView.items.map((item, index) => (
                    <tr
                      key={`${String(item.id ?? index)}-${index}`}
                      className="brain-control__table-row"
                      onClick={() =>
                        void openDrawer(activeResource, "view", Number(item.id ?? 0) || null)
                      }
                    >
                      {currentColumns
                        .filter((column) => resourceView.visibleColumns.includes(column.key))
                        .map((column) => (
                          <td key={`${item.id ?? index}-${column.key}`}>
                            {renderCellValue(column, item[column.key])}
                          </td>
                        ))}
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={Math.max(1, resourceView.visibleColumns.length)}>
                      <EmptyState
                        title="Sin resultados"
                        description="Ajusta filtros o crea el primer registro de este modulo."
                      />
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          <div className="brain-control__pagination">
            <button
              className="button button--secondary"
              disabled={resourceView.page <= 1}
              onClick={() => changePage(resourceView.page - 1)}
              type="button"
            >
              Prev
            </button>
            <span>
              Page {resourceView.page} of {resourceView.totalPages}
            </span>
            <button
              className="button button--secondary"
              disabled={resourceView.page >= resourceView.totalPages}
              onClick={() => changePage(resourceView.page + 1)}
              type="button"
            >
              Next
            </button>
          </div>
        </SectionCard>
      ) : null}

      {drawer?.open ? (
        <div className="brain-control__drawer-backdrop" onClick={closeDrawer} role="presentation">
          <aside
            className="brain-control__drawer"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="brain-control__drawer-header">
              <div>
                <p className="brain-control__drawer-eyebrow">{drawer.resource}</p>
                <h3>{drawerTitle(drawer)}</h3>
                <p>{drawerSubtitle(drawer)}</p>
              </div>
              <div className="brain-control__drawer-actions">
                {drawer.mode === "view" && drawer.id ? (
                  <button
                    className="button button--secondary"
                    onClick={() => switchDrawerMode("edit")}
                    type="button"
                  >
                    Edit
                  </button>
                ) : null}
                {drawer.resource === "knowledge_document" && drawer.id ? (
                  <button
                    className="button button--secondary"
                    onClick={() => void publishKnowledgeDocument()}
                    type="button"
                  >
                    Publish
                  </button>
                ) : null}
                {drawer.mode !== "view" ? (
                  <button className="button" onClick={() => void saveDrawer()} type="button">
                    Save
                  </button>
                ) : null}
                <button className="button button--ghost" onClick={closeDrawer} type="button">
                  Close
                </button>
              </div>
            </div>

            <div className="brain-control__drawer-body">
              {drawer.loading ? (
                <EmptyState
                  title="Cargando detalle"
                  description="Leyendo contexto del registro y relaciones asociadas."
                />
              ) : drawer.resource === "user_account" ? (
                <div className="brain-control__people-drawer">
                  <DrawerSection
                    title="Profile"
                    subtitle="Datos base del usuario y estado operativo."
                  >
                    <div className="brain-control__form-grid">
                      {peopleProfileFields.map((field) => (
                        <FormField
                          key={field.key}
                          field={field}
                          value={drawer.formValues[field.key]}
                          disabled={drawer.mode === "view"}
                          options={resolveFieldOptions(field, options, drawer)}
                          onChange={(value) => updateDrawerField(field.key, value)}
                        />
                      ))}
                    </div>
                  </DrawerSection>

                  <DrawerSection
                    title="Roles"
                    subtitle="Sincronizacion manual del set de roles."
                    actions={
                      drawer.mode !== "view" ? (
                        <button
                          className="button button--secondary"
                          disabled={!canWriteActiveResource}
                          onClick={() => void savePeopleRoles()}
                          type="button"
                        >
                          Sync roles
                        </button>
                      ) : null
                    }
                  >
                    <div className="brain-control__check-grid">
                      {options?.roles.map((role) => (
                        <label key={String(role.value)} className="brain-control__check-card">
                          <input
                            checked={pendingRoleIds.includes(Number(role.value))}
                            disabled={drawer.mode === "view"}
                            onChange={() =>
                              setPendingRoleIds((current) =>
                                current.includes(Number(role.value))
                                  ? current.filter((id) => id !== Number(role.value))
                                  : [...current, Number(role.value)]
                              )
                            }
                            type="checkbox"
                          />
                          <div>
                            <strong>{role.label}</strong>
                            {role.description ? <span>{role.description}</span> : null}
                          </div>
                        </label>
                      ))}
                    </div>
                  </DrawerSection>

                  <DrawerSection
                    title="Area assignments"
                    subtitle="Asignaciones funcionales y ownership por area."
                  >
                    <div className="brain-control__inline-table">
                      <div className="brain-control__inline-table-head">
                        <span>Area</span>
                        <span>Responsabilidad</span>
                        <span>Primary</span>
                        <span>Fechas</span>
                        <span>Accion</span>
                      </div>
                      {Array.isArray(drawer.related?.areaAssignments) &&
                      drawer.related.areaAssignments.length ? (
                        (drawer.related.areaAssignments as Array<Record<string, unknown>>).map(
                          (assignment) => (
                            <div
                              key={String(assignment.id ?? assignment.business_area_id)}
                              className="brain-control__inline-table-row"
                            >
                              <span>{String(assignment.business_area_name ?? "-")}</span>
                              <span>{String(assignment.responsibility_level ?? "-")}</span>
                              <span>
                                {assignment.is_primary ? (
                                  <StatusBadge tone="success" label="primary" />
                                ) : (
                                  <StatusBadge tone="neutral" label="support" />
                                )}
                              </span>
                              <span>
                                {formatDate(assignment.start_date)} - {formatDate(assignment.end_date)}
                              </span>
                              <span>
                                {drawer.mode !== "view" ? (
                                  <button
                                    className="button button--ghost"
                                    onClick={() =>
                                      void deleteAreaAssignment(Number(assignment.id))
                                    }
                                    type="button"
                                  >
                                    Delete
                                  </button>
                                ) : null}
                              </span>
                            </div>
                          )
                        )
                      ) : (
                        <p className="brain-control__muted">Sin asignaciones cargadas.</p>
                      )}
                    </div>

                    {drawer.mode !== "view" ? (
                      <div className="brain-control__draft-grid">
                        <label>
                          <span>Area</span>
                          <select
                            className="input"
                            value={String(assignmentDraft.businessAreaId ?? "")}
                            onChange={(event) =>
                              setAssignmentDraft((current) => ({
                                ...current,
                                businessAreaId: event.target.value
                              }))
                            }
                          >
                            <option value="">Selecciona un area</option>
                            {(options?.businessAreas ?? []).map((area) => (
                              <option key={String(area.value)} value={String(area.value)}>
                                {area.label}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label>
                          <span>Responsabilidad</span>
                          <select
                            className="input"
                            value={String(assignmentDraft.responsibilityLevel ?? "member")}
                            onChange={(event) =>
                              setAssignmentDraft((current) => ({
                                ...current,
                                responsibilityLevel: event.target.value
                              }))
                            }
                          >
                            {resolveOptionSource("responsibilityLevel", options).map((option) => (
                              <option key={String(option.value)} value={String(option.value)}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label>
                          <span>Start</span>
                          <input
                            className="input"
                            type="date"
                            value={String(assignmentDraft.startDate ?? "")}
                            onChange={(event) =>
                              setAssignmentDraft((current) => ({
                                ...current,
                                startDate: event.target.value
                              }))
                            }
                          />
                        </label>
                        <label>
                          <span>End</span>
                          <input
                            className="input"
                            type="date"
                            value={String(assignmentDraft.endDate ?? "")}
                            onChange={(event) =>
                              setAssignmentDraft((current) => ({
                                ...current,
                                endDate: event.target.value
                              }))
                            }
                          />
                        </label>
                        <label className="brain-control__check-row">
                          <input
                            checked={Boolean(assignmentDraft.isPrimary)}
                            onChange={(event) =>
                              setAssignmentDraft((current) => ({
                                ...current,
                                isPrimary: event.target.checked
                              }))
                            }
                            type="checkbox"
                          />
                          <span>Primary assignment</span>
                        </label>
                        <button
                          className="button button--secondary"
                          onClick={() => void saveAreaAssignment()}
                          type="button"
                        >
                          Add assignment
                        </button>
                      </div>
                    ) : null}
                  </DrawerSection>

                  <DrawerSection
                    title="Capacity profile"
                    subtitle="Capacidad semanal y limites de trabajo en paralelo."
                    actions={
                      drawer.mode !== "view" ? (
                        <button
                          className="button button--secondary"
                          onClick={() => void saveCapacityProfile()}
                          type="button"
                        >
                          Save capacity
                        </button>
                      ) : null
                    }
                  >
                    <div className="brain-control__form-grid">
                      <label>
                        <span>Weekly capacity hours</span>
                        <input
                          className="input"
                          disabled={drawer.mode === "view"}
                          type="number"
                          value={String(capacityDraft.weeklyCapacityHours ?? "")}
                          onChange={(event) =>
                            setCapacityDraft((current) => ({
                              ...current,
                              weeklyCapacityHours: event.target.value
                            }))
                          }
                        />
                      </label>
                      <label>
                        <span>Max parallel projects</span>
                        <input
                          className="input"
                          disabled={drawer.mode === "view"}
                          type="number"
                          value={String(capacityDraft.maxParallelProjects ?? "")}
                          onChange={(event) =>
                            setCapacityDraft((current) => ({
                              ...current,
                              maxParallelProjects: event.target.value
                            }))
                          }
                        />
                      </label>
                      <label>
                        <span>Max parallel tasks</span>
                        <input
                          className="input"
                          disabled={drawer.mode === "view"}
                          type="number"
                          value={String(capacityDraft.maxParallelTasks ?? "")}
                          onChange={(event) =>
                            setCapacityDraft((current) => ({
                              ...current,
                              maxParallelTasks: event.target.value
                            }))
                          }
                        />
                      </label>
                      <label className="brain-control__form-field brain-control__form-field--full">
                        <span>Notes</span>
                        <textarea
                          className="input input--multiline"
                          disabled={drawer.mode === "view"}
                          value={String(capacityDraft.notes ?? "")}
                          onChange={(event) =>
                            setCapacityDraft((current) => ({
                              ...current,
                              notes: event.target.value
                            }))
                          }
                        />
                      </label>
                    </div>
                  </DrawerSection>
                </div>
              ) : (
                <div className="brain-control__generic-drawer">
                  <div className="brain-control__record-summary">
                    <div>
                      <span>Record label</span>
                      <strong>
                        {String(
                          drawer.item?.record_label ??
                            drawer.item?.name ??
                            drawer.item?.title ??
                            drawer.item?.display_name ??
                            "New record"
                        )}
                      </strong>
                    </div>
                    {drawer.item?.status ? (
                      <div>
                        <span>Status</span>
                        <StatusBadge
                          tone={toneFromValue(drawer.item.status)}
                          label={String(drawer.item.status)}
                        />
                      </div>
                    ) : null}
                  </div>
                  <div className="brain-control__form-grid">
                    {drawerResourceConfig?.fields.map((field) => (
                      <FormField
                        key={field.key}
                        field={field}
                        value={drawer.formValues[field.key]}
                        disabled={drawer.mode === "view"}
                        hidden={!isFieldVisible(field, drawer.formValues)}
                        options={resolveFieldOptions(field, options, drawer)}
                        onChange={(value) => updateDrawerField(field.key, value)}
                      />
                    ))}
                  </div>
                </div>
              )}
            </div>
          </aside>
        </div>
      ) : null}
    </div>
  );
}

function resolveModuleResource(moduleKey: BrainAdminModuleKey): BrainAdminResourceKey | null {
  if (moduleKey === "overview") {
    return null;
  }

  if (moduleKey === "people") {
    return "user_account";
  }

  return moduleToResource[moduleKey as Exclude<BrainAdminModuleKey, "overview" | "people">];
}

function getResourceConfig(resource: BrainAdminResourceKey): BrainControlResourceConfig {
  if (resource === "user_account") {
    return {
      moduleKey: "people",
      resource: "user_account",
      title: "People",
      subtitle: "Usuarios, roles, asignaciones de area y capacidad.",
      createLabel: "Nuevo usuario",
      defaultVisibleColumns: peopleColumns.map((column) => column.key),
      columns: peopleColumns,
      filters: peopleFilters,
      fields: peopleProfileFields
    };
  }

  return genericResourceConfigs[
    resource as keyof typeof genericResourceConfigs
  ] as BrainControlResourceConfig;
}

function readStoredActorValue(): string {
  return window.localStorage.getItem(actorStorageKey) ?? "";
}

function createDefaultResourceState(
  resource: BrainAdminResourceKey,
  moduleKey: BrainAdminModuleKey
): ResourceViewState {
  const config = getResourceConfig(resource);

  return {
    query: buildDefaultQuery(config.filters),
    page: 1,
    pageSize: 25,
    sortBy: config.defaultVisibleColumns[0] ?? "updated_at",
    sortDir: "asc",
    visibleColumns: config.defaultVisibleColumns,
    items: [],
    total: 0,
    totalPages: 1,
    loading: false
  };
}

function buildDefaultQuery(filters: BrainControlFilterConfig[]): Record<string, string> {
  return Object.fromEntries(filters.map((filter) => [filter.key, filter.defaultValue ?? ""]));
}

function resourceStateStorageKey(
  resource: BrainAdminResourceKey,
  moduleKey: BrainAdminModuleKey
): string {
  return `vlv:brain-control:${moduleKey}:${resource}`;
}

function readResourceState(
  resource: BrainAdminResourceKey,
  moduleKey: BrainAdminModuleKey
): ResourceViewState {
  const fallback = createDefaultResourceState(resource, moduleKey);
  const raw = window.localStorage.getItem(resourceStateStorageKey(resource, moduleKey));

  if (!raw) {
    return fallback;
  }

  try {
    const parsed = JSON.parse(raw) as Partial<ResourceViewState>;
    return {
      ...fallback,
      ...parsed,
      items: [],
      total: 0,
      totalPages: 1,
      loading: false,
      query: {
        ...fallback.query,
        ...(parsed.query ?? {})
      }
    };
  } catch {
    return fallback;
  }
}

function persistResourceState(
  resource: BrainAdminResourceKey,
  moduleKey: BrainAdminModuleKey,
  state: ResourceViewState
): void {
  window.localStorage.setItem(
    resourceStateStorageKey(resource, moduleKey),
    JSON.stringify(state)
  );
}

function mapRecordToFormValues(
  resource: BrainAdminResourceKey,
  item: Record<string, unknown> | null
): Record<string, unknown> {
  if (!item) {
    return createEmptyFormValues(resource);
  }

  switch (resource) {
    case "organization":
      return {
        id: item.id ?? null,
        name: item.name ?? "",
        legalName: item.legal_name ?? "",
        status: item.status ?? "active",
        currentStage: item.current_stage ?? "",
        baseCity: item.base_city ?? "",
        baseState: item.base_state ?? "",
        country: item.country ?? "",
        description: item.description ?? "",
        visionSummary: item.vision_summary ?? "",
        notes: item.notes ?? ""
      };
    case "user_account":
      return {
        id: item.id ?? null,
        firstName: item.first_name ?? "",
        lastName: item.last_name ?? "",
        displayName: item.display_name ?? "",
        email: item.email ?? "",
        phone: item.phone ?? "",
        employmentStatus: item.employment_status ?? "active",
        timezone: item.timezone ?? "America/Mexico_City",
        roleSummary: item.role_summary ?? "",
        isActive: booleanFromValue(item.is_active, true),
        notes: item.notes ?? ""
      };
    case "role":
      return {
        id: item.id ?? null,
        name: item.name ?? "",
        description: item.description ?? ""
      };
    case "business_area":
      return {
        id: item.id ?? null,
        name: item.name ?? "",
        code: item.code ?? "",
        priorityLevel: item.priority_level ?? "",
        responsibleUserId: item.responsible_user_id ?? "",
        isActive: booleanFromValue(item.is_active, true),
        description: item.description ?? ""
      };
    case "business_line":
      return {
        id: item.id ?? null,
        name: item.name ?? "",
        businessAreaId: item.business_area_id ?? "",
        currentStatus: item.current_status ?? "",
        strategicPriority: item.strategic_priority ?? "",
        ownerUserId: item.owner_user_id ?? "",
        isActive: booleanFromValue(item.is_active, true),
        description: item.description ?? "",
        businessModelSummary: item.business_model_summary ?? "",
        monetizationNotes: item.monetization_notes ?? ""
      };
    case "business_priority":
      return {
        id: item.id ?? null,
        title: item.title ?? "",
        status: item.status ?? "",
        scopeType: item.scope_type ?? "organization",
        scopeId: item.scope_id ?? "",
        ownerUserId: item.owner_user_id ?? "",
        priorityOrder: item.priority_order ?? "",
        targetPeriod: item.target_period ?? "",
        description: item.description ?? ""
      };
    case "objective_record":
      return {
        id: item.id ?? null,
        title: item.title ?? "",
        status: item.status ?? "",
        objectiveType: item.objective_type ?? "",
        businessAreaId: item.business_area_id ?? "",
        ownerUserId: item.owner_user_id ?? "",
        completionPercent: item.completion_percent ?? "",
        targetDate: normalizeDateInput(item.target_date),
        description: item.description ?? ""
      };
    case "external_system":
      return {
        id: item.id ?? null,
        name: item.name ?? "",
        systemType: item.system_type ?? "",
        isActive: booleanFromValue(item.is_active, true),
        description: item.description ?? ""
      };
    case "knowledge_document":
      return {
        id: item.id ?? null,
        title: item.title ?? "",
        documentType: item.document_type ?? "",
        status: item.status ?? "",
        storageType: item.storage_type ?? "",
        businessAreaId: item.business_area_id ?? "",
        ownerUserId: item.owner_user_id ?? "",
        versionLabel: item.version_label ?? "",
        externalUrl: item.external_url ?? "",
        summary: item.summary ?? ""
      };
    default:
      return createEmptyFormValues(resource);
  }
}

function createEmptyFormValues(resource: BrainAdminResourceKey): Record<string, unknown> {
  switch (resource) {
    case "organization":
      return {
        name: "",
        legalName: "",
        status: "active",
        currentStage: "",
        baseCity: "",
        baseState: "",
        country: "Mexico",
        description: "",
        visionSummary: "",
        notes: ""
      };
    case "user_account":
      return {
        firstName: "",
        lastName: "",
        displayName: "",
        email: "",
        phone: "",
        employmentStatus: "active",
        timezone: "America/Mexico_City",
        roleSummary: "",
        isActive: true,
        notes: ""
      };
    case "role":
      return {
        name: "",
        description: ""
      };
    case "business_area":
      return {
        name: "",
        code: "",
        priorityLevel: "high",
        responsibleUserId: "",
        isActive: true,
        description: ""
      };
    case "business_line":
      return {
        name: "",
        businessAreaId: "",
        currentStatus: "active",
        strategicPriority: "high",
        ownerUserId: "",
        isActive: true,
        description: "",
        businessModelSummary: "",
        monetizationNotes: ""
      };
    case "business_priority":
      return {
        title: "",
        status: "active",
        scopeType: "organization",
        scopeId: "",
        ownerUserId: "",
        priorityOrder: "",
        targetPeriod: "",
        description: ""
      };
    case "objective_record":
      return {
        title: "",
        status: "active",
        objectiveType: "strategic",
        businessAreaId: "",
        ownerUserId: "",
        completionPercent: "",
        targetDate: "",
        description: ""
      };
    case "external_system":
      return {
        name: "",
        systemType: "pms",
        isActive: true,
        description: ""
      };
    case "knowledge_document":
      return {
        title: "",
        documentType: "strategy",
        status: "draft",
        storageType: "local_path",
        businessAreaId: "",
        ownerUserId: "",
        versionLabel: "",
        externalUrl: "",
        summary: ""
      };
    default:
      return {};
  }
}

function resolveFilterOptions(
  filter: BrainControlFilterConfig,
  options: BrainAdminReferenceOptions | null,
  drawer: DrawerState | null
): BrainAdminReferenceOption[] {
  if (filter.options) {
    return filter.options.map((option) => ({ value: option.value, label: option.label }));
  }

  if (!filter.optionSource) {
    return [];
  }

  if (filter.key === "scopeId" && drawer) {
    const scopeType = String(drawer.formValues.scopeType ?? "organization");

    if (scopeType === "business_line") {
      return options?.businessLines ?? [];
    }

    if (scopeType === "organization") {
      return [];
    }
  }

  return resolveOptionSource(filter.optionSource, options);
}

function resolveFieldOptions(
  field: BrainControlFieldConfig,
  options: BrainAdminReferenceOptions | null,
  drawer: DrawerState | null
): BrainAdminReferenceOption[] {
  if (field.options) {
    return field.options.map((option) => ({ value: option.value, label: option.label }));
  }

  if (!field.optionSource) {
    return [];
  }

  if (field.key === "scopeId" && drawer) {
    const scopeType = String(drawer.formValues.scopeType ?? "organization");

    if (scopeType === "business_line") {
      return options?.businessLines ?? [];
    }

    if (scopeType === "organization") {
      return [];
    }
  }

  return resolveOptionSource(field.optionSource, options);
}

function resolveOptionSource(
  source: BrainControlOptionSource,
  options: BrainAdminReferenceOptions | null
): BrainAdminReferenceOption[] {
  if (!options) {
    return [];
  }

  switch (source) {
    case "users":
      return options.users;
    case "roles":
      return options.roles;
    case "businessAreas":
      return options.businessAreas;
    case "businessLines":
      return options.businessLines;
    case "priorities":
      return options.priorities;
    case "organizationStatus":
      return options.statuses.organizationStatus ?? [];
    case "employmentStatus":
      return options.statuses.employmentStatus ?? [];
    case "priorityLevel":
      return options.statuses.priorityLevel ?? [];
    case "businessLineStatus":
      return options.statuses.businessLineStatus ?? [];
    case "businessPriorityStatus":
      return options.statuses.businessPriorityStatus ?? [];
    case "objectiveStatus":
      return options.statuses.objectiveStatus ?? [];
    case "objectiveType":
      return options.statuses.objectiveType ?? [];
    case "responsibilityLevel":
      return options.statuses.responsibilityLevel ?? [];
    case "systemType":
      return options.statuses.systemType ?? [];
    case "knowledgeDocumentStatus":
      return options.statuses.knowledgeDocumentStatus ?? [];
    case "knowledgeDocumentType":
      return options.statuses.knowledgeDocumentType ?? [];
    case "knowledgeStorageType":
      return options.statuses.knowledgeStorageType ?? [];
    case "scopeType":
      return options.statuses.scopeType ?? [];
    default:
      return [];
  }
}

function isFieldVisible(
  field: BrainControlFieldConfig,
  values: Record<string, unknown>
): boolean {
  if (!field.visibleWhen) {
    return true;
  }

  const current = String(values[field.visibleWhen.field] ?? "");

  if (field.visibleWhen.equals !== undefined) {
    return current === field.visibleWhen.equals;
  }

  if (field.visibleWhen.notEquals !== undefined) {
    return current !== field.visibleWhen.notEquals;
  }

  return true;
}

function hasUnsavedChanges(drawer: DrawerState): boolean {
  if (drawer.mode === "view") {
    return false;
  }

  return JSON.stringify(drawer.formValues) !== JSON.stringify(drawer.initialFormValues);
}

function booleanFromValue(value: unknown, fallback = false): boolean {
  if (typeof value === "boolean") {
    return value;
  }

  if (typeof value === "number") {
    return value > 0;
  }

  if (typeof value === "string") {
    return ["1", "true", "yes", "active"].includes(value.toLowerCase());
  }

  return fallback;
}

function normalizeDateInput(value: unknown): string {
  if (typeof value !== "string" || !value) {
    return "";
  }

  return value.length >= 10 ? value.slice(0, 10) : value;
}

function drawerTitle(drawer: DrawerState): string {
  if (drawer.mode === "create") {
    return "Nuevo registro";
  }

  return String(
    drawer.item?.record_label ??
      drawer.item?.name ??
      drawer.item?.title ??
      drawer.item?.display_name ??
      `Registro ${drawer.id ?? ""}`
  );
}

function drawerSubtitle(drawer: DrawerState): string {
  if (drawer.mode === "create") {
    return "Modo create. Completa el formulario y guarda.";
  }

  if (drawer.mode === "edit") {
    return "Modo edit. Los cambios se auditan mediante stored procedures.";
  }

  return "Modo view. Puedes revisar el contexto completo y pasar a edicion.";
}

function renderCellValue(
  column: BrainControlColumnConfig,
  value: unknown
): ReactNode {
  switch (column.type) {
    case "status":
      return value ? (
        <StatusBadge tone={toneFromValue(value)} label={String(value)} />
      ) : (
        <span className="brain-control__muted">-</span>
      );
    case "boolean":
      return (
        <StatusBadge
          tone={booleanFromValue(value) ? "success" : "neutral"}
          label={booleanFromValue(value) ? "yes" : "no"}
        />
      );
    case "date":
      return <span>{formatDateTime(value)}</span>;
    case "number":
      return (
        <span>{value === null || value === undefined || value === "" ? "-" : String(value)}</span>
      );
    case "array":
      return Array.isArray(value) ? value.join(", ") : "-";
    default:
      return (
        <span>{value === null || value === undefined || value === "" ? "-" : String(value)}</span>
      );
  }
}

function toneFromValue(value: unknown): "success" | "warning" | "danger" | "neutral" {
  const normalized = String(value ?? "").toLowerCase();

  if (
    ["active", "published", "open", "approved", "done", "completed", "high"].includes(
      normalized
    )
  ) {
    return "success";
  }

  if (["draft", "pending", "medium", "review", "planned"].includes(normalized)) {
    return "warning";
  }

  if (["inactive", "archived", "blocked", "error", "cancelled", "low"].includes(normalized)) {
    return "danger";
  }

  return "neutral";
}

function toneFromWriteMode(
  mode: "full" | "bootstrap_only" | "blocked"
): "success" | "warning" | "danger" {
  if (mode === "full") {
    return "success";
  }

  if (mode === "bootstrap_only") {
    return "warning";
  }

  return "danger";
}

function formatDateTime(value: unknown): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(String(value));
  if (Number.isNaN(parsed.getTime())) {
    return String(value);
  }

  return parsed.toLocaleString("es-MX", {
    dateStyle: "short",
    timeStyle: "short"
  });
}

function formatDate(value: unknown): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(String(value));
  if (Number.isNaN(parsed.getTime())) {
    return String(value);
  }

  return parsed.toLocaleDateString("es-MX");
}

function StatusCard({
  label,
  value,
  emphasis = false
}: {
  label: string;
  value: ReactNode;
  emphasis?: boolean;
}) {
  return (
    <div
      className={
        emphasis
          ? "brain-control__status-card brain-control__status-card--emphasis"
          : "brain-control__status-card"
      }
    >
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function OverviewMetric({
  label,
  value,
  helper
}: {
  label: string;
  value: number;
  helper: string;
}) {
  return (
    <div className="brain-control__metric-card">
      <span>{label}</span>
      <strong>{value}</strong>
      <small>{helper}</small>
    </div>
  );
}

function EmptyState({
  title,
  description
}: {
  title: string;
  description: string;
}) {
  return (
    <div className="brain-control__empty">
      <strong>{title}</strong>
      <p>{description}</p>
    </div>
  );
}

function DrawerSection({
  title,
  subtitle,
  actions,
  children
}: {
  title: string;
  subtitle?: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="brain-control__drawer-section">
      <header className="brain-control__drawer-section-header">
        <div>
          <h4>{title}</h4>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        {actions ? <div>{actions}</div> : null}
      </header>
      {children}
    </section>
  );
}

function FilterField({
  filter,
  value,
  options,
  onChange
}: {
  filter: BrainControlFilterConfig;
  value: string;
  options: BrainAdminReferenceOption[];
  onChange: (value: string) => void;
}) {
  if (filter.type === "select") {
    return (
      <label className="brain-control__field">
        <span>{filter.label}</span>
        <select className="input" value={value} onChange={(event) => onChange(event.target.value)}>
          <option value="">{filter.placeholder ?? "Todos"}</option>
          {options.map((option) => (
            <option key={String(option.value)} value={String(option.value)}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    );
  }

  return (
    <label className="brain-control__field">
      <span>{filter.label}</span>
      <input
        className="input"
        placeholder={filter.placeholder}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  );
}

function FilterChipBar({
  filters,
  query,
  options
}: {
  filters: BrainControlFilterConfig[];
  query: Record<string, string>;
  options: BrainAdminReferenceOptions | null;
}) {
  const activeFilters = filters
    .map((filter) => {
      const value = query[filter.key];

      if (!value || value === "all") {
        return null;
      }

      const sourceOptions = filter.optionSource ? resolveOptionSource(filter.optionSource, options) : [];
      const option =
        sourceOptions.find((candidate) => String(candidate.value) === String(value)) ??
        filter.options?.find((candidate) => candidate.value === value);

      return {
        key: filter.key,
        label: filter.label,
        value: option ? option.label : value
      };
    })
    .filter(Boolean) as Array<{ key: string; label: string; value: string }>;

  if (!activeFilters.length) {
    return null;
  }

  return (
    <div className="brain-control__chips">
      {activeFilters.map((filter) => (
        <span key={filter.key} className="brain-control__chip">
          {filter.label}: {filter.value}
        </span>
      ))}
    </div>
  );
}

function FormField({
  field,
  value,
  disabled,
  hidden,
  options,
  onChange
}: {
  field: BrainControlFieldConfig;
  value: unknown;
  disabled: boolean;
  hidden?: boolean;
  options: BrainAdminReferenceOption[];
  onChange: (value: unknown) => void;
}) {
  if (hidden) {
    return null;
  }

  const className =
    field.type === "textarea"
      ? "brain-control__form-field brain-control__form-field--full"
      : "brain-control__form-field";

  if (field.type === "checkbox") {
    return (
      <label className={`${className} brain-control__check-row`}>
        <input
          checked={booleanFromValue(value)}
          disabled={disabled}
          onChange={(event) => onChange(event.target.checked)}
          type="checkbox"
        />
        <span>{field.label}</span>
      </label>
    );
  }

  if (field.type === "select") {
    return (
      <label className={className}>
        <span>{field.label}</span>
        <select
          className="input"
          disabled={disabled}
          value={String(value ?? "")}
          onChange={(event) => onChange(event.target.value)}
        >
          <option value="">{field.placeholder ?? "Selecciona una opcion"}</option>
          {options.map((option) => (
            <option key={String(option.value)} value={String(option.value)}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    );
  }

  if (field.type === "textarea") {
    return (
      <label className={className}>
        <span>{field.label}</span>
        <textarea
          className="input input--multiline"
          disabled={disabled}
          placeholder={field.placeholder}
          value={String(value ?? "")}
          onChange={(event) => onChange(event.target.value)}
        />
      </label>
    );
  }

  return (
    <label className={className}>
      <span>{field.label}</span>
      <input
        className="input"
        disabled={disabled}
        placeholder={field.placeholder}
        type={field.type === "number" ? "number" : field.type === "date" ? "date" : "text"}
        value={String(value ?? "")}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  );
}

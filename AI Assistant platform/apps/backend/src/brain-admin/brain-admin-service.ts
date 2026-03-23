import {
  AssistantRequest,
  BrainAdminActorContext,
  BrainAdminBootstrap,
  BrainAdminDeletePayload,
  BrainAdminDetailResponse,
  BrainAdminListResponse,
  BrainAdminReferenceOptions,
  BrainAdminResourceKey,
  BrainAdminSavePayload,
  BrainAdminSummary
} from "@vlv-ai/shared";
import { z } from "zod";

import { ActionDefinition } from "../actions/action-registry";
import { BusinessBrainScopeResolver } from "../actions/business-brain-scope-resolver";
import {
  bootstrapWritableResources,
  brainAdminModules,
  brainAdminResources,
  brainAdminStatusOptions,
  getBrainAdminResourceDefinition
} from "./brain-admin-registry";
import { RuntimeConfigService } from "../config/runtime-config-service";
import { MariaDbPool } from "../db/mariadb-pool";
import { ProcedureExecutionResult, ProcedureExecutor } from "../db/procedure-executor";
import { NotFoundError, ValidationError } from "../shared/errors";

type BrainRow = Record<string, unknown>;

interface ReferenceData {
  organization: BrainRow | null;
  users: BrainRow[];
  roles: BrainRow[];
  userRoles: BrainRow[];
  businessAreas: BrainRow[];
  userAreaAssignments: BrainRow[];
  userCapacityProfiles: BrainRow[];
  businessLines: BrainRow[];
  businessPriorities: BrainRow[];
}

interface NormalizedListQuery {
  search?: string;
  page: number;
  pageSize: number;
  sortBy?: string;
  sortDir: "asc" | "desc";
  filters: Record<string, unknown>;
}

export class BrainAdminService {
  constructor(
    private readonly runtimeConfigService: RuntimeConfigService,
    private readonly mariaDbPool: MariaDbPool,
    private readonly procedureExecutor: ProcedureExecutor,
    private readonly scopeResolver: BusinessBrainScopeResolver
  ) {}

  async getBootstrap(sessionActorUserId?: number | null): Promise<BrainAdminBootstrap> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const organization = await this.findOrganization(actorContext);

    return {
      target: "business_brain",
      organization,
      actorContext,
      modules: brainAdminModules
    };
  }

  async getSummary(sessionActorUserId?: number | null): Promise<BrainAdminSummary> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const pool = await this.mariaDbPool.getPool("business_brain");
    const organizationId = actorContext.resolvedOrganizationId;

    const countQueries: Array<[BrainAdminResourceKey, string, unknown[]]> = [
      ["organization", "SELECT COUNT(*) AS total FROM organization WHERE id = ?", [organizationId]],
      ["user_account", "SELECT COUNT(*) AS total FROM user_account WHERE organization_id = ?", [organizationId]],
      ["role", "SELECT COUNT(*) AS total FROM role", []],
      ["business_area", "SELECT COUNT(*) AS total FROM business_area WHERE organization_id = ?", [organizationId]],
      ["business_line", "SELECT COUNT(*) AS total FROM business_line WHERE organization_id = ?", [organizationId]],
      ["business_priority", "SELECT COUNT(*) AS total FROM business_priority WHERE organization_id = ?", [organizationId]],
      ["objective_record", "SELECT COUNT(*) AS total FROM objective_record WHERE organization_id = ?", [organizationId]],
      ["external_system", "SELECT COUNT(*) AS total FROM external_system", []],
      ["knowledge_document", "SELECT COUNT(*) AS total FROM knowledge_document WHERE organization_id = ?", [organizationId]]
    ];

    const counts = Object.fromEntries(
      await Promise.all(
        countQueries.map(async ([key, sql, params]) => {
          const [rows] = await pool.query(sql, params);
          const total = Number(this.firstRow(rows)?.total ?? 0);
          return [key, total];
        })
      )
    ) as Partial<Record<BrainAdminResourceKey, number>>;

    const [recentChangeRows] = await pool.query(
      `SELECT
        a.id,
        a.action_type,
        a.entity_type,
        a.entity_id,
        a.change_summary,
        a.created_at,
        u.display_name AS actor_display_name
      FROM audit_log a
      LEFT JOIN user_account u ON u.id = a.user_id
      WHERE a.entity_type IN (
        'organization',
        'user_account',
        'role',
        'user_area_assignment',
        'user_capacity_profile',
        'business_area',
        'business_line',
        'business_priority',
        'objective_record',
        'external_system',
        'knowledge_document'
      )
      ORDER BY a.created_at DESC, a.id DESC
      LIMIT 12`
    );

    return {
      target: "business_brain",
      actorContext,
      counts,
      recentChanges: this.asRows(recentChangeRows)
    };
  }

  async getOptions(sessionActorUserId?: number | null): Promise<BrainAdminReferenceOptions> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const references = await this.loadReferenceData(actorContext);

    return {
      actorContext,
      users: references.users.map((row) => ({
        value: Number(row.id),
        label: String(row.display_name ?? `Usuario ${row.id}`),
        description: String(row.email ?? row.role_summary ?? "")
      })),
      roles: references.roles.map((row) => ({
        value: Number(row.id),
        label: String(row.name ?? `Role ${row.id}`),
        description: String(row.description ?? "")
      })),
      businessAreas: references.businessAreas.map((row) => ({
        value: Number(row.id),
        label: String(row.name ?? `Area ${row.id}`),
        description: String(row.code ?? row.description ?? "")
      })),
      businessLines: references.businessLines.map((row) => ({
        value: Number(row.id),
        label: String(row.name ?? `Line ${row.id}`),
        description: String(row.current_status ?? "")
      })),
      priorities: references.businessPriorities.map((row) => ({
        value: Number(row.id),
        label: String(row.title ?? `Priority ${row.id}`),
        description: String(row.status ?? "")
      })),
      statuses: brainAdminStatusOptions
    };
  }

  async listResource(
    resource: BrainAdminResourceKey,
    query: Record<string, unknown>,
    sessionActorUserId?: number | null
  ): Promise<BrainAdminListResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const normalizedQuery = this.normalizeListQuery(resource, query);
    const rows = await this.lookupResourceRows(resource, actorContext, normalizedQuery.search);
    const filtered = this.applyResourceFilters(resource, rows, normalizedQuery);
    const sorted = this.sortRows(filtered, normalizedQuery.sortBy, normalizedQuery.sortDir);
    const pageItems = this.paginateRows(sorted, normalizedQuery.page, normalizedQuery.pageSize);

    return {
      resource,
      items: pageItems,
      total: sorted.length,
      page: normalizedQuery.page,
      pageSize: normalizedQuery.pageSize,
      totalPages: Math.max(1, Math.ceil(sorted.length / normalizedQuery.pageSize)),
      sortBy: normalizedQuery.sortBy,
      sortDir: normalizedQuery.sortDir,
      filtersApplied: {
        search: normalizedQuery.search ?? null,
        ...normalizedQuery.filters
      },
      actorContext
    };
  }

  async getResourceDetail(
    resource: BrainAdminResourceKey,
    id: number,
    sessionActorUserId?: number | null
  ): Promise<BrainAdminDetailResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const rows = await this.lookupResourceRows(resource, actorContext, undefined, id);
    const item = rows[0] ?? null;

    if (!item) {
      throw new NotFoundError(`No se encontro ${resource} con id ${id}.`);
    }

    return {
      resource,
      item,
      actorContext
    };
  }

  async saveResource(
    resource: BrainAdminResourceKey,
    id: number | null,
    payload: BrainAdminSavePayload,
    sessionActorUserId?: number | null
  ): Promise<BrainAdminDetailResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    this.ensureWriteAllowed(resource, actorContext);

    const definition = getBrainAdminResourceDefinition(resource).upsertAction;

    if (!definition) {
      throw new ValidationError(`El recurso ${resource} no soporta escritura manual.`);
    }

    const preparedValues = this.prepareSaveValues(resource, id, payload.values, actorContext);
    const execution = await this.executeDefinition(definition, preparedValues, actorContext);
    const savedRow = this.firstRow(execution.recordsets[0]);
    const savedId = Number(savedRow?.id ?? id ?? 0);

    if (!Number.isFinite(savedId) || savedId <= 0) {
      throw new ValidationError(`No se pudo resolver el id final para ${resource}.`);
    }

    return this.getResourceDetail(resource, savedId, sessionActorUserId);
  }

  async deleteResource(
    resource: BrainAdminResourceKey,
    id: number,
    payload: BrainAdminDeletePayload,
    sessionActorUserId?: number | null
  ): Promise<Record<string, unknown>> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    this.ensureWriteAllowed(resource, actorContext);

    const definition = getBrainAdminResourceDefinition(resource).deleteAction;

    if (!definition) {
      throw new ValidationError(`El recurso ${resource} no expone borrado fisico.`);
    }

    const execution = await this.executeDefinition(
      definition,
      {
        id,
        reason: payload.reason ?? ""
      },
      actorContext
    );

    return this.firstRow(execution.recordsets[0]) ?? { deletedId: id };
  }

  async syncUserRoles(
    userId: number,
    roleIds: number[],
    sessionActorUserId?: number | null
  ): Promise<BrainAdminDetailResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    this.ensureWriteAllowed("user_role", actorContext);

    const execution = await this.executeDefinition(
      {
        target: "business_brain",
        name: "brain_admin.user_role.sync",
        description: "Sync user roles for Brain Control.",
        executable: true,
        mode: "write",
        requiredPermissions: [],
        argsSchema: z.object({
          userId: z.number().int().positive(),
          roleIdsCsv: z.string().optional()
        }),
        procedure: {
          name: "sp_user_role_sync",
          kind: "standard",
          mapArguments: (
            args: { userId: number; roleIdsCsv?: string },
            request: AssistantRequest
          ) => [args.userId, args.roleIdsCsv ?? "", request.actorUserId]
        }
      } as unknown as ActionDefinition,
      {
        userId,
        roleIdsCsv: roleIds.join(",")
      },
      actorContext
    );

    void execution;

    return this.getUserContext(userId, sessionActorUserId);
  }

  async getUserContext(
    userId: number,
    sessionActorUserId?: number | null
  ): Promise<BrainAdminDetailResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    const references = await this.loadReferenceData(actorContext);
    const users = await this.lookupResourceRows("user_account", actorContext, undefined, userId, references);
    const item = users[0] ?? null;

    if (!item) {
      throw new NotFoundError(`No se encontro el usuario ${userId}.`);
    }

    const roles = references.userRoles
      .filter((row) => Number(row.user_id) === userId)
      .map((row) => {
        const role = references.roles.find((candidate) => Number(candidate.id) === Number(row.role_id));
        return {
          ...row,
          role_name: role?.name ?? null,
          role_description: role?.description ?? null
        };
      });

    const areaAssignments = references.userAreaAssignments
      .filter((row) => Number(row.user_id) === userId)
      .map((row) => {
        const area = references.businessAreas.find(
          (candidate) => Number(candidate.id) === Number(row.business_area_id)
        );
        return {
          ...row,
          business_area_name: area?.name ?? null
        };
      });

    const capacityProfile =
      references.userCapacityProfiles.find((row) => Number(row.user_id) === userId) ?? null;

    return {
      resource: "user_account",
      item,
      actorContext,
      related: {
        roles,
        areaAssignments,
        capacityProfile
      }
    };
  }

  async publishKnowledgeDocument(
    knowledgeDocumentId: number,
    sessionActorUserId?: number | null
  ): Promise<BrainAdminDetailResponse> {
    const actorContext = await this.resolveActorContext(sessionActorUserId);
    this.ensureWriteAllowed("knowledge_document", actorContext);

    await this.executeDefinition(
      {
        target: "business_brain",
        name: "brain_admin.knowledge_document.publish",
        description: "Publish a knowledge document from Brain Control.",
        executable: true,
        mode: "write",
        requiredPermissions: [],
        argsSchema: z.object({
          knowledgeDocumentId: z.number().int().positive()
        }),
        procedure: {
          name: "sp_knowledge_document_publish",
          kind: "standard",
          mapArguments: (
            args: { knowledgeDocumentId: number },
            request: AssistantRequest
          ) => [args.knowledgeDocumentId, request.actorUserId]
        }
      } as unknown as ActionDefinition,
      {
        knowledgeDocumentId
      },
      actorContext
    );

    return this.getResourceDetail("knowledge_document", knowledgeDocumentId, sessionActorUserId);
  }

  private async resolveActorContext(
    sessionActorUserId?: number | null
  ): Promise<BrainAdminActorContext> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    const defaultActorUserId = config.domains.business_brain.assistant.defaultActorUserId;
    const effectiveActorUserId =
      typeof sessionActorUserId === "number" && Number.isFinite(sessionActorUserId)
        ? sessionActorUserId
        : defaultActorUserId;
    const resolvedOrganizationId = await this.scopeResolver.resolveDefaultOrganizationId();
    const pool = await this.mariaDbPool.getPool("business_brain");

    const [aggregateRows] = await pool.query(
      `SELECT
        COUNT(*) AS user_count,
        SUM(CASE WHEN id = ? THEN 1 ELSE 0 END) AS actor_matches
      FROM user_account
      WHERE organization_id = ?`,
      [effectiveActorUserId, resolvedOrganizationId]
    );

    const aggregate = this.firstRow(aggregateRows);
    const userCount = Number(aggregate?.user_count ?? 0);
    const actorFound = Number(aggregate?.actor_matches ?? 0) > 0;
    const bootstrapMode = userCount === 0;

    if (effectiveActorUserId > 0 && actorFound) {
      return {
        defaultActorUserId,
        sessionActorUserId:
          typeof sessionActorUserId === "number" && Number.isFinite(sessionActorUserId)
            ? sessionActorUserId
            : null,
        effectiveActorUserId,
        resolvedOrganizationId,
        actorFound: true,
        bootstrapMode,
        writeMode: "full",
        writableResources: Object.keys(brainAdminResources) as BrainAdminResourceKey[]
      };
    }

    if (bootstrapMode && effectiveActorUserId === 0) {
      return {
        defaultActorUserId,
        sessionActorUserId:
          typeof sessionActorUserId === "number" && Number.isFinite(sessionActorUserId)
            ? sessionActorUserId
            : null,
        effectiveActorUserId,
        resolvedOrganizationId,
        actorFound: false,
        bootstrapMode: true,
        writeMode: "bootstrap_only",
        writableResources: bootstrapWritableResources,
        reason:
          "No existe aun un actor valido en user_account. Solo se permiten writes de bootstrap."
      };
    }

    return {
      defaultActorUserId,
      sessionActorUserId:
        typeof sessionActorUserId === "number" && Number.isFinite(sessionActorUserId)
          ? sessionActorUserId
          : null,
      effectiveActorUserId,
      resolvedOrganizationId,
      actorFound: false,
      bootstrapMode,
      writeMode: "blocked",
      writableResources: [],
      reason:
        "No hay un actor valido para auditoria. Configura un actor existente en business_brain antes de editar."
    };
  }

  private ensureWriteAllowed(
    resource: BrainAdminResourceKey,
    actorContext: BrainAdminActorContext
  ): void {
    if (actorContext.writableResources.includes(resource)) {
      return;
    }

    throw new ValidationError(
      actorContext.reason ??
        `El recurso ${resource} no puede escribirse con el actor actual del Brain Control.`
    );
  }

  private async findOrganization(actorContext: BrainAdminActorContext): Promise<BrainRow | null> {
    const rows = await this.lookupResourceRows(
      "organization",
      actorContext,
      undefined,
      actorContext.resolvedOrganizationId
    );
    return rows[0] ?? null;
  }

  private async lookupResourceRows(
    resource: BrainAdminResourceKey,
    actorContext: BrainAdminActorContext,
    search?: string,
    id?: number,
    preloadedReferences?: ReferenceData
  ): Promise<BrainRow[]> {
    const definition = getBrainAdminResourceDefinition(resource).lookupAction;
    const execution = await this.executeDefinition(
      definition,
      {
        id: id ?? undefined,
        organizationId:
          resource === "role" || resource === "external_system"
            ? undefined
            : actorContext.resolvedOrganizationId,
        search: search ?? undefined,
        onlyActive: undefined,
        limit: 500
      },
      actorContext
    );
    const rows = this.asRows(execution.recordsets[0]);
    return this.enrichRows(resource, rows, actorContext, preloadedReferences);
  }

  private async executeDefinition(
    definition: ActionDefinition,
    rawArgs: Record<string, unknown>,
    actorContext: BrainAdminActorContext
  ): Promise<ProcedureExecutionResult> {
    const parsedArguments = definition.argsSchema.parse(rawArgs) as Record<string, unknown>;
    const request = await this.buildRequest(actorContext);
    return this.procedureExecutor.execute(definition, parsedArguments, request);
  }

  private async buildRequest(actorContext: BrainAdminActorContext): Promise<AssistantRequest> {
    const config = await this.runtimeConfigService.getDecryptedConfig();
    const target = config.domains.business_brain;

    return {
      tenantId: config.tenantId,
      target: "business_brain",
      companyCode: target.assistant.companyCode,
      conversationId: "brain-control",
      userId: "brain-control",
      actorUserId: actorContext.effectiveActorUserId,
      message: "brain-admin-manual-operation",
      locale: target.assistant.defaultLocale,
      channel: "admin",
      roles: ["admin"],
      permissions: ["brain.admin"]
    };
  }

  private async loadReferenceData(actorContext: BrainAdminActorContext): Promise<ReferenceData> {
    const organizationId = actorContext.resolvedOrganizationId;
    const [organization, users, roles, userRoles, businessAreas, userAreaAssignments, userCapacityProfiles, businessLines, businessPriorities] =
      await Promise.all([
        this.lookupBaseRows("organization", organizationId, undefined, organizationId),
        this.lookupBaseRows("user_account", organizationId),
        this.lookupBaseRows("role", organizationId),
        this.lookupBaseRows("user_role", organizationId),
        this.lookupBaseRows("business_area", organizationId),
        this.lookupBaseRows("user_area_assignment", organizationId),
        this.lookupBaseRows("user_capacity_profile", organizationId),
        this.lookupBaseRows("business_line", organizationId),
        this.lookupBaseRows("business_priority", organizationId)
      ]);

    return {
      organization: organization[0] ?? null,
      users,
      roles,
      userRoles,
      businessAreas,
      userAreaAssignments,
      userCapacityProfiles,
      businessLines,
      businessPriorities
    };
  }

  private async lookupBaseRows(
    resource: BrainAdminResourceKey,
    organizationId: number,
    search?: string,
    id?: number
  ): Promise<BrainRow[]> {
    const definition = getBrainAdminResourceDefinition(resource).lookupAction;
    const actorContext = await this.resolveActorContext(undefined);
    const execution = await this.executeDefinition(
      definition,
      {
        id: id ?? undefined,
        organizationId: resource === "role" || resource === "external_system" ? undefined : organizationId,
        search: search ?? undefined,
        onlyActive: undefined,
        limit: 500
      },
      actorContext
    );
    return this.asRows(execution.recordsets[0]);
  }

  private async enrichRows(
    resource: BrainAdminResourceKey,
    rows: BrainRow[],
    actorContext: BrainAdminActorContext,
    preloadedReferences?: ReferenceData
  ): Promise<BrainRow[]> {
    const references = preloadedReferences ?? (await this.loadReferenceData(actorContext));
    const usersById = this.indexById(references.users);
    const rolesById = this.indexById(references.roles);
    const areasById = this.indexById(references.businessAreas);
    const linesById = this.indexById(references.businessLines);

    switch (resource) {
      case "organization":
        return rows.map((row) => ({
          ...row,
          record_label: row.name ?? row.id
        }));
      case "user_account":
        return rows.map((row) => {
          const userId = Number(row.id);
          const userRoles = references.userRoles.filter(
            (candidate) => Number(candidate.user_id) === userId
          );
          const areaAssignments = references.userAreaAssignments.filter(
            (candidate) => Number(candidate.user_id) === userId
          );
          const primaryRole = userRoles.find((candidate) => Number(candidate.is_primary) === 1);
          const primaryArea = areaAssignments.find((candidate) => Number(candidate.is_primary) === 1);
          const capacityProfile = references.userCapacityProfiles.find(
            (candidate) => Number(candidate.user_id) === userId
          );

          return {
            ...row,
            role_ids: userRoles.map((candidate) => Number(candidate.role_id)),
            role_labels: userRoles
              .map((candidate) => rolesById.get(Number(candidate.role_id))?.name)
              .filter(Boolean),
            primary_role_label:
              rolesById.get(Number(primaryRole?.role_id ?? 0))?.name ?? null,
            business_area_ids: areaAssignments.map((candidate) => Number(candidate.business_area_id)),
            business_area_labels: areaAssignments
              .map((candidate) => areasById.get(Number(candidate.business_area_id))?.name)
              .filter(Boolean),
            primary_business_area_label:
              areasById.get(Number(primaryArea?.business_area_id ?? 0))?.name ?? null,
            assignment_count: areaAssignments.length,
            capacity_profile_id: capacityProfile?.id ?? null,
            weekly_capacity_hours: capacityProfile?.weekly_capacity_hours ?? null,
            max_parallel_projects: capacityProfile?.max_parallel_projects ?? null,
            max_parallel_tasks: capacityProfile?.max_parallel_tasks ?? null,
            record_label: row.display_name ?? row.id
          };
        });
      case "role":
        return rows.map((row) => {
          const roleId = Number(row.id);
          const assignments = references.userRoles.filter(
            (candidate) => Number(candidate.role_id) === roleId
          );
          return {
            ...row,
            assigned_user_count: assignments.length,
            assigned_user_labels: assignments
              .map((candidate) => usersById.get(Number(candidate.user_id))?.display_name)
              .filter(Boolean),
            record_label: row.name ?? row.id
          };
        });
      case "user_role":
        return rows.map((row) => ({
          ...row,
          user_display_name: usersById.get(Number(row.user_id))?.display_name ?? null,
          role_name: rolesById.get(Number(row.role_id))?.name ?? null
        }));
      case "user_area_assignment":
        return rows.map((row) => ({
          ...row,
          user_display_name: usersById.get(Number(row.user_id))?.display_name ?? null,
          business_area_name: areasById.get(Number(row.business_area_id))?.name ?? null
        }));
      case "user_capacity_profile":
        return rows.map((row) => ({
          ...row,
          user_display_name: usersById.get(Number(row.user_id))?.display_name ?? null
        }));
      case "business_area":
        return rows.map((row) => ({
          ...row,
          responsible_user_name:
            usersById.get(Number(row.responsible_user_id))?.display_name ?? null,
          record_label: row.name ?? row.id
        }));
      case "business_line":
        return rows.map((row) => ({
          ...row,
          business_area_name: areasById.get(Number(row.business_area_id))?.name ?? null,
          owner_user_name: usersById.get(Number(row.owner_user_id))?.display_name ?? null,
          record_label: row.name ?? row.id
        }));
      case "business_priority":
        return rows.map((row) => ({
          ...row,
          owner_user_name: usersById.get(Number(row.owner_user_id))?.display_name ?? null,
          scope_label: this.resolveScopeLabel(row, references, linesById, areasById),
          record_label: row.title ?? row.id
        }));
      case "objective_record":
        return rows.map((row) => ({
          ...row,
          business_area_name: areasById.get(Number(row.business_area_id))?.name ?? null,
          owner_user_name: usersById.get(Number(row.owner_user_id))?.display_name ?? null,
          completion_bucket: this.resolveCompletionBucket(row.completion_percent),
          record_label: row.title ?? row.id
        }));
      case "external_system":
        return rows.map((row) => ({
          ...row,
          record_label: row.name ?? row.id
        }));
      case "knowledge_document":
        return rows.map((row) => ({
          ...row,
          business_area_name: areasById.get(Number(row.business_area_id))?.name ?? null,
          owner_user_name: usersById.get(Number(row.owner_user_id))?.display_name ?? null,
          record_label: row.title ?? row.id
        }));
      default:
        return rows;
    }
  }

  private resolveScopeLabel(
    row: BrainRow,
    references: ReferenceData,
    linesById: Map<number, BrainRow>,
    areasById: Map<number, BrainRow>
  ): string {
    const scopeType = String(row.scope_type ?? "organization");
    const scopeId = Number(row.scope_id ?? 0);

    if (scopeType === "business_area") {
      return String(areasById.get(scopeId)?.name ?? "Business Area");
    }

    if (scopeType === "business_line") {
      return String(linesById.get(scopeId)?.name ?? "Business Line");
    }

    return String(references.organization?.name ?? "Organization");
  }

  private resolveCompletionBucket(value: unknown): string {
    const percent = Number(value ?? 0);

    if (percent >= 100) {
      return "100";
    }
    if (percent >= 75) {
      return "75-99";
    }
    if (percent >= 50) {
      return "50-74";
    }
    if (percent >= 25) {
      return "25-49";
    }
    return "0-24";
  }

  private normalizeListQuery(
    resource: BrainAdminResourceKey,
    query: Record<string, unknown>
  ): NormalizedListQuery {
    const definition = getBrainAdminResourceDefinition(resource);
    const filters: Record<string, unknown> = {};

    for (const [key, rawValue] of Object.entries(query)) {
      if (["search", "page", "pageSize", "sortBy", "sortDir"].includes(key)) {
        continue;
      }

      const normalized = this.normalizeScalar(rawValue);
      if (normalized !== undefined && normalized !== "") {
        filters[key] = normalized;
      }
    }

    return {
      search: this.asOptionalString(query.search),
      page: this.asPositiveInteger(query.page) ?? 1,
      pageSize: Math.min(this.asPositiveInteger(query.pageSize) ?? 25, 100),
      sortBy: this.asOptionalString(query.sortBy) ?? definition.defaultSortBy,
      sortDir: this.asSortDirection(query.sortDir) ?? definition.defaultSortDir,
      filters
    };
  }

  private applyResourceFilters(
    resource: BrainAdminResourceKey,
    rows: BrainRow[],
    query: NormalizedListQuery
  ): BrainRow[] {
    return rows.filter((row) => {
      const filters = query.filters;

      switch (resource) {
        case "organization":
          return this.matchesText(row.status, filters.status) &&
            this.matchesText(row.current_stage, filters.currentStage);
        case "user_account":
          return this.matchesActiveState(row.is_active, filters.activeState) &&
            this.matchesText(row.employment_status, filters.employmentStatus) &&
            this.matchesText(row.timezone, filters.timezone);
        case "role":
          return true;
        case "business_area":
          return this.matchesActiveState(row.is_active, filters.activeState) &&
            this.matchesText(row.priority_level, filters.priorityLevel) &&
            this.matchesId(row.responsible_user_id, filters.responsibleUserId);
        case "business_line":
          return this.matchesActiveState(row.is_active, filters.activeState) &&
            this.matchesId(row.business_area_id, filters.businessAreaId) &&
            this.matchesText(row.current_status, filters.currentStatus) &&
            this.matchesId(row.owner_user_id, filters.ownerUserId) &&
            this.matchesText(row.strategic_priority, filters.strategicPriority);
        case "business_priority":
          return this.matchesText(row.status, filters.status) &&
            this.matchesText(row.scope_type, filters.scopeType) &&
            this.matchesId(row.owner_user_id, filters.ownerUserId) &&
            this.matchesText(row.target_period, filters.targetPeriod);
        case "objective_record":
          return this.matchesText(row.status, filters.status) &&
            this.matchesText(row.objective_type, filters.objectiveType) &&
            this.matchesId(row.business_area_id, filters.businessAreaId) &&
            this.matchesId(row.owner_user_id, filters.ownerUserId) &&
            this.matchesText(row.completion_bucket, filters.completionBucket);
        case "external_system":
          return this.matchesActiveState(row.is_active, filters.activeState) &&
            this.matchesText(row.system_type, filters.systemType);
        case "knowledge_document":
          return this.matchesText(row.status, filters.status) &&
            this.matchesText(row.document_type, filters.documentType) &&
            this.matchesText(row.storage_type, filters.storageType) &&
            this.matchesId(row.business_area_id, filters.businessAreaId) &&
            this.matchesId(row.owner_user_id, filters.ownerUserId) &&
            this.matchesText(row.version_label, filters.versionLabel);
        default:
          return true;
      }
    });
  }

  private prepareSaveValues(
    resource: BrainAdminResourceKey,
    id: number | null,
    values: Record<string, unknown>,
    actorContext: BrainAdminActorContext
  ): Record<string, unknown> {
    const normalized = Object.fromEntries(
      Object.entries(values).map(([key, value]) => [key, this.normalizeScalar(value)])
    );

    if (id) {
      normalized.id = id;
    }

    switch (resource) {
      case "user_account":
      case "business_area":
      case "business_line":
      case "business_priority":
      case "objective_record":
      case "knowledge_document":
        normalized.organizationId = actorContext.resolvedOrganizationId;
        break;
      default:
        break;
    }

    return normalized;
  }

  private sortRows(
    rows: BrainRow[],
    sortBy: string | undefined,
    sortDir: "asc" | "desc"
  ): BrainRow[] {
    if (!sortBy) {
      return rows;
    }

    const sorted = [...rows].sort((left, right) => {
      const leftValue = left[sortBy];
      const rightValue = right[sortBy];

      if (leftValue == null && rightValue == null) {
        return 0;
      }
      if (leftValue == null) {
        return 1;
      }
      if (rightValue == null) {
        return -1;
      }

      if (typeof leftValue === "number" && typeof rightValue === "number") {
        return leftValue - rightValue;
      }

      return String(leftValue).localeCompare(String(rightValue), "es-MX", {
        sensitivity: "base",
        numeric: true
      });
    });

    return sortDir === "desc" ? sorted.reverse() : sorted;
  }

  private paginateRows(rows: BrainRow[], page: number, pageSize: number): BrainRow[] {
    const offset = Math.max(0, (page - 1) * pageSize);
    return rows.slice(offset, offset + pageSize);
  }

  private asRows(value: unknown): BrainRow[] {
    if (!Array.isArray(value)) {
      return [];
    }

    return value.filter((item) => Boolean(item) && typeof item === "object") as BrainRow[];
  }

  private firstRow(value: unknown): BrainRow | null {
    return this.asRows(value)[0] ?? null;
  }

  private indexById(rows: BrainRow[]): Map<number, BrainRow> {
    return new Map(rows.map((row) => [Number(row.id), row]));
  }

  private matchesText(value: unknown, expected: unknown): boolean {
    if (expected === undefined || expected === null || expected === "") {
      return true;
    }

    return String(value ?? "").toLowerCase() === String(expected).toLowerCase();
  }

  private matchesId(value: unknown, expected: unknown): boolean {
    if (expected === undefined || expected === null || expected === "") {
      return true;
    }

    return Number(value ?? 0) === Number(expected);
  }

  private matchesActiveState(value: unknown, expected: unknown): boolean {
    if (expected === undefined || expected === null || expected === "" || expected === "all") {
      return true;
    }

    const active = Number(value ?? 0) === 1;
    return expected === "active" ? active : !active;
  }

  private normalizeScalar(value: unknown): unknown {
    if (Array.isArray(value)) {
      return value.at(-1);
    }

    if (typeof value !== "string") {
      return value;
    }

    const trimmed = value.trim();

    if (!trimmed) {
      return undefined;
    }

    if (trimmed === "true") {
      return true;
    }

    if (trimmed === "false") {
      return false;
    }

    if (/^\d+$/.test(trimmed)) {
      return Number(trimmed);
    }

    if (/^\d+\.\d+$/.test(trimmed)) {
      return Number(trimmed);
    }

    return trimmed;
  }

  private asOptionalString(value: unknown): string | undefined {
    const normalized = this.normalizeScalar(value);
    return typeof normalized === "string" ? normalized : undefined;
  }

  private asPositiveInteger(value: unknown): number | undefined {
    const normalized = this.normalizeScalar(value);

    if (typeof normalized === "number" && Number.isInteger(normalized) && normalized > 0) {
      return normalized;
    }

    return undefined;
  }

  private asSortDirection(value: unknown): "asc" | "desc" | undefined {
    const normalized = this.asOptionalString(value)?.toLowerCase();
    return normalized === "asc" || normalized === "desc" ? normalized : undefined;
  }
}

# Brain Control Panel

## Purpose

`Brain Control` is the manual operations panel for the `business_brain` domain inside the existing admin UI.

It is:

- single-organization by design for Vive la Vibe
- focused on direct manual operations without chat
- backed only by approved stored procedures
- intended for operator/admin workflows

It does **not** ask for `organizationId` in the UI. The backend resolves the active organization automatically from the Business Brain context.

## Where It Lives

- Admin UI entry: `apps/admin-ui/src/features/brain-control/BrainControlPanel.tsx`
- Backend service: `apps/backend/src/brain-admin/brain-admin-service.ts`
- Backend routes: `apps/backend/src/api/routes/brain-admin-routes.ts`
- Resource registry: `apps/backend/src/brain-admin/brain-admin-registry.ts`

## UX Structure

The panel is composed of:

1. `Overview`
2. `Organization`
3. `People`
4. `Roles & Access`
5. `Business Areas`
6. `Business Lines`
7. `Priorities`
8. `Objectives`
9. `External Systems`
10. `Knowledge Documents`

Operational patterns:

- sticky data grid
- quick search plus facet filters
- visible filter chips
- persisted filters, sorting, visible columns, and page size in `localStorage`
- right-side drawer for `view`, `edit`, and `create`
- direct writes with confirmation
- single-organization language only

## Module Coverage

### Overview

Shows:

- current organization
- effective actor
- write mode
- counts for core entities
- recent audit changes
- quick links into each manual module

### Organization

Purpose:

- maintain the single company profile for Vive la Vibe

SPs:

- `sp_organization_data`
- `sp_organization_upsert`

Main filters:

- `search`
- `status`
- `currentStage`

### People

Purpose:

- maintain users and their direct operating context

SPs:

- `sp_user_account_data`
- `sp_user_account_upsert`
- `sp_user_role_data`
- `sp_user_role_sync`
- `sp_user_area_assignment_data`
- `sp_user_area_assignment_upsert`
- `sp_user_area_assignment_delete`
- `sp_user_capacity_profile_data`
- `sp_user_capacity_profile_upsert`

Drawer sections:

- profile
- roles
- area assignments
- capacity profile

Main filters:

- `search`
- `activeState`
- `employmentStatus`
- `timezone`

### Roles & Access

Purpose:

- maintain the role catalog used by the Brain

SPs:

- `sp_role_data`
- `sp_role_upsert`

Main filters:

- `search`

### Business Areas

Purpose:

- maintain areas, ownership, priority and active state

SPs:

- `sp_business_area_data`
- `sp_business_area_upsert`

Main filters:

- `search`
- `activeState`
- `priorityLevel`
- `responsibleUserId`

### Business Lines

Purpose:

- maintain business lines with area, owner, status and strategic priority

SPs:

- `sp_business_line_data`
- `sp_business_line_upsert`

Main filters:

- `search`
- `activeState`
- `businessAreaId`
- `currentStatus`
- `ownerUserId`
- `strategicPriority`

### Priorities

Purpose:

- maintain active and historical business priorities

SPs:

- `sp_business_priority_data`
- `sp_business_priority_upsert`

Main filters:

- `search`
- `status`
- `scopeType`
- `ownerUserId`
- `targetPeriod`

### Objectives

Purpose:

- maintain strategic objectives, area, owner and progress

SPs:

- `sp_objective_record_data`
- `sp_objective_record_upsert`

Main filters:

- `search`
- `status`
- `objectiveType`
- `businessAreaId`
- `ownerUserId`
- `completionBucket`

### External Systems

Purpose:

- maintain known external systems referenced by the Brain

SPs:

- `sp_external_system_data`
- `sp_external_system_upsert`

Main filters:

- `search`
- `activeState`
- `systemType`

### Knowledge Documents

Purpose:

- maintain document metadata and publication state

SPs:

- `sp_knowledge_document_data`
- `sp_knowledge_document_upsert`
- `sp_knowledge_document_publish`

Main filters:

- `search`
- `status`
- `documentType`
- `storageType`
- `businessAreaId`
- `ownerUserId`
- `versionLabel`

## Backend API Surface

The panel uses dedicated manual endpoints, not the chat endpoint.

- `GET /api/brain-admin/bootstrap`
- `GET /api/brain-admin/summary`
- `GET /api/brain-admin/options`
- `GET /api/brain-admin/:resource`
- `GET /api/brain-admin/:resource/:id`
- `POST /api/brain-admin/:resource`
- `PUT /api/brain-admin/:resource/:id`
- `DELETE /api/brain-admin/:resource/:id`
- `GET /api/brain-admin/users/:id/context`
- `POST /api/brain-admin/users/:id/roles/sync`
- `POST /api/brain-admin/knowledge-documents/:id/publish`

## Actor And Audit Behavior

The panel uses the Business Brain actor model already defined in runtime config.

Rules:

- `organizationId` is auto-resolved server-side
- actor defaults to `domains.business_brain.assistant.defaultActorUserId`
- the UI may override actor with `x-brain-actor-user-id`
- writes are blocked if the resolved actor is not allowed for the current write mode
- bootstrap mode only allows bootstrap-safe resources
- writes continue to flow through stored procedures, so audit and business rules stay centralized

## Write Mode Expectations

- `full`: normal writes enabled for allowed resources
- `bootstrap_only`: only bootstrap resources can be written
- `blocked`: no manual writes should be attempted

The panel surfaces the current mode at the top so the operator can see immediately whether create/edit actions should work.

## Notes For Future Expansion

This v1 does not cover:

- projects
- tasks
- meetings
- alerting / AI entity modules
- uploads for knowledge docs
- advanced role-based UI permissions

Those can be added later following the same pattern:

- registry-backed resource definition
- SP-only writes
- list/detail/create/update drawer flow
- server-side filters and enrichment

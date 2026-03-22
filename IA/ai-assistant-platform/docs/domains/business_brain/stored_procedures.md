# Stored Procedures

This file documents the allowed backend actions and Business Brain procedures behind them.
The assistant must only choose from the `business_brain` catalog.

## Snapshot Action

### `brain.current_context`

- Composite action built from:
  - `sp_organization_data`
  - `sp_business_area_data`
  - `sp_business_line_data`
  - `sp_business_priority_data`
  - `sp_objective_record_data`
  - `sp_external_system_data`
  - `sp_knowledge_document_data`

## Core Read Actions

### `brain.organization.lookup`

- Procedure: `sp_organization_data`
- Inputs: optional `id`, optional `organizationId`, optional `search`, optional `onlyActive`, optional `limit`

### `brain.user_account.lookup`

- Procedure: `sp_user_account_data`
- Inputs: optional `id`, optional `organizationId`, optional `search`, optional `onlyActive`, optional `limit`

### `brain.role.lookup`

- Procedure: `sp_role_data`

### `brain.business_area.lookup`

- Procedure: `sp_business_area_data`

### `brain.business_line.lookup`

- Procedure: `sp_business_line_data`

### `brain.business_priority.lookup`

- Procedure: `sp_business_priority_data`

### `brain.objective.lookup`

- Procedure: `sp_objective_record_data`

### `brain.external_system.lookup`

- Procedure: `sp_external_system_data`

### `brain.knowledge_document.lookup`

- Procedure: `sp_knowledge_document_data`

## Core Write Actions

### Bootstrap-safe writes

- `brain.organization.upsert` -> `sp_organization_upsert`
- `brain.user_account.upsert` -> `sp_user_account_upsert`
- `brain.role.upsert` -> `sp_role_upsert`
- `brain.user_roles.sync` -> `sp_user_role_sync`

These may allow actor user id `0` only where the SP helper permits bootstrap behavior.

### Standard writes

- `brain.business_area.upsert` -> `sp_business_area_upsert`
- `brain.business_line.upsert` -> `sp_business_line_upsert`
- `brain.business_priority.upsert` -> `sp_business_priority_upsert`
- `brain.objective.upsert` -> `sp_objective_record_upsert`
- `brain.external_system.upsert` -> `sp_external_system_upsert`
- `brain.knowledge_document.upsert` -> `sp_knowledge_document_upsert`
- `brain.knowledge_document.publish` -> `sp_knowledge_document_publish`

## Reference Note

- The authoritative detailed SP reference lives in:
  - `BusinessBrainDB/docs/BUSINESS_BRAIN_SQL_SP_REFERENCE.md`
- This file is the prompt-oriented operational subset used by the dual-target backend.

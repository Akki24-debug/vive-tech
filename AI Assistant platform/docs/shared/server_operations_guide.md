# Server Operations Guide

## Purpose

This guide explains how to run, operate, validate, and troubleshoot the Vive La Vibe AI assistant server.

It is the operational reference for the current dual-target backend:

- `business_brain`
- `pms`

## Current Operating Model

- One Node/TypeScript backend serves both targets.
- Each request must declare a single `target`.
- Each target has its own:
  - MariaDB connection pool
  - action catalog
  - Markdown operating docs
  - assistant defaults
- Shared platform services:
  - OpenAI integration
  - admin UI
  - approvals
  - logs
  - conversations
  - WhatsApp adapter

## Active Rollout Status

- `business_brain` is the active operational target.
- `pms` remains supported by code and documentation and can run in parallel.
- The backend must never mix docs, actions, or DB calls across targets inside the same request.

## Prerequisites

- Node.js and npm installed.
- Repo available locally at:
  - `C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform`
- MariaDB access available for each enabled target.
- Runtime config present at:
  - `storage/runtime/config/runtime-config.json`

Recommended environment variable:

```powershell
$env:APP_ENCRYPTION_KEY="set-a-stable-local-secret"
```

If `APP_ENCRYPTION_KEY` is missing, the backend falls back to a development secret. That is acceptable for local testing, but not for stable long-term runtime use.

## Runtime Files

Main runtime paths:

- Config:
  - `storage/runtime/config/runtime-config.json`
- Approvals:
  - `storage/runtime/approvals`
- Conversations:
  - `storage/runtime/conversations`
- Logs:
  - `storage/runtime/logs/application.jsonl`

These files are operational state, not source code.

## Cost Control Defaults

The backend now includes a cheap/debug workflow intended for local testing and routing validation.

Runtime optimization flags:

- `cheapModeEnabled`
- `debugModelOverride`
- `disableBroadBrainSnapshots`
- `skipFinalLlmForSimpleReads`
- `logEstimatedCost`
- `maxRecentConversationMessages`
- `maxDocs`
- `maxDocsBundleBytes`

Environment variables override saved runtime config:

```powershell
$env:AI_DEBUG_CHEAP_MODE="true"
$env:AI_MODEL_DEBUG="gpt-5-mini"
$env:AI_DISABLE_BRAIN_SNAPSHOT="true"
$env:AI_SKIP_FINAL_RESPONSE_LLM_ON_SIMPLE_READS="true"
$env:AI_LOG_ESTIMATED_COST="true"
$env:AI_MAX_RECENT_CONVERSATION_MESSAGES="4"
$env:AI_MAX_DOCS="4"
$env:AI_MAX_DOCS_BUNDLE_BYTES="24000"
```

Operational effect:

- broad `brain.current_context` reads are blocked by default in cheap/debug mode
- simple Brain reads can skip the second LLM pass and use server-side templates
- prompt bundles are intentionally smaller
- request logs include request IDs, bundle sizes, latency, and estimated cost when available

## Start Business Brain Database

If you are working with `business_brain`, start its dedicated local MariaDB instance first:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\scripts"
powershell -ExecutionPolicy Bypass -File .\start-local-business-brain-db.ps1
```

Quick validation:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\scripts"
powershell -ExecutionPolicy Bypass -File .\check-local-business-brain-db.ps1
```

Stop it when needed:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\scripts"
powershell -ExecutionPolicy Bypass -File .\stop-local-business-brain-db.ps1
```

## Start the Server

From the platform root:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform"
npm install
```

Run backend:

```powershell
npm run dev:backend
```

Run admin UI in a second terminal:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform"
npm run dev:admin
```

Default local runtime:

- Backend API: `http://localhost:3001`
- Admin UI: Vite default local port, normally `http://localhost:5173`

## Build and Validation Commands

Typecheck all workspaces:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform"
npm run typecheck
```

Build all workspaces:

```powershell
cd "C:\Users\ragnarok\Documents\repos\Proyecto VLV\AI Assistant platform"
npm run build
```

## Day-to-Day Operation Flow

### 1. Confirm dependencies

- Ensure the target database is running.
- Ensure `runtime-config.json` points to the correct host, port, user, password, and database.

### 2. Start backend and admin UI

- Start backend first.
- Start admin UI second.

### 3. Validate platform health

Check:

- `GET /api/health`

Expected:

- server up
- runtime configured
- `defaultTarget` visible

### 4. Choose the correct target

Before any test or conversation:

- use `business_brain` for operational/business-brain work
- use `pms` for PMS work

Never mix both in one request.

### 5. Validate docs and action catalog

Check target docs:

- `GET /api/docs?target=business_brain`
- `GET /api/docs?target=pms`

Check target actions:

- `GET /api/actions?target=business_brain`
- `GET /api/actions?target=pms`

### 6. Use admin chat or approved API calls

- Use admin chat for guided testing.
- The assistant should only call actions from the selected target catalog.
- Write actions may trigger approval flow depending on policy.

## API Endpoints Used in Operations

Core endpoints:

- `GET /api/health`
- `GET /api/config`
- `POST /api/config`
- `POST /api/config/test`
- `GET /api/docs`
- `GET /api/actions`
- `POST /api/assistant/messages`

Support endpoints:

- conversations routes
- approvals routes
- logs routes
- WhatsApp webhook routes

Operational rule:

- `/api/docs` and `/api/actions` must always be queried with the intended `target`.

## Action Catalog

This section lists the currently registered actions that the model may use. The model must never call procedures outside this list.

### Shared Non-Executable Action

Present on both targets:

- `conversation.clarify`
  - Use when the user request is missing required inputs.
  - No stored procedure is executed.

## Business Brain Actions

Target:

- `business_brain`

Current registered actions:

- `brain.current_context`
  - Composite read snapshot of current business context.
- `brain.organization.lookup`
  - Read organizations.
- `brain.organization.upsert`
  - Create or update organization.
- `brain.user_account.lookup`
  - Read users.
- `brain.user_account.upsert`
  - Create or update user.
- `brain.role.lookup`
  - Read roles.
- `brain.role.upsert`
  - Create or update role.
- `brain.user_roles.sync`
  - Replace all roles for a user.
- `brain.business_area.lookup`
  - Read business areas.
- `brain.business_area.upsert`
  - Create or update business area.
- `brain.business_line.lookup`
  - Read business lines.
- `brain.business_line.upsert`
  - Create or update business line.
- `brain.business_priority.lookup`
  - Read strategic priorities.
- `brain.business_priority.upsert`
  - Create or update strategic priority.
- `brain.objective.lookup`
  - Read strategic objectives.
- `brain.objective.upsert`
  - Create or update strategic objective.
- `brain.external_system.lookup`
  - Read external systems.
- `brain.external_system.upsert`
  - Create or update external system.
- `brain.knowledge_document.lookup`
  - Read knowledge documents.
- `brain.knowledge_document.upsert`
  - Create or update knowledge document.
- `brain.knowledge_document.publish`
  - Publish a knowledge document.

Business Brain operating notes:

- Use `lookup` actions for reads.
- Use `upsert` actions for create or update.
- Use `brain.user_account.upsert` and `brain.role.upsert` with extra care during bootstrap.
- `brain.user_roles.sync` replaces the full assigned role set, it does not append incrementally.
- Do not use `brain.current_context` as the default first move.
- Prefer table-specific lookups unless the user explicitly asks for a broad snapshot.

## PMS Actions

Target:

- `pms`

Current registered actions:

- `availability.search`
  - Read availability.
- `pricing.quote`
  - Calculate a stay quote.
- `property.lookup`
  - Read properties, categories, rooms, and rate plans.
- `guest.lookup`
  - Read guests and reservation history.
- `catalog.lookup`
  - Read operational catalog items.
- `reservation.lookup`
  - Read reservations.
- `operations.current_state`
  - Composite operational snapshot.
- `reservation.create_hold`
  - Create a reservation hold.
- `reservation.confirm_hold`
  - Confirm a hold into an active reservation.
- `reservation.update`
  - Update reservation state and core fields.

PMS operating notes:

- PMS remains code-ready and can run in parallel.
- Its reconnection and business validation may still be staged separately from the current `business_brain` rollout.

## Documents the Model Uses

Shared docs loaded for all requests:

- `docs/shared/platform_overview.md`
- `docs/shared/target_routing.md`

Target-specific docs for `business_brain`:

- `docs/domains/business_brain/business_rules.md`
- `docs/domains/business_brain/stored_procedures.md`
- `docs/domains/business_brain/assistant_behavior.md`
- `docs/domains/business_brain/permissions.md`
- `docs/domains/business_brain/company_context.md`

Target-specific docs for `pms`:

- `docs/domains/pms/business_rules.md`
- `docs/domains/pms/stored_procedures.md`
- `docs/domains/pms/assistant_behavior.md`
- `docs/domains/pms/permissions.md`
- `docs/domains/pms/company_context.md`

Operational rule:

- If a doc changes business behavior, permissions, routing, or procedure expectations, restart the backend after the change.

## Approval and Safety Model

- Read actions can execute immediately if allowed by policy.
- Write actions may require approval.
- The assistant must not invent SQL, skip the registry, or fabricate records.
- The backend is the execution gatekeeper.

## Parallel Operation Rules

The server is designed to run both targets in parallel.

This means:

- one backend process
- two independent MariaDB pools
- two isolated action catalogs
- two isolated doc bundles
- shared approvals, logs, and conversations with target metadata preserved

Required discipline:

- each request must specify one target
- each target must keep its own valid DB config
- never route a `business_brain` request through PMS actions
- never route a `pms` request through Business Brain actions

## Smoke Test Checklist

### Business Brain

1. Start `BD vive la vibe brain`.
2. Start backend.
3. Start admin UI.
4. Check `GET /api/health`.
5. Check `GET /api/actions?target=business_brain`.
6. Check `GET /api/docs?target=business_brain`.
7. Run a simple read using admin chat, such as organization lookup.

### PMS

1. Ensure PMS MariaDB is reachable.
2. Check `GET /api/actions?target=pms`.
3. Check `GET /api/docs?target=pms`.
4. Run a simple read such as property or reservation lookup.

## Common Failure Modes

### Backend starts but actions fail

Likely causes:

- wrong DB host or port
- wrong database name
- target database not running
- wrong user or password
- stored procedures not installed

Checks:

- `POST /api/config/test`
- target-specific DB availability
- Business Brain local DB status script
- `storage/runtime/logs/application.jsonl`

Key forensic event types:

- `ai.preflight.failed`
- `ai.request.started`
- `ai.request.completed`
- `ai.request.costed`
- `assistant.routing.selected`
- `assistant.routing.blocked_broad_read`
- `assistant.response.templated`
- `assistant.response.llm_skipped`
- `assistant.docs.bundle_built`

### Docs endpoint works but assistant behaves with the wrong domain

Likely causes:

- wrong `target` selected in admin UI
- missing or incorrect target defaults
- stale assumptions in request flow

Checks:

- verify selected target in UI
- verify `defaultTarget`
- verify `GET /api/docs?target=...`
- verify `GET /api/actions?target=...`

### Secret-related issues

Likely causes:

- changed `APP_ENCRYPTION_KEY`
- existing encrypted secrets no longer match the active key

Resolution:

- restore the previous encryption key
- or re-save secrets through setup with the new key

## Operational Change Rules

When changing the platform:

- update code
- update the relevant target docs
- update this guide if operation changes
- typecheck
- build
- validate health, docs, and actions

If the action catalog changes:

- update `apps/backend/src/actions/action-registry.ts`
- update target docs
- update this guide's action list

## Recommended Operator Routine

At the beginning of a session:

1. Confirm the intended target.
2. Confirm the target DB is up.
3. Start backend and admin UI.
4. Check health.
5. Check actions and docs for that target.

Before write testing:

1. Confirm actor user id and permissions.
2. Confirm the action belongs to the selected target.
3. Confirm approvals policy if applicable.

Before ending the session:

1. Review logs.
2. Stop local Business Brain DB if it was only needed for the session.
3. Commit doc or config-safe changes if they belong in source control.

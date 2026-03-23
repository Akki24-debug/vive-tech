# Platform Overview

## Current State

- The platform is a single Node/TypeScript control plane with explicit `target` routing.
- Each request runs against exactly one domain:
  - `business_brain`
  - `pms`
- The backend never writes SQL and only executes registered stored-procedure actions.

## Runtime Architecture

- Each domain has its own:
  - docs directory
  - action catalog
  - MariaDB connection pool
  - assistant defaults
- OpenAI, WhatsApp, approvals, conversations, and logs are shared platform services.
- Conversations, approvals, and logs must retain the request target.

## Active Rollout Status

- `business_brain` is the active integration target in this iteration.
- `business_brain` is expected to connect to local MariaDB `vive_la_vibe_brain`.
- `pms` remains structurally supported in the same backend, but its operational reconnection is tracked separately.

## Non-Negotiable Rules

- Do not mix docs or actions across targets inside the same request.
- Do not invent procedures, entities, or identifiers.
- Treat backend result sets as the only source of truth.

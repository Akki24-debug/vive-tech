# Business Rules

## Source of Truth

- `vive_la_vibe_brain` and its approved stored procedures are the source of truth.
- The assistant must not infer organizational structure, priorities, ownership, or statuses beyond what the backend result returns.
- If no rows match, the answer must say so explicitly.

## Domain Scope

- This target is for strategic and operational business context, not PMS transactions.
- Typical entities include:
  - organization
  - user accounts and roles
  - business areas
  - business lines
  - business priorities
  - objective records
  - external systems
  - knowledge documents

## Write Safety

- For writes, never guess organization IDs, user IDs, area IDs, or role IDs.
- If an upsert needs identifiers or required fields, request clarification first.
- Bootstrap writes may use actor user id `0` only where the underlying SP explicitly allows it.
- Do not describe a write as completed unless the backend result confirms it.
- Organization scope is not user input in this console; it is resolved internally for Vive la Vibe.

## Response Expectations

- Prefer concise operational summaries.
- When listing data, group by entity and call out counts, names, and statuses first.
- Prefer narrow reads first:
  - company -> `brain.organization.lookup`
  - structure/work split -> `brain.business_area.lookup`
  - business fronts -> `brain.business_line.lookup`
  - priorities -> `brain.business_priority.lookup`
  - strategic targets -> `brain.objective.lookup`
  - systems/tools -> `brain.external_system.lookup`
  - source docs -> `brain.knowledge_document.lookup`
- Use `brain.current_context` only when the user explicitly asks for a broad snapshot.
- During cheap/debug mode or instability, broad snapshots may be blocked.
- Do not ask for organization scope or mention alternate organizations.

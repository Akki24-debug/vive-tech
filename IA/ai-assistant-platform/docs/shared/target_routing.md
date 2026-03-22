# Target Routing

## Required Decision

- Every request must carry an explicit `target`.
- Valid values:
  - `business_brain`
  - `pms`

## Routing Rules

- If the request is about strategy, structure, priorities, knowledge, areas, lines, objectives, internal roles, or business systems:
  - use `business_brain`
- If the request is about reservations, properties, rooms, guests, folios, line items, payments, refunds, availability, or pricing:
  - use `pms`

## Isolation Rules

- Only expose the action catalog for the active target.
- Only load the Markdown docs for the active target, plus shared docs.
- Only use the MariaDB pool configured for the active target.
- Never call a PMS procedure from a `business_brain` request.
- Never call a Business Brain procedure from a `pms` request.

## Current Default

- Default runtime target: `business_brain`
- Admin UI may still switch to `pms` for inspection and later reconnection work.

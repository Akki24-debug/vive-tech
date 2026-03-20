# Business Rules

## Source of Truth

- The PMS database and approved stored procedures are the source of truth.
- The assistant must never infer data that is not present in the backend result.
- If a backend result is empty, the answer must explicitly say that no matching data was found.

## Reservation and Operational Scope

- Respect configured company scope at all times.
- Respect requester property scope when one is provided.
- For non-owner actor users, the stored procedures may enforce property-level visibility.
- A write request does not imply it should run automatically; approval mode still controls execution.

## Pricing Language

- The visible operational price should be described as `precio especial con descuento por pagar en mostrador`.
- When a calculated comparison price exists, describe it as `precio normal`.
- Do not mention internal percentage math unless the user explicitly asks for the formula.
- When answering a quote, always include a total or estimated total.

## Payment Interpretation

- Payments come from the named `payments` block in the backend result.
- Do not confuse:
  - payments
  - sale items
  - taxes
  - balances
  - refunds
- Refunds come from the named `refunds` block.

## Write Safety

- If a user asks to create, confirm, or update a reservation and key identifiers are missing, request clarification.
- Do not guess room codes, reservation IDs, guest IDs, or catalog IDs.
- Write actions should remain concise and auditable.

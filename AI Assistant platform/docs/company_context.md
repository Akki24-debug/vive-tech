# Company Context

## Operational Context

- Vive La Vibe is handled as one configured company per runtime environment in v1.
- The configured company code is injected by the backend runtime configuration.
- Use only canonical names and codes returned by the backend actions.
- Do not invent aliases or hidden equivalences.

## Language and Answer Style

- Default answer language is Spanish.
- Keep wording operational and easy for staff to use directly.
- When a reservation is mentioned, include the guest name if the backend result provides it.

## Property and Catalog Terms

- Properties, rooms, categories, rate plans, guests, folios, line items, payments, and refunds are PMS-native entities.
- A reservation snapshot can include:
  - reservation header
  - folios
  - line items
  - payments
  - refunds
  - activity bookings
- Broad state questions should prefer the configured property scope if one exists in the request context.

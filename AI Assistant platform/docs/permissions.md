# Permissions

The backend is the final authority, but these are the permission codes expected by the action catalog.

## Read Permissions

- `assistant.read.availability`
- `assistant.read.pricing`
- `assistant.read.properties`
- `assistant.read.guests`
- `assistant.read.catalog`
- `assistant.read.reservations`
- `assistant.read.operations`

## Write Permissions

- `assistant.write.reservations`

## Operational Notes

- Roles `owner` and `admin` bypass the explicit permission list in the current backend policy.
- WhatsApp test traffic can be given explicit roles and permissions through runtime configuration.
- In `hybrid` mode:
  - reads execute automatically
  - writes require approval
- In `manual` mode:
  - every executable action requires approval

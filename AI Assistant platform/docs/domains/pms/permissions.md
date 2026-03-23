# Permissions

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
- In `hybrid` mode:
  - reads execute automatically
  - writes require approval
- In `manual` mode:
  - every executable action requires approval

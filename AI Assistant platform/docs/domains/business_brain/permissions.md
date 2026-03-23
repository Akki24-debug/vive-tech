# Permissions

## Read Permissions

- `brain.read.context`
- `brain.read.organization`
- `brain.read.users`
- `brain.read.roles`
- `brain.read.business_areas`
- `brain.read.business_lines`
- `brain.read.priorities`
- `brain.read.objectives`
- `brain.read.integrations`
- `brain.read.knowledge`

## Write Permissions

- `brain.write.organization`
- `brain.write.users`
- `brain.write.roles`
- `brain.write.business_areas`
- `brain.write.business_lines`
- `brain.write.priorities`
- `brain.write.objectives`
- `brain.write.integrations`
- `brain.write.knowledge`

## Operational Notes

- Roles `owner` and `admin` bypass explicit permission checks in the current backend policy.
- In `hybrid` mode:
  - reads execute automatically
  - writes require approval
- In `manual` mode:
  - every executable action requires approval

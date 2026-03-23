# Test Chat Runbook

## Purpose

The admin `Test Chat` view is the fastest way to validate:

- prompt behavior
- action selection
- stored procedure wiring
- approvals
- final response formatting

## Recommended defaults

- Channel: `admin`
- Roles CSV: `admin`
- Property scope: optional, use when testing one property only
- PMS Actor User ID: a real `app_user.id_user` that is allowed to operate in the PMS

## Suggested test sequence

### Read actions

1. Availability:
   - ask for rooms for a date, nights, and number of people
2. Reservation lookup:
   - ask for the status of a reservation or operational state
3. Property lookup:
   - ask which rooms or categories belong to a property

### Write actions

1. Create hold
2. Confirm hold
3. Update reservation

All writes should:

- create an approval in `hybrid` or `manual`
- stay pending until an operator approves
- write execution logs

## What to inspect

- Assistant action chosen
- Approval ID when applicable
- Recent logs
- Conversation history
- Final response wording in Spanish

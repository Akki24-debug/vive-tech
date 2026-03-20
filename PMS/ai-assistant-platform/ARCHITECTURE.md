# Architecture Notes

## Runtime components

### Backend

- `apps/backend/src/ai`
  - proposal phase
  - final response phase
- `apps/backend/src/actions`
  - catalog of allowed actions
  - policy validation
  - execution service for procedure-backed and composite actions
- `apps/backend/src/db`
  - MariaDB pool
  - stored procedure executor
- `apps/backend/src/channels/whatsapp`
  - Meta Cloud webhook verification
  - inbound message adapter
  - outbound replies
- `apps/backend/src/conversations`
  - persistent conversation store
- `apps/backend/src/approvals`
  - manual approval state

### Admin UI

- `setup`
- `test chat`
- `approvals`
- `docs`
- `logs`

## Execution flow

1. A channel sends a message to `/api/assistant/messages` or `/api/whatsapp/webhook`.
2. The backend stores the user turn in the conversation store.
3. The proposal layer loads the action catalog and Markdown docs.
4. OpenAI returns one structured action proposal.
5. The policy layer validates:
   - tenant
   - permissions
   - execution mode
   - action schema
6. The execution layer:
   - calls one stored procedure directly, or
   - builds a composite snapshot from multiple allowed procedures
7. The response layer turns the named backend result into operator-facing Spanish output.
8. The result is appended to the conversation and written to logs.
9. If approval is required, the action is stored and waits in the queue.

## Important design choices

- No web browsing.
- No model-generated SQL.
- PMS stored procedures remain the source of truth.
- Node only validates, routes, executes, and formats.
- Write actions are approval-gated in `hybrid` and `manual`.
- WhatsApp and admin chat share the same orchestration path.

## Action shapes

The backend currently supports:

- direct SP actions
- post-processed SP actions with named recordset output
- composite read actions like `operations.current_state`

The named result shape is intentional: it gives the model much cleaner data than raw indexed recordsets.

## Identity model

The runtime separates:

- `userId`: channel identity, such as admin console session or WhatsApp phone
- `actorUserId`: numeric PMS `app_user.id_user` used for SP authorization and auditing

This avoids mixing transport identity with PMS operator identity.

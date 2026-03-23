# VLV AI Assistant Platform

Node/TypeScript orchestration server for Vive La Vibe operational domains.

This project is the AI control plane that sits between channels like admin chat or WhatsApp and the stored procedures exposed by the active runtime target. The model never connects directly to MariaDB and never writes SQL.

## What this v1 includes

- Express backend using OpenAI Responses API
- Stored-procedure action registry with approval flow
- Explicit target routing between:
  - `business_brain`
  - `pms`
- Admin UI with:
  - setup and secret management
  - connection tests
  - test chat console
  - approvals
  - docs status
  - logs
- WhatsApp Meta Cloud webhook adapter
- Local runtime persistence for config, approvals, logs, and conversations

## Main runtime flow

1. A message arrives from admin chat or WhatsApp.
2. The backend resolves the request target and loads only the Markdown docs and runtime context for that target.
3. OpenAI proposes one allowed action from the target-specific catalog.
4. The backend validates permissions, execution mode, and arguments.
5. The backend executes the mapped stored procedure or composite snapshot action for that target.
6. The backend generates the final Spanish operational response.
7. If the action is write mode and approval is required, it waits in the approval queue.

## Project layout

```text
AI Assistant platform/
  apps/
    backend/     Express API, actions, approvals, OpenAI, WhatsApp
    admin-ui/    React admin console with setup, chat, approvals, logs
  packages/
    shared/      Shared TypeScript contracts
  docs/
    shared/      Markdown loaded for every target
    domains/     Target-specific Markdown operating context
  storage/       Local runtime files: config, approvals, conversations, logs
```

## Required runtime inputs

You will enter most operational values through the admin UI.

### `.env`

Copy `.env.example` to `.env` and set:

- `APP_ENCRYPTION_KEY`
- `PORT`

### Admin UI runtime configuration

Save these through the setup screen:

- Default target
- Per target runtime:
  - enabled flag
  - docs directory
  - company code
  - default locale
  - default property code
  - default actor user id
  - WhatsApp actor user id
  - WhatsApp roles CSV
  - WhatsApp permissions CSV
  - MariaDB host/port/user/password/database
- OpenAI:
  - API key
  - model
  - base URL if needed
- WhatsApp Meta Cloud:
  - base URL
  - phone number id
  - business account id
  - API token
  - app secret
  - webhook verify token

## Development

From this directory:

```bash
npm install
npm run dev:backend
npm run dev:admin
```

## Key files to customize

- Shared docs for all targets:
  - `docs/shared/platform_overview.md`
  - `docs/shared/target_routing.md`
- Target docs for the model:
  - `docs/domains/business_brain/*`
  - `docs/domains/pms/*`
- Action catalog:
  - `apps/backend/src/actions/action-registry.ts`
- Admin test chat:
  - `apps/admin-ui/src/features/chat/ChatPanel.tsx`

## More docs

- [ARCHITECTURE.md](./ARCHITECTURE.md)
- [docs/shared/server_operations_guide.md](./docs/shared/server_operations_guide.md)
- [docs/domains/business_brain/brain_control_panel.md](./docs/domains/business_brain/brain_control_panel.md)
- [SETUP_LOCAL.md](./SETUP_LOCAL.md)
- [WHATSAPP_META_CLOUD.md](./WHATSAPP_META_CLOUD.md)
- [DEPLOY_HOSTINGER.md](./DEPLOY_HOSTINGER.md)
- [TESTING_CHAT.md](./TESTING_CHAT.md)

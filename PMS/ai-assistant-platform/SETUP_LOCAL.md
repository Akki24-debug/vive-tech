# Local Setup

## 1. Environment

Create `.env` from `.env.example`:

```env
APP_ENCRYPTION_KEY=replace-with-a-long-random-secret
PORT=3001
```

## 2. Install dependencies

```bash
npm install
```

## 3. Start services

Backend:

```bash
npm run dev:backend
```

Admin UI:

```bash
npm run dev:admin
```

## 4. Save runtime configuration

Open the admin UI and fill:

- Assistant runtime
- MariaDB credentials
- OpenAI key/model
- WhatsApp Meta Cloud test credentials

## 5. Test connections

Use the Setup screen buttons:

- Test Database
- Test OpenAI
- Test WhatsApp

## 6. Use the test chat

Open the `Test Chat` view and verify:

- read queries return PMS data
- write queries create approvals
- approvals can be executed from the Approvals view

# Hostinger Deployment Notes

## Target shape

Use the same Node server that you tested locally.

Deploy:

- backend Node app
- admin UI static build
- shared runtime storage directory

## Minimum requirements

- Node.js version compatible with the project toolchain
- access to MariaDB reachable from the server
- HTTPS for the WhatsApp webhook
- writable filesystem for:
  - `storage/runtime/config`
  - `storage/runtime/approvals`
  - `storage/runtime/conversations`
  - `storage/runtime/logs`

## Deployment steps

1. Upload project files or deploy from git.
2. Run `npm install`.
3. Set environment variables:
   - `APP_ENCRYPTION_KEY`
   - `PORT`
4. Build the project:
   - `npm run build`
5. Start the backend with a Node process manager.
6. Serve the admin UI build as static files.
7. Configure reverse proxy rules to expose:
   - backend API
   - admin UI
   - WhatsApp webhook

## Operational caution

- The runtime config file contains encrypted secrets, but the encryption key must still be protected.
- Back up `storage/runtime` if you want to preserve approvals, logs, and conversations.
- If you deploy multiple instances later, local filesystem storage should be replaced by shared persistence.

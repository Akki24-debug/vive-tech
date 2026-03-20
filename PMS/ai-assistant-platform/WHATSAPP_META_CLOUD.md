# WhatsApp Meta Cloud Setup

## Values you need from Meta

- Phone Number ID
- Business Account ID
- Permanent or temporary API token
- Webhook verify token that you choose
- App Secret if you want signature validation later

## Runtime config fields

In the admin UI setup page, fill:

- WhatsApp Base URL
  - usually `https://graph.facebook.com/v22.0/`
- Phone Number ID
- Business Account ID
- API Token
- App Secret
- Webhook Verify Token

Also fill the Assistant runtime fields for WhatsApp:

- WhatsApp Actor User ID
- WhatsApp Roles CSV
- WhatsApp Permissions CSV

Those values define which PMS operator identity and permission set the test WhatsApp channel will use.

## Webhook endpoint

- Verification:
  - `GET /api/whatsapp/webhook`
- Messages:
  - `POST /api/whatsapp/webhook`

## Recommended test behavior

- Use `hybrid` mode.
- Keep read permissions enabled.
- Keep writes approval-gated.
- Verify that a WhatsApp write request:
  - creates a pending approval
  - sends a pending notice back to WhatsApp
  - sends the final confirmation or rejection after operator review

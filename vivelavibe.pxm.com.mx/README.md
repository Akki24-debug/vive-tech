Vive la Vibe — deployment notes

Domain
- Production domain: https://www.vivelavibe.pxm.com.mx

Layout on Hostinger
- Pages (public site) live at the domain root: `/public_html`
  - Files: `index.html`, `contacto.html`, `actividades.html`
  - Shared assets: `resources.js`, `texts.js`, `theme.js`, `page.css`
  - Page controllers: `page-contact.js`, `page-activities.js`
- Backend endpoint + helpers live under a folder with the same name as this repo:
  - Folder: `/public_html/pagina vive la vibe/`
  - Files: `get-hostings.php`, `get-activities.php`, `search-availability.php`
- Database connection helper (separate repo/folder):
  - Folder: `/public_html/main domain/pms db connections/`
  - Files: `connection.php`, `config.php`, `test-connection.php`, etc.

Endpoint pathing
- The frontend fetches hostings from: `/get-hostings.php?code=VIBE`
- Activities are fetched from: `/get-activities.php?code=VIBE`
- You can override via `window.VIVE_CREDENTIALS.hostingsEndpoint` and `window.VIVE_CREDENTIALS.activitiesEndpoint`.
- Shopping cart UI (`cart.js`) injects the “Mi viaje” button automatically and persists selections client-side.

Images
- Property lobby images: `/public_html/images/{PROPERTYCODE}/`
- Room/category images: `/public_html/images/{PROPERTYCODE}/{CATEGORYCODE}/`
- `get-hostings.php` aggregates those folders into the JSON payload (`lobbyImages`, `roomTypes[].images`, and `images`).

Availability & reservas
- Stored procedures: `bd pms/sp_search_availability.sql`, `bd pms/sp_create_reservation.sql`, `bd pms/sp_property_room_calendar.sql`
- Frontend calls: `/pagina vive la vibe/search-availability.php` via `apiClient.js` (POST `{code,checkIn,nights,people}`)

- Server prerequisites
- Create/actualiza los procedimientos almacenados listados en `bd pms/` segun corresponda (`sp_get_company_properties`, `sp_get_company_activities`, `sp_search_availability`, `sp_create_reservation`, `sp_property_room_calendar`).
- Verifica `main domain/pms db connections/config.php` con credenciales validas y permisos EXECUTE/SELECT.

Smoke tests
- DB connectivity: `https://www.vivelavibe.pxm.com.mx/main%20domain/pms%20db%20connections/test-connection.php`
- API endpoint: `https://www.vivelavibe.pxm.com.mx/pagina%20vive%20la%20vibe/get-hostings.php?code=VIBE`

Notes
- `get-hostings.php` y `get-activities.php` buscan `main domain/pms db connections/connection.php` en su propia carpeta, la carpeta padre y el document root. Si falta, devuelven un error JSON claro en lugar de un HTTP 500 generico.

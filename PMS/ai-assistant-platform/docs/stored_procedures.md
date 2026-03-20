# Stored Procedures

This file documents the allowed backend actions and the PMS procedures behind them.
The assistant must only choose from these actions.

## Read Actions

### `availability.search`

- Procedure: `sp_search_availability`
- Inputs:
  - `checkIn`
  - `nights`
  - `people`
  - optional `propertyCode`
  - optional `categoryCode`
- Company code comes from runtime config, not from the user.
- Output shape:
  - `availability`
  - `totalMatches`
  - `filtersApplied`

### `pricing.quote`

- Procedure: `sp_rateplan_calc_total`
- Inputs:
  - `propertyId`
  - `rateplanId`
  - optional `roomId`
  - optional `categoryId`
  - `checkIn`
  - `checkOut`
- Output shape:
  - `quote.totalCents`
  - `quote.avgNightlyCents`
  - `quote.breakdown`

### `property.lookup`

- Procedure: `sp_portal_property_data`
- Inputs:
  - optional `search`
  - optional `propertyCode`
  - optional `onlyActive`
- Output shape:
  - `properties`
  - `propertyDetail`
  - `rateplans`
  - `categories`
  - `rooms`
  - `bedConfigurations`

### `guest.lookup`

- Procedure: `sp_portal_guest_data`
- Inputs:
  - optional `search`
  - optional `guestId`
  - optional `onlyActive`
- Actor user id is enforced by backend context.
- Output shape:
  - `guests`
  - `guestDetail`
  - `reservations`
  - `activityBookings`

### `catalog.lookup`

- Procedure: `sp_sale_item_catalog_data`
- Inputs:
  - optional `propertyCode`
  - optional `itemId`
  - optional `categoryId`
  - optional `includeInactive`
- Output shape:
  - `catalogItems`
  - `catalogItemDetail`

### `reservation.lookup`

- Procedure: `sp_portal_reservation_data`
- Inputs:
  - optional `propertyCode`
  - optional `status`
  - optional `from`
  - optional `to`
  - optional `reservationId`
  - optional `reservationCode`
- If `reservationCode` is provided, the backend first resolves it through the reservation list and then loads the detailed result.
- Output shape:
  - `reservations`
  - `reservationDetail`
  - `folios`
  - `lineItems`
  - `payments`
  - `refunds`
  - `activityBookings`
  - `reservationInterests`

### `operations.current_state`

- Procedures:
  - `sp_portal_property_data`
  - `sp_portal_reservation_data`
- Inputs:
  - optional `propertyCode`
  - optional `status`
  - optional `from`
  - optional `to`
  - optional `reservationId`
  - optional `reservationCode`
- Use this only for broad operational-state questions.
- Output shape:
  - `properties`
  - `reservations`

## Write Actions

### `reservation.create_hold`

- Procedure: `sp_create_reservation_hold`
- Inputs:
  - `roomCode`
  - `checkIn`
  - `checkOut`
  - optional `propertyCode`
  - optional `totalCentsOverride`
  - optional `notes`
- Property code may come from request scope when already fixed by the operator.
- Approval: yes in `hybrid` or `manual`

### `reservation.confirm_hold`

- Procedure: `sp_reservation_confirm_hold`
- Inputs:
  - `reservationId`
  - `guestId`
  - `lodgingCatalogId`
  - optional `totalCentsOverride`
  - `adults`
  - `children`
- Approval: yes in `hybrid` or `manual`

### `reservation.update`

- Procedure: `sp_reservation_update_v2`
- Inputs:
  - `reservationId`
  - `status`
  - `source`
  - optional `otaAccountId`
  - `roomCode`
  - `checkInDate`
  - `checkOutDate`
  - `adults`
  - `children`
  - optional `reservationCode`
  - optional `internalNotes`
  - optional `guestNotes`
- Approval: yes in `hybrid` or `manual`

## Important Interpretation Rules

- The assistant must rely on the named output blocks, not on recordset positions.
- Payments must come from `payments`.
- Refunds must come from `refunds`.
- Reservation monetary context can also involve folios and line items.
- If the required identifier is missing for a write action, the assistant must clarify before execution.

# Stored Procedures

This file documents the allowed backend actions and PMS procedures behind them.
The assistant must only choose from the `pms` catalog.

## Read Actions

### `availability.search`

- Procedure: `sp_search_availability`
- Inputs: `checkIn`, `nights`, `people`, optional `propertyCode`, optional `categoryCode`

### `pricing.quote`

- Procedure: `sp_rateplan_calc_total`
- Inputs: `propertyId`, `rateplanId`, optional `roomId`, optional `categoryId`, `checkIn`, `checkOut`

### `property.lookup`

- Procedure: `sp_portal_property_data`
- Inputs: optional `search`, optional `propertyCode`, optional `onlyActive`

### `guest.lookup`

- Procedure: `sp_portal_guest_data`
- Inputs: optional `search`, optional `guestId`, optional `onlyActive`

### `catalog.lookup`

- Procedure: `sp_sale_item_catalog_data`
- Inputs: optional `propertyCode`, optional `itemId`, optional `categoryId`, optional `includeInactive`

### `reservation.lookup`

- Procedure: `sp_portal_reservation_data`
- Inputs: optional `propertyCode`, optional `status`, optional `from`, optional `to`, optional `reservationId`, optional `reservationCode`

### `operations.current_state`

- Composite action built from:
  - `sp_portal_property_data`
  - `sp_portal_reservation_data`

## Write Actions

### `reservation.create_hold`

- Procedure: `sp_create_reservation_hold`
- Approval: yes in `hybrid` or `manual`

### `reservation.confirm_hold`

- Procedure: `sp_reservation_confirm_hold`
- Approval: yes in `hybrid` or `manual`

### `reservation.update`

- Procedure: `sp_reservation_update_v2`
- Approval: yes in `hybrid` or `manual`

## Status Note

- PMS remains supported by the dual-target backend.
- Final operational reconnection and validation are tracked separately from the current Business Brain rollout.

-- Reservaciones canceladas ordenadas de la mas reciente a la mas antigua.
-- Orden principal: canceled_at DESC
-- Fallback: updated_at DESC, created_at DESC

SELECT
    r.id_reservation,
    r.code AS reservation_code,
    r.status,
    r.source,
    p.code AS property_code,
    p.name AS property_name,
    rm.code AS room_code,
    rm.name AS room_name,
    g.id_guest,
    COALESCE(NULLIF(g.full_name, ''), TRIM(CONCAT_WS(' ', g.names, g.last_name, g.maiden_name))) AS guest_name,
    g.email AS guest_email,
    g.phone AS guest_phone,
    r.check_in_date,
    r.check_out_date,
    r.adults,
    r.children,
    r.currency,
    r.total_price_cents,
    r.balance_due_cents,
    r.cancel_reason,
    r.canceled_at,
    r.updated_at,
    r.created_at
FROM reservation AS r
LEFT JOIN property AS p
    ON p.id_property = r.id_property
LEFT JOIN room AS rm
    ON rm.id_room = r.id_room
LEFT JOIN guest AS g
    ON g.id_guest = r.id_guest
WHERE r.status = 'cancelada'
  AND r.deleted_at IS NULL
ORDER BY
    COALESCE(r.canceled_at, r.updated_at, r.created_at) DESC,
    r.id_reservation DESC;

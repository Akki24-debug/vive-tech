DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_reservation_totals_report` $$
CREATE PROCEDURE `sp_reservation_totals_report` (
  IN p_company_code VARCHAR(100),
  IN p_reservation_ids TEXT
)
BEGIN
  DECLARE v_company_id BIGINT;

  IF p_company_code IS NULL OR p_company_code = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company code is required';
  END IF;

  SELECT id_company INTO v_company_id
  FROM company
  WHERE code = p_company_code
  LIMIT 1;
  IF v_company_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown company';
  END IF;

  SELECT
    r.id_reservation,
    COALESCE(charges.total, 0) AS charges_cents,
    COALESCE(taxes.total, 0) AS taxes_cents,
    COALESCE(payments.total, 0) AS payments_cents,
    COALESCE(obligations.total, 0) AS obligations_cents,
    COALESCE(incomes.total, 0) AS incomes_cents
  FROM reservation r
  JOIN property p ON p.id_property = r.id_property
  LEFT JOIN (
    SELECT f.id_reservation, COALESCE(SUM(li.amount_cents),0) AS total
    FROM folio f
    JOIN line_item li ON li.id_folio = f.id_folio
    WHERE li.item_type = 'sale_item'
      AND li.deleted_at IS NULL
      AND li.is_active = 1
    GROUP BY f.id_reservation
  ) charges ON charges.id_reservation = r.id_reservation
  LEFT JOIN (
    SELECT f.id_reservation, COALESCE(SUM(ti.amount_cents),0) AS total
    FROM line_item ti
    JOIN folio f ON f.id_folio = ti.id_folio
    WHERE ti.item_type = 'tax_item'
      AND ti.deleted_at IS NULL
      AND ti.is_active = 1
    GROUP BY f.id_reservation
  ) taxes ON taxes.id_reservation = r.id_reservation
  LEFT JOIN (
    SELECT f.id_reservation, COALESCE(SUM(li.amount_cents),0) AS total
    FROM line_item li
    JOIN folio f ON f.id_folio = li.id_folio
    WHERE li.item_type = 'payment'
      AND li.deleted_at IS NULL
      AND li.is_active = 1
    GROUP BY f.id_reservation
  ) payments ON payments.id_reservation = r.id_reservation
  LEFT JOIN (
    SELECT f.id_reservation, COALESCE(SUM(li.amount_cents),0) AS total
    FROM line_item li
    JOIN folio f ON f.id_folio = li.id_folio
    WHERE li.item_type = 'obligation'
      AND li.deleted_at IS NULL
      AND li.is_active = 1
    GROUP BY f.id_reservation
  ) obligations ON obligations.id_reservation = r.id_reservation
  LEFT JOIN (
    SELECT f.id_reservation, COALESCE(SUM(li.amount_cents),0) AS total
    FROM line_item li
    JOIN folio f ON f.id_folio = li.id_folio
    WHERE li.item_type = 'income'
      AND li.deleted_at IS NULL
      AND li.is_active = 1
    GROUP BY f.id_reservation
  ) incomes ON incomes.id_reservation = r.id_reservation
  WHERE p.id_company = v_company_id
    AND (p_reservation_ids IS NULL OR p_reservation_ids = '' OR FIND_IN_SET(r.id_reservation, p_reservation_ids));
END $$

DELIMITER ;

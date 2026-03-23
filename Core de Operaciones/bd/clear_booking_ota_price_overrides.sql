START TRANSACTION;

SELECT
  oa.id_ota_account,
  oa.ota_name,
  p.code AS property_code,
  COUNT(*) AS active_override_count
FROM ota_price_override opo
JOIN ota_account oa
  ON oa.id_ota_account = opo.id_ota_account
 AND oa.deleted_at IS NULL
LEFT JOIN property p
  ON p.id_property = oa.id_property
WHERE opo.is_active = 1
  AND LOWER(TRIM(COALESCE(oa.ota_name, ''))) = 'booking'
GROUP BY oa.id_ota_account, oa.ota_name, p.code
ORDER BY p.code, oa.id_ota_account;

UPDATE ota_price_override opo
JOIN ota_account oa
  ON oa.id_ota_account = opo.id_ota_account
 AND oa.deleted_at IS NULL
SET opo.is_active = 0,
    opo.updated_at = NOW()
WHERE opo.is_active = 1
  AND LOWER(TRIM(COALESCE(oa.ota_name, ''))) = 'booking';

SELECT ROW_COUNT() AS overrides_deactivated;

COMMIT;

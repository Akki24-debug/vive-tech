# Direct DB Calls Audit (Modules)

Last update: 2026-03-03

## Scope
- Search scope: `public_html/modules/*.php`
- Pattern used: `pms_get_connection(`, `->prepare(`, `->query(`, `->exec(`
- Goal: detectar codigo con acceso directo a BD en lugar de SP.

## Summary

| Module | Direct DB hits | SP calls |
|---|---:|---:|
| reservations.php | 101 | 16 |
| reservation_wizard.php | 77 | 17 |
| settings.php | 41 | 10 |
| rateplans.php | 40 | 2 |
| sale_items.php | 38 | 8 |
| calendar.php | 28 | 13 |
| otas.php | 20 | 3 |
| categories.php | 15 | 2 |
| activities.php | 12 | 4 |
| rooms.php | 12 | 2 |
| dashboard.php | 12 | 3 |
| incomes.php | 11 | 1 |
| app_users.php | 8 | 2 |
| line_item_report.php | 7 | 0 |
| messages.php | 5 | 2 |
| reports.php | 5 | 12 |
| sale_item_report.php | 5 | 1 |
| obligations.php | 4 | 2 |
| payments.php | 4 | 0 |
| properties.php | 4 | 2 |
| guests.php | 3 | 2 |
| ota_ical.php | 3 | 0 |

## Detailed Evidence (sample lines)

### reservations.php
- Direct DB hits: 101
- L13: $reservationsRateplanPricingService = new RateplanPricingService(pms_get_connection());
- L216: $pdo = pms_get_connection();
- L217: $stmt = $pdo->query("SHOW COLUMNS FROM ota_account_info_catalog LIKE 'display_alias'");
- L224: $pdo = pms_get_connection();
- L225: $stmt = $pdo->prepare(
- L285: $pdo = pms_get_connection();
- L287: $stmt = $pdo->prepare(
- L343: $pdo = pms_get_connection();
- ... +93 lines more in this module.

### reservation_wizard.php
- Direct DB hits: 77
- L13: $rwRateplanPricingService = new RateplanPricingService(pms_get_connection());
- L87: $pdo = pms_get_connection();
- L129: $stmt = $pdo->prepare($sql);
- L148: $pdo = pms_get_connection();
- L189: $stmt = $pdo->prepare($sql);
- L231: $pdo = pms_get_connection();
- L233: $stmtCompany = $pdo->prepare('SELECT id_company FROM company WHERE UPPER(code) = UPPER(?) LIMIT 1');
- L241: $stmtProperty = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? AND deleted_at IS NULL LIMIT 1');
- ... +69 lines more in this module.

### settings.php
- Direct DB hits: 41
- L22: $stmt = $pdo->query("SHOW COLUMNS FROM line_item_catalog LIKE 'rate_percent'");
- L37: $pdo = pms_get_connection();
- L64: $stmt = $pdo->prepare($sql);
- L99: $pdo = pms_get_connection();
- L100: $stmtRoots = $pdo->prepare(
- L142: $stmtDelete = $pdo->prepare(
- L377: $pdo = pms_get_connection();
- L387: $stmtDuplicate = $pdo->prepare(
- ... +33 lines more in this module.

### rateplans.php
- Direct DB hits: 40
- L83: $pdo = pms_get_connection();
- L84: $stmt = $pdo->prepare(
- L115: $pdo = isset($pdo) ? $pdo : pms_get_connection();
- L116: $stmt = $pdo->prepare('SELECT id_rateplan FROM rateplan WHERE id_property = ? AND code = ? AND deleted_at IS NULL LIMIT 1');
- L121: $pdo = isset($pdo) ? $pdo : pms_get_connection();
- L122: $stmt = $pdo->prepare(
- L131: $pdo = isset($pdo) ? $pdo : pms_get_connection();
- L132: $stmt = $pdo->prepare(
- ... +32 lines more in this module.

### sale_items.php
- Direct DB hits: 38
- L25: $pdo = pms_get_connection();
- L87: $stmt = $pdo->prepare($sql);
- L663: $pdo = pms_get_connection();
- L665: $parentStmt = $pdo->prepare(
- L685: $existingStmt = $pdo->prepare(
- L737: $stmtValid = $pdo->prepare($sql);
- L751: $stmtDeactivateLinks = $pdo->prepare(
- L763: $stmtDeactivateCalc = $pdo->prepare(
- ... +30 lines more in this module.

### calendar.php
- Direct DB hits: 28
- L12: $rateplanPricingService = new RateplanPricingService(pms_get_connection());
- L327: $pdo = pms_get_connection();
- L328: $stmt = $pdo->prepare(
- L605: $pdo = pms_get_connection();
- L606: $stmt = $pdo->prepare(
- L639: $pdo = pms_get_connection();
- L640: $stmt = $pdo->prepare(
- L698: $pdo = pms_get_connection();
- ... +20 lines more in this module.

### otas.php
- Direct DB hits: 20
- L38: $pdo = pms_get_connection();
- L39: $stmt = $pdo->query("SHOW COLUMNS FROM ota_account_info_catalog LIKE 'display_alias'");
- L158: $pdoColor = pms_get_connection();
- L160: $stmtColor = $pdoColor->prepare(
- L180: $pdoInfo = pms_get_connection();
- L181: $stmtDeactivate = $pdoInfo->prepare(
- L193: $stmtUpsert = $pdoInfo->prepare(
- L203: $stmtInsert = $pdoInfo->prepare(
- ... +12 lines more in this module.

### categories.php
- Direct DB hits: 15
- L178: $pdo = pms_get_connection();
- L189: $stmt = $pdo->prepare($duplicateSql);
- L263: $pdo = pms_get_connection();
- L264: $stmt = $pdo->prepare(
- L276: $stmt = $pdo->prepare(
- L301: $pdo = pms_get_connection();
- L302: $stmt = $pdo->prepare(
- L330: $pdo = pms_get_connection();
- ... +7 lines more in this module.

### activities.php
- Direct DB hits: 12
- L47: $pdo = pms_get_connection();
- L48: $stmt = $pdo->prepare(
- L91: $pdo = pms_get_connection();
- L92: $stmt = $pdo->prepare(
- L110: $pdo = pms_get_connection();
- L111: $stmt = $pdo->prepare(
- L166: $pdo = pms_get_connection();
- L167: $stmt = $pdo->prepare(
- ... +4 lines more in this module.

### rooms.php
- Direct DB hits: 12
- L33: $pdoCategoryCatalog = pms_get_connection();
- L34: $stmtCategoryCatalog = $pdoCategoryCatalog->prepare(
- L126: $pdo = pms_get_connection();
- L127: $stmt = $pdo->prepare(
- L164: $pdo = pms_get_connection();
- L175: $stmt = $pdo->prepare($duplicateSql);
- L271: $pdo = pms_get_connection();
- L272: $stmt = $pdo->prepare(
- ... +4 lines more in this module.

### dashboard.php
- Direct DB hits: 12
- L7: $pdo = pms_get_connection();
- L267: $stmt = $pdo->prepare(
- L444: $stmt = $pdo->prepare(
- L455: $stmt = $pdo->prepare(
- L468: $stmt = $pdo->prepare(
- L481: $stmt = $pdo->prepare(
- L494: $stmt = $pdo->prepare(
- L548: $stmt = $pdo->prepare($upcomingSql);
- ... +4 lines more in this module.

### incomes.php
- Direct DB hits: 11
- L56: $pdo = pms_get_connection();
- L71: $stmtMethod = $pdo->prepare(
- L86: $stmtLine = $pdo->prepare(
- L121: $stmtUpdate = $pdo->prepare('UPDATE line_item SET paid_cents = ?, updated_at = NOW() WHERE id_line_item = ? AND deleted_at IS NULL AND is_active = 1');
- L125: $stmtLog = $pdo->prepare(
- L203: $pdo = pms_get_connection();
- L204: $stmtMethods = $pdo->prepare(
- L288: $pdo = pms_get_connection();
- ... +3 lines more in this module.

### app_users.php
- Direct DB hits: 8
- L27: $pdo = pms_get_connection();
- L31: $pdo->prepare(
- L40: $stmt = $pdo->prepare(
- L48: $insert = $pdo->prepare(
- L82: $pdo = pms_get_connection();
- L86: $pdo->prepare(
- L95: $stmt = $pdo->prepare(
- L109: $insert = $pdo->prepare(

### line_item_report.php
- Direct DB hits: 7
- L247: $pdo = pms_get_connection();
- L250: $stmtProperty = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? AND deleted_at IS NULL LIMIT 1');
- L261: $stmtCategory = $pdo->prepare($sqlCategory);
- L295: $stmtCatalog = $pdo->prepare($sqlCatalog);
- L329: $stmtReservation = $pdo->prepare($sqlReservation);
- L347: $stmtMethods = $pdo->prepare($sqlMethods);
- L647: $stmtMain = $pdo->prepare($sql);

### messages.php
- Direct DB hits: 5
- L98: $pdo = pms_get_connection();
- L112: $stmt = $pdo->prepare($templateSql);
- L145: $pdo = pms_get_connection();
- L152: $stmt = $pdo->prepare(
- L185: $stmt = $pdo->prepare(

### reports.php
- Direct DB hits: 5
- L177: $pdo = pms_get_connection();
- L179: $stmtRate = $pdo->query("SHOW COLUMNS FROM line_item_catalog LIKE 'rate_percent'");
- L203: $stmt = $pdo->prepare($sql);
- L378: $pdo = pms_get_connection();
- L405: $stmt = $pdo->prepare($sql);

### sale_item_report.php
- Direct DB hits: 5
- L49: $pdo = pms_get_connection();
- L52: $stmt = $pdo->prepare('SELECT id_property FROM property WHERE id_company = ? AND code = ? LIMIT 1');
- L65: $stmt = $pdo->prepare($sql);
- L98: $stmt = $pdo->prepare($sql);
- L189: $stmtRes = $pdo->prepare(

### obligations.php
- Direct DB hits: 4
- L229: $pdo = pms_get_connection();
- L230: $stmtObligationMethods = $pdo->prepare(
- L307: $pdo = pms_get_connection();
- L318: $stmtPaymentLog = $pdo->prepare(

### payments.php
- Direct DB hits: 4
- L97: $pdo = pms_get_connection();
- L126: $stmtMethods = $pdo->prepare($sqlMethods);
- L131: $stmtStatus = $pdo->prepare($sqlStatus);
- L224: $stmt = $pdo->prepare($sql);

### properties.php
- Direct DB hits: 4
- L48: $pdo = pms_get_connection();
- L49: $stmt = $pdo->prepare(
- L99: $pdo = pms_get_connection();
- L100: $stmt = $pdo->prepare(

### guests.php
- Direct DB hits: 3
- L42: $pdo = pms_get_connection();
- L43: $stmt = $pdo->prepare(
- L76: $verify = $pdo->prepare('SELECT id_guest FROM guest WHERE id_guest = ? LIMIT 1');

### ota_ical.php
- Direct DB hits: 3
- L18: $db = pms_get_connection();
- L147: $stmt = $db->prepare(
- L158: $stmt = $db->prepare(

## Suggested Prioritization
1. `reservations.php` and `reservation_wizard.php` (highest direct SQL surface).
2. `settings.php`, `rateplans.php`, `sale_items.php`, `calendar.php` (high mixed mode).
3. Reporting modules with direct read queries (`line_item_report.php`, `sale_item_report.php`, `payments.php`).

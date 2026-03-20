<?php

require_once __DIR__ . '/report_field_codes.php';

if (!function_exists('reports_v2_tables_ready')) {
    function reports_v2_tables_ready(PDO $pdo)
    {
        $required = array('report_template', 'report_template_field', 'report_calculation');
        $missing = array();
        foreach ($required as $tableName) {
            if (!pms_table_exists($pdo, $tableName)) {
                $missing[] = $tableName;
            }
        }
        return $missing;
    }
}

if (!function_exists('reports_v2_template_field_catalog_table_ready')) {
    function reports_v2_template_field_catalog_table_ready(PDO $pdo)
    {
        return pms_table_exists($pdo, 'report_template_field_catalog');
    }
}

if (!function_exists('reports_v2_field_type_options')) {
    function reports_v2_field_type_options()
    {
        return array(
            'reservation' => 'Reservacion',
            'line_item' => 'Line item',
            'calculated' => 'Calculado',
        );
    }
}

if (!function_exists('reports_v2_metric_options')) {
    function reports_v2_metric_options()
    {
        return array(
            'item_name' => array('label' => 'Nombre', 'data_type' => 'text', 'numeric' => false),
            'item_name_parent' => array('label' => 'Nombre - nombre padre', 'data_type' => 'text', 'numeric' => false),
            'item_type' => array('label' => 'Tipo', 'data_type' => 'text', 'numeric' => false),
            'service_date' => array('label' => 'Fecha servicio', 'data_type' => 'date', 'numeric' => false),
            'amount_cents' => array('label' => 'Monto', 'data_type' => 'currency', 'numeric' => true),
            'paid_cents' => array('label' => 'Cantidad pagada', 'data_type' => 'currency', 'numeric' => true),
            'quantity' => array('label' => 'Cantidad', 'data_type' => 'number', 'numeric' => true),
            'unit_price_cents' => array('label' => 'Precio unitario', 'data_type' => 'currency', 'numeric' => true),
        );
    }
}

if (!function_exists('reports_v2_format_options')) {
    function reports_v2_format_options()
    {
        return array(
            'auto' => 'Auto',
            'text' => 'Texto',
            'date' => 'Fecha',
            'datetime' => 'Fecha y hora',
            'number' => 'Numero',
            'integer' => 'Entero',
            'currency' => 'Moneda',
        );
    }
}

if (!function_exists('reports_v2_h')) {
    function reports_v2_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('reports_v2_post')) {
    function reports_v2_post($key, $default = '')
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }
}

if (!function_exists('reports_v2_fetch_templates')) {
    function reports_v2_fetch_templates(PDO $pdo, $companyId)
    {
        $stmt = $pdo->prepare(
            'SELECT
                rt.id_report_template,
                rt.report_key,
                rt.report_name,
                rt.description,
                rt.row_source,
                rt.is_active,
                COUNT(rtf.id_report_template_field) AS field_count
             FROM report_template rt
             LEFT JOIN report_template_field rtf
               ON rtf.id_report_template = rt.id_report_template
              AND rtf.deleted_at IS NULL
              AND rtf.is_active = 1
             WHERE rt.id_company = ?
               AND rt.deleted_at IS NULL
             GROUP BY
                rt.id_report_template,
                rt.report_key,
                rt.report_name,
                rt.description,
                rt.row_source,
                rt.is_active
             ORDER BY rt.is_active DESC, rt.report_name, rt.id_report_template'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_fetch_template')) {
    function reports_v2_fetch_template(PDO $pdo, $companyId, $templateId)
    {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM report_template
             WHERE id_company = ?
               AND id_report_template = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array((int)$companyId, (int)$templateId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

if (!function_exists('reports_v2_fetch_template_fields')) {
    function reports_v2_fetch_template_fields(PDO $pdo, $templateId)
    {
        $defaultValueSelect = reports_v2_template_field_has_default_value_column($pdo)
            ? 'rtf.default_value'
            : 'NULL AS default_value';
        $allowMultipleCatalogsSelect = reports_v2_template_field_has_allow_multiple_catalogs_column($pdo)
            ? 'rtf.allow_multiple_catalogs'
            : '0 AS allow_multiple_catalogs';
        $stmt = $pdo->prepare(
            'SELECT
                rtf.id_report_template_field,
                rtf.id_report_template,
                rtf.field_type,
                rtf.display_name,
                ' . $allowMultipleCatalogsSelect . ',
                rtf.reservation_field_code,
                rtf.id_line_item_catalog,
                rtf.id_report_calculation,
                rtf.source_metric,
                rtf.format_hint,
                rtf.order_index,
                rtf.is_visible,
                rtf.is_active,
                rtf.deleted_at,
                rtf.created_at,
                rtf.created_by,
                rtf.updated_at,
                ' . $defaultValueSelect . ',
                rc.calc_name,
                rc.format_hint AS calc_format_hint,
                rc.decimal_places,
                lic.item_name AS line_item_name,
                lic.catalog_type AS line_item_type
             FROM report_template_field rtf
             LEFT JOIN report_calculation rc
               ON rc.id_report_calculation = rtf.id_report_calculation
              AND rc.deleted_at IS NULL
             LEFT JOIN line_item_catalog lic
               ON lic.id_line_item_catalog = rtf.id_line_item_catalog
              AND lic.deleted_at IS NULL
             WHERE rtf.id_report_template = ?
               AND rtf.deleted_at IS NULL
             ORDER BY rtf.order_index, rtf.id_report_template_field'
        );
        $stmt->execute(array((int)$templateId));
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return reports_v2_attach_template_field_catalog_links($pdo, $fields);
    }
}

if (!function_exists('reports_v2_template_field_has_default_value_column')) {
    function reports_v2_template_field_has_default_value_column(PDO $pdo)
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?'
            );
            $stmt->execute(array('report_template_field', 'default_value'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_template_field_has_allow_multiple_catalogs_column')) {
    function reports_v2_template_field_has_allow_multiple_catalogs_column(PDO $pdo)
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?'
            );
            $stmt->execute(array('report_template_field', 'allow_multiple_catalogs'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_attach_template_field_catalog_links')) {
    function reports_v2_attach_template_field_catalog_links(PDO $pdo, array $fields)
    {
        if (empty($fields)) {
            return $fields;
        }

        $fieldIds = array();
        $indexByFieldId = array();
        foreach ($fields as $index => $field) {
            $fields[$index]['linked_catalog_ids'] = array();
            $fields[$index]['linked_catalog_labels'] = array();
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            if ($fieldId > 0) {
                $fieldIds[] = $fieldId;
                $indexByFieldId[$fieldId] = $index;
            }
        }

        if (reports_v2_template_field_catalog_table_ready($pdo) && !empty($fieldIds)) {
            $placeholders = implode(',', array_fill(0, count($fieldIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT
                    rtfc.id_report_template_field,
                    rtfc.id_line_item_catalog,
                    rtfc.sort_order,
                    lic.item_name,
                    sic.category_name AS subcategory_name,
                    parent.category_name AS category_name,
                    p.name AS property_name
                 FROM report_template_field_catalog rtfc
                 JOIN line_item_catalog lic
                   ON lic.id_line_item_catalog = rtfc.id_line_item_catalog
                  AND lic.deleted_at IS NULL
                 LEFT JOIN sale_item_category sic
                   ON sic.id_sale_item_category = lic.id_category
                  AND sic.deleted_at IS NULL
                 LEFT JOIN sale_item_category parent
                   ON parent.id_sale_item_category = sic.id_parent_sale_item_category
                  AND parent.deleted_at IS NULL
                 LEFT JOIN property p
                   ON p.id_property = sic.id_property
                  AND p.deleted_at IS NULL
                 WHERE rtfc.id_report_template_field IN (' . $placeholders . ')
                 ORDER BY rtfc.id_report_template_field, rtfc.sort_order, rtfc.id_report_template_field_catalog'
            );
            $stmt->execute($fieldIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $fieldId = isset($row['id_report_template_field']) ? (int)$row['id_report_template_field'] : 0;
                if ($fieldId <= 0 || !isset($indexByFieldId[$fieldId])) {
                    continue;
                }
                $parts = array();
                if (!empty($row['property_name'])) {
                    $parts[] = trim((string)$row['property_name']);
                }
                if (!empty($row['category_name'])) {
                    $parts[] = trim((string)$row['category_name']);
                }
                if (!empty($row['subcategory_name']) && (string)$row['subcategory_name'] !== (string)$row['category_name']) {
                    $parts[] = trim((string)$row['subcategory_name']);
                }
                if (!empty($row['item_name'])) {
                    $parts[] = trim((string)$row['item_name']);
                }
                $index = $indexByFieldId[$fieldId];
                $fields[$index]['linked_catalog_ids'][] = (int)$row['id_line_item_catalog'];
                $fields[$index]['linked_catalog_labels'][] = implode(' / ', array_filter($parts));
            }
        }

        foreach ($fields as $index => $field) {
            if (!empty($fields[$index]['linked_catalog_ids'])) {
                continue;
            }
            $catalogId = isset($field['id_line_item_catalog']) ? (int)$field['id_line_item_catalog'] : 0;
            if ($catalogId > 0) {
                $fields[$index]['linked_catalog_ids'] = array($catalogId);
                $lineItemName = isset($field['line_item_name']) ? trim((string)$field['line_item_name']) : '';
                $fields[$index]['linked_catalog_labels'] = $lineItemName !== '' ? array($lineItemName) : array();
            }
        }

        return $fields;
    }
}

if (!function_exists('reports_v2_value_is_empty_or_zero')) {
    function reports_v2_value_is_empty_or_zero($rawValue, array $meta)
    {
        if ($rawValue === null) {
            return true;
        }
        if (is_numeric($rawValue) && (float)$rawValue == 0.0) {
            return true;
        }
        if (is_string($rawValue)) {
            return trim($rawValue) === '';
        }
        if (empty($meta['numeric']) && $rawValue === '') {
            return true;
        }
        return false;
    }
}

if (!function_exists('reports_v2_field_catalog_ids')) {
    function reports_v2_field_catalog_ids(array $field)
    {
        $catalogIds = array();
        if (!empty($field['linked_catalog_ids']) && is_array($field['linked_catalog_ids'])) {
            foreach ($field['linked_catalog_ids'] as $catalogId) {
                $catalogId = (int)$catalogId;
                if ($catalogId > 0 && !in_array($catalogId, $catalogIds, true)) {
                    $catalogIds[] = $catalogId;
                }
            }
        }
        $singleCatalogId = isset($field['id_line_item_catalog']) ? (int)$field['id_line_item_catalog'] : 0;
        if ($singleCatalogId > 0 && !in_array($singleCatalogId, $catalogIds, true)) {
            $catalogIds[] = $singleCatalogId;
        }
        return $catalogIds;
    }
}

if (!function_exists('reports_v2_build_line_item_cell')) {
    function reports_v2_build_line_item_cell(array $field, array $metricMeta, array $lineMetrics, $catalogId, $currency, $formatHint)
    {
        $metricCode = isset($field['source_metric']) ? (string)$field['source_metric'] : 'amount_cents';
        $meta = array(
            'data_type' => $metricMeta['data_type'],
            'numeric' => !empty($metricMeta['numeric']),
        );
        if ($catalogId > 0 && isset($lineMetrics[$catalogId]) && array_key_exists($metricCode, $lineMetrics[$catalogId])) {
            $rawValue = $lineMetrics[$catalogId][$metricCode];
        } else {
            $rawValue = !empty($metricMeta['numeric']) ? 0 : '';
        }
        return array(
            'raw' => $rawValue,
            'display' => pms_report_format_value($rawValue, $meta, $currency, $formatHint),
            'meta' => $meta,
            'error' => '',
            'catalog_id' => (int)$catalogId,
        );
    }
}

if (!function_exists('reports_v2_line_item_variant_cells')) {
    function reports_v2_line_item_variant_cells(array $field, array $metricMeta, array $lineMetrics, $currency, $formatHint)
    {
        $variants = array();
        foreach (reports_v2_field_catalog_ids($field) as $catalogId) {
            $cell = reports_v2_build_line_item_cell($field, $metricMeta, $lineMetrics, $catalogId, $currency, $formatHint);
            if (!reports_v2_value_is_empty_or_zero($cell['raw'], $cell['meta'])) {
                $variants[] = $cell;
            }
        }
        return $variants;
    }
}

if (!function_exists('reports_v2_fetch_calculations')) {
    function reports_v2_fetch_calculations(PDO $pdo, $companyId)
    {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM report_calculation
             WHERE id_company = ?
               AND deleted_at IS NULL
             ORDER BY is_active DESC, calc_name, id_report_calculation'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_fetch_calculation')) {
    function reports_v2_fetch_calculation(PDO $pdo, $companyId, $calcId)
    {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM report_calculation
             WHERE id_company = ?
               AND id_report_calculation = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(array((int)$companyId, (int)$calcId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

if (!function_exists('reports_v2_fetch_line_item_catalogs')) {
    function reports_v2_fetch_line_item_catalogs(PDO $pdo, $companyId)
    {
        $stmt = $pdo->prepare(
            'SELECT
                lic.id_line_item_catalog,
                lic.catalog_type,
                lic.item_name,
                sic.category_name AS subcategory_name,
                parent.category_name AS category_name,
                p.code AS property_code,
                p.name AS property_name
             FROM line_item_catalog lic
             JOIN sale_item_category sic
               ON sic.id_sale_item_category = lic.id_category
              AND sic.deleted_at IS NULL
             LEFT JOIN sale_item_category parent
               ON parent.id_sale_item_category = sic.id_parent_sale_item_category
              AND parent.deleted_at IS NULL
             LEFT JOIN property p
               ON p.id_property = sic.id_property
              AND p.deleted_at IS NULL
             WHERE sic.id_company = ?
               AND lic.deleted_at IS NULL
               AND lic.is_active = 1
             ORDER BY
                COALESCE(p.name, "General"),
                COALESCE(parent.category_name, sic.category_name),
                sic.category_name,
                lic.item_name'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_build_unique_code')) {
    function reports_v2_build_unique_code(PDO $pdo, $tableName, $idColumn, $codeColumn, $companyId, $baseCode, $currentId = 0)
    {
        $baseCode = pms_report_slugify($baseCode);
        $candidate = $baseCode;
        $suffix = 2;
        while (true) {
            $sql = 'SELECT ' . $idColumn . '
                    FROM ' . $tableName . '
                    WHERE id_company = ?
                      AND ' . $codeColumn . ' = ?
                      AND deleted_at IS NULL';
            $params = array((int)$companyId, $candidate);
            if ((int)$currentId > 0) {
                $sql .= ' AND ' . $idColumn . ' <> ?';
                $params[] = (int)$currentId;
            }
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $existing = (int)$stmt->fetchColumn();
            if ($existing <= 0) {
                return $candidate;
            }
            $candidate = $baseCode . '_' . $suffix;
            $suffix++;
        }
    }
}

if (!function_exists('reports_v2_build_variable_catalog')) {
    function reports_v2_build_variable_catalog(array $lineItemCatalogs)
    {
        $variables = array();
        foreach (pms_report_reservation_field_catalog() as $fieldCode => $meta) {
            if (!empty($meta['numeric'])) {
                $variables[$fieldCode] = array(
                    'label' => $meta['label'],
                    'group' => $meta['group'],
                    'data_type' => isset($meta['data_type']) ? $meta['data_type'] : 'number',
                    'source_type' => 'reservation',
                );
            }
        }

        $metricOptions = reports_v2_metric_options();
        foreach ($lineItemCatalogs as $catalog) {
            $catalogId = isset($catalog['id_line_item_catalog']) ? (int)$catalog['id_line_item_catalog'] : 0;
            if ($catalogId <= 0) {
                continue;
            }
            $baseLabel = isset($catalog['item_name']) ? (string)$catalog['item_name'] : ('Line item #' . $catalogId);
            foreach ($metricOptions as $metricCode => $metricMeta) {
                if (empty($metricMeta['numeric'])) {
                    continue;
                }
                $variables['line_item_' . $catalogId . '_' . $metricCode] = array(
                    'label' => $baseLabel . ' / ' . $metricMeta['label'],
                    'group' => 'Line items',
                    'data_type' => $metricMeta['data_type'],
                    'source_type' => 'line_item',
                    'id_line_item_catalog' => $catalogId,
                    'metric' => $metricCode,
                );
            }
        }

        return $variables;
    }
}

if (!function_exists('reports_v2_validate_expression')) {
    function reports_v2_validate_expression($expression, array $variableCatalog, &$error = '')
    {
        $probe = array();
        foreach ($variableCatalog as $variableCode => $meta) {
            $probe[$variableCode] = 0;
        }
        pms_report_safe_eval_expression($expression, $probe, $error);
        return $error === '';
    }
}

if (!function_exists('reports_v2_fetch_report_base_rows')) {
    function reports_v2_fetch_report_base_rows(PDO $pdo, $companyId, array $filters, $limit = 500)
    {
        $where = array(
            'p.id_company = ?',
            'r.deleted_at IS NULL',
            'COALESCE(r.is_active, 1) = 1',
            'p.deleted_at IS NULL'
        );
        $params = array((int)$companyId);

        $propertyCode = isset($filters['property_code']) ? strtoupper(trim((string)$filters['property_code'])) : '';
        if ($propertyCode !== '') {
            $where[] = 'UPPER(p.code) = ?';
            $params[] = $propertyCode;
        }

        $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
        if ($status !== '') {
            if ($status === 'activas') {
                $where[] = 'LOWER(TRIM(COALESCE(r.status, \'\'))) <> \'cancelada\'';
            } else {
                $where[] = 'r.status = ?';
                $params[] = $status;
            }
        }

        $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
        if ($search !== '') {
            $where[] = '(r.code LIKE ? OR g.full_name LIKE ? OR g.names LIKE ? OR rm.name LIKE ? OR rm.code LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        $dateFrom = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
        $dateTo = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
        if ($dateFrom !== '' && $dateTo !== '') {
            $where[] = 'r.check_out_date >= ? AND r.check_in_date <= ?';
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif ($dateFrom !== '') {
            $where[] = 'r.check_out_date >= ?';
            $params[] = $dateFrom;
        } elseif ($dateTo !== '') {
            $where[] = 'r.check_in_date <= ?';
            $params[] = $dateTo;
        }

        $sql = 'SELECT
                    r.id_reservation,
                    r.code AS reservation_code,
                    r.status AS reservation_status,
                    r.source AS reservation_source,
                    CASE
                        WHEN COALESCE(r.id_ota_account, 0) > 0 THEN \'OTA\'
                        WHEN COALESCE(r.id_reservation_source, 0) > 0 THEN \'Catalogo\'
                        WHEN COALESCE(TRIM(r.source), \'\') <> \'\' THEN \'Manual\'
                        ELSE \'\'
                    END AS reservation_origin_type,
                    rsc.source_name AS reservation_source_catalog_name,
                    rsc.source_code AS reservation_source_catalog_code,
                    oa.ota_name AS reservation_ota_name,
                    oa.platform AS reservation_ota_platform,
                    oa.external_code AS reservation_ota_external_code,
                    oa.contact_email AS reservation_ota_contact_email,
                    r.channel_ref AS reservation_channel_ref,
                    r.check_in_date AS reservation_check_in_date,
                    r.check_out_date AS reservation_check_out_date,
                    r.nights AS reservation_nights,
                    r.eta AS reservation_eta,
                    r.etd AS reservation_etd,
                    r.checkin_at AS reservation_checkin_at,
                    r.checkout_at AS reservation_checkout_at,
                    r.adults AS reservation_adults,
                    r.children AS reservation_children,
                    r.infants AS reservation_infants,
                    r.currency AS reservation_currency,
                    r.total_price_cents AS reservation_total_price_cents,
                    r.balance_due_cents AS reservation_balance_due_cents,
                    r.deposit_due_cents AS reservation_deposit_due_cents,
                    r.created_at AS reservation_created_at,
                    g.names AS guest_names,
                    g.last_name AS guest_last_name,
                    g.full_name AS guest_full_name,
                    g.email AS guest_email,
                    g.phone AS guest_phone,
                    g.nationality AS guest_nationality,
                    p.code AS property_code,
                    p.name AS property_name,
                    p.city AS property_city,
                    p.state AS property_state,
                    rm.code AS room_code,
                    rm.name AS room_name,
                    rm.floor AS room_floor,
                    rc.code AS category_code,
                    rc.name AS category_name,
                    rc.base_occupancy AS category_base_occupancy,
                    rc.max_occupancy AS category_max_occupancy,
                    rp.code AS rateplan_code,
                    rp.name AS rateplan_name
                FROM reservation r
                JOIN property p
                  ON p.id_property = r.id_property
                LEFT JOIN guest g
                  ON g.id_guest = r.id_guest
                 AND g.deleted_at IS NULL
                LEFT JOIN reservation_source_catalog rsc
                  ON rsc.id_reservation_source = r.id_reservation_source
                 AND rsc.deleted_at IS NULL
                LEFT JOIN ota_account oa
                  ON oa.id_ota_account = r.id_ota_account
                 AND oa.deleted_at IS NULL
                LEFT JOIN room rm
                  ON rm.id_room = r.id_room
                 AND rm.deleted_at IS NULL
                LEFT JOIN roomcategory rc
                  ON rc.id_category = r.id_category
                 AND rc.deleted_at IS NULL
                LEFT JOIN rateplan rp
                  ON rp.id_rateplan = r.id_rateplan
                 AND rp.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.check_in_date DESC, r.id_reservation DESC
                LIMIT ' . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_fetch_line_item_metrics')) {
    function reports_v2_fetch_line_item_metrics(PDO $pdo, array $reservationIds)
    {
        $reservationIds = array_values(array_filter(array_map('intval', $reservationIds)));
        if (empty($reservationIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = 'SELECT
                    f.id_reservation,
                    li.id_line_item_catalog,
                    MAX(COALESCE(lic.item_name, \'\')) AS item_name,
                    MAX(COALESCE(sic.category_name, \'\')) AS item_parent_name,
                    CASE
                        WHEN COUNT(DISTINCT COALESCE(li.item_type, \'\')) = 1 THEN MAX(COALESCE(li.item_type, \'\'))
                        ELSE \'mixed\'
                    END AS item_type,
                    MIN(li.service_date) AS service_date,
                    SUM(COALESCE(li.amount_cents, 0)) AS amount_cents,
                    SUM(COALESCE(li.paid_cents, 0)) AS paid_cents,
                    SUM(COALESCE(li.quantity, 0)) AS quantity,
                    CASE
                        WHEN ABS(SUM(COALESCE(li.quantity, 0))) > 0.000001 THEN ROUND(SUM(COALESCE(li.unit_price_cents, 0) * COALESCE(li.quantity, 0)) / SUM(COALESCE(li.quantity, 0)))
                        ELSE MAX(COALESCE(li.unit_price_cents, 0))
                    END AS unit_price_cents
                FROM folio f
                JOIN line_item li
                  ON li.id_folio = f.id_folio
                 AND li.deleted_at IS NULL
                 AND COALESCE(li.is_active, 1) = 1
                LEFT JOIN line_item_catalog lic
                  ON lic.id_line_item_catalog = li.id_line_item_catalog
                 AND lic.deleted_at IS NULL
                LEFT JOIN sale_item_category sic
                  ON sic.id_sale_item_category = lic.id_category
                 AND sic.deleted_at IS NULL
                WHERE f.id_reservation IN (' . $placeholders . ')
                  AND f.deleted_at IS NULL
                  AND COALESCE(f.is_active, 1) = 1
                  AND (
                    f.status IS NULL
                    OR LOWER(TRIM(f.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                  )
                  AND li.id_line_item_catalog IS NOT NULL
                  AND (
                    li.status IS NULL
                    OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\')
                  )
                GROUP BY f.id_reservation, li.id_line_item_catalog';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($reservationIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $metrics = array();
        foreach ($rows as $row) {
            $reservationId = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
            $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
            if ($reservationId <= 0 || $catalogId <= 0) {
                continue;
            }
            if (!isset($metrics[$reservationId])) {
                $metrics[$reservationId] = array();
            }
            $itemName = isset($row['item_name']) ? trim((string)$row['item_name']) : '';
            $itemParentName = isset($row['item_parent_name']) ? trim((string)$row['item_parent_name']) : '';
            $metrics[$reservationId][$catalogId] = array(
                'item_name' => $itemName,
                'item_name_parent' => $itemName !== ''
                    ? trim($itemName . ($itemParentName !== '' ? ' - ' . $itemParentName : ''))
                    : '',
                'item_type' => isset($row['item_type']) ? (string)$row['item_type'] : '',
                'service_date' => isset($row['service_date']) ? (string)$row['service_date'] : '',
                'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0,
                'paid_cents' => isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0,
                'quantity' => isset($row['quantity']) ? (float)$row['quantity'] : 0.0,
                'unit_price_cents' => isset($row['unit_price_cents']) ? (int)$row['unit_price_cents'] : 0,
            );
        }
        return $metrics;
    }
}

if (!function_exists('reports_v2_build_report_rows')) {
    function reports_v2_build_report_rows(array $baseRows, array $fields, array $calculationsById, array $lineItemMetrics, array $variableCatalog)
    {
        $reservationCatalog = pms_report_reservation_field_catalog();
        $metricOptions = reports_v2_metric_options();
        $reportRows = array();
        $totals = array();

        foreach ($baseRows as $baseRow) {
            $reservationId = isset($baseRow['id_reservation']) ? (int)$baseRow['id_reservation'] : 0;
            $currency = isset($baseRow['reservation_currency']) ? (string)$baseRow['reservation_currency'] : 'MXN';
            $lineMetrics = isset($lineItemMetrics[$reservationId]) ? $lineItemMetrics[$reservationId] : array();

            $calcVariables = array();
            foreach ($variableCatalog as $variableCode => $meta) {
                if (isset($meta['source_type']) && $meta['source_type'] === 'reservation') {
                    $calcVariables[$variableCode] = pms_report_reservation_field_value($baseRow, $variableCode);
                } else {
                    $catalogId = isset($meta['id_line_item_catalog']) ? (int)$meta['id_line_item_catalog'] : 0;
                    $metricCode = isset($meta['metric']) ? (string)$meta['metric'] : 'amount_cents';
                    $calcVariables[$variableCode] = isset($lineMetrics[$catalogId][$metricCode]) ? $lineMetrics[$catalogId][$metricCode] : 0;
                }
            }

            $baseCells = array();
            $expandedFieldVariants = array();
            $expandedRowCount = 1;
            foreach ($fields as $field) {
                $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
                $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : 'reservation';
                $formatHint = isset($field['format_hint']) ? (string)$field['format_hint'] : 'auto';
                $meta = array('data_type' => 'text', 'numeric' => false);
                $rawValue = null;
                $error = '';

                if ($fieldType === 'reservation') {
                    $fieldCode = isset($field['reservation_field_code']) ? (string)$field['reservation_field_code'] : '';
                    $meta = isset($reservationCatalog[$fieldCode]) ? $reservationCatalog[$fieldCode] : $meta;
                    $rawValue = pms_report_reservation_field_value($baseRow, $fieldCode);
                } elseif ($fieldType === 'line_item') {
                    $metricCode = isset($field['source_metric']) ? (string)$field['source_metric'] : 'amount_cents';
                    $metricMeta = isset($metricOptions[$metricCode]) ? $metricOptions[$metricCode] : $metricOptions['amount_cents'];
                    $allowMultipleCatalogs = !empty($field['allow_multiple_catalogs']);
                    $catalogIds = reports_v2_field_catalog_ids($field);
                    if ($allowMultipleCatalogs && count($catalogIds) > 1) {
                        $variantCells = reports_v2_line_item_variant_cells($field, $metricMeta, $lineMetrics, $currency, $formatHint);
                        if (count($variantCells) > 1) {
                            $expandedFieldVariants[$fieldId] = $variantCells;
                            if (count($variantCells) > $expandedRowCount) {
                                $expandedRowCount = count($variantCells);
                            }
                            continue;
                        }
                        if (count($variantCells) === 1) {
                            $baseCells[$fieldId] = $variantCells[0];
                            if (!empty($baseCells[$fieldId]['meta']['numeric']) && is_numeric($baseCells[$fieldId]['raw'])) {
                                if (!isset($totals[$fieldId])) {
                                    $totals[$fieldId] = 0;
                                }
                                $totals[$fieldId] += (float)$baseCells[$fieldId]['raw'];
                            }
                            continue;
                        }
                    }
                    $catalogId = !empty($catalogIds) ? (int)$catalogIds[0] : 0;
                    $cell = reports_v2_build_line_item_cell($field, $metricMeta, $lineMetrics, $catalogId, $currency, $formatHint);
                    $meta = $cell['meta'];
                    $rawValue = $cell['raw'];
                    if ($catalogId <= 0 && !empty($metricMeta['numeric'])) {
                        $rawValue = 0;
                    }
                } else {
                    $calcId = isset($field['id_report_calculation']) ? (int)$field['id_report_calculation'] : 0;
                    if (isset($calculationsById[$calcId])) {
                        $calculation = $calculationsById[$calcId];
                        $meta = array(
                            'data_type' => isset($calculation['format_hint']) ? (string)$calculation['format_hint'] : 'number',
                            'numeric' => true,
                            'decimal_places' => isset($calculation['decimal_places']) ? (int)$calculation['decimal_places'] : 2,
                        );
                        $rawValue = pms_report_safe_eval_expression(
                            isset($calculation['expression_text']) ? (string)$calculation['expression_text'] : '',
                            $calcVariables,
                            $error
                        );
                    }
                }

                $baseCells[$fieldId] = array(
                    'raw' => $rawValue,
                    'display' => $error !== ''
                        ? $error
                        : pms_report_format_value($rawValue, $meta, $currency, $formatHint),
                    'meta' => $meta,
                    'error' => $error,
                );

                if ($error === '' && !empty($meta['numeric']) && is_numeric($rawValue)) {
                    if (!isset($totals[$fieldId])) {
                        $totals[$fieldId] = 0;
                    }
                    $totals[$fieldId] += (float)$rawValue;
                }

                $defaultValue = isset($field['default_value']) ? trim((string)$field['default_value']) : '';
                if ($error === '' && $defaultValue !== '' && reports_v2_value_is_empty_or_zero($rawValue, $meta)) {
                    $baseCells[$fieldId]['display'] = $defaultValue;
                }
            }

            for ($rowIndex = 0; $rowIndex < $expandedRowCount; $rowIndex++) {
                $cells = array();
                foreach ($fields as $field) {
                    $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
                    if (isset($expandedFieldVariants[$fieldId])) {
                        if (isset($expandedFieldVariants[$fieldId][$rowIndex])) {
                            $cells[$fieldId] = $expandedFieldVariants[$fieldId][$rowIndex];
                        } else {
                            $cells[$fieldId] = array(
                                'raw' => '',
                                'display' => '',
                                'meta' => array('data_type' => 'text', 'numeric' => false),
                                'error' => '',
                            );
                        }
                        continue;
                    }
                    $cells[$fieldId] = isset($baseCells[$fieldId]) && is_array($baseCells[$fieldId])
                        ? $baseCells[$fieldId]
                        : array(
                            'raw' => '',
                            'display' => '',
                            'meta' => array('data_type' => 'text', 'numeric' => false),
                            'error' => '',
                        );
                }

                $reportRows[] = array(
                    'reservation_id' => $reservationId,
                    'reservation_code' => isset($baseRow['reservation_code']) ? (string)$baseRow['reservation_code'] : '',
                    'currency' => $currency,
                    'base' => $baseRow,
                    'row_index' => $rowIndex,
                    'row_span_count' => $expandedRowCount,
                    'cells' => $cells,
                );
            }
        }

        return array($reportRows, $totals);
    }
}

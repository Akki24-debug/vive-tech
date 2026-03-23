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

if (!function_exists('reports_v2_row_source_options')) {
    function reports_v2_row_source_options()
    {
        return array(
            'reservation' => 'Reservacion',
            'line_item' => 'Line item',
            'combined' => 'Combinado',
        );
    }
}

if (!function_exists('reports_v2_line_item_row_type_options')) {
    function reports_v2_line_item_row_type_options()
    {
        return array(
            'all' => 'Todos',
            'sale_item' => 'Cargo / servicio',
            'tax_item' => 'Impuesto',
            'payment' => 'Pago',
            'obligation' => 'Obligacion',
            'income' => 'Ingreso',
        );
    }
}

if (!function_exists('reports_v2_date_type_options')) {
    function reports_v2_date_type_options()
    {
        return array(
            'created_at' => 'Fecha de creacion',
            'service_date' => 'Fecha de servicio',
            'check_in_date' => 'Fecha de check in',
            'check_out_date' => 'Fecha de check out',
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
            'folio_id' => array('label' => 'Folio / ID', 'data_type' => 'integer', 'numeric' => true),
            'folio_name' => array('label' => 'Folio / Nombre', 'data_type' => 'text', 'numeric' => false),
            'folio_status' => array('label' => 'Folio / Estatus', 'data_type' => 'text', 'numeric' => false),
            'folio_total_cents' => array('label' => 'Folio / Total', 'data_type' => 'currency', 'numeric' => true),
            'folio_balance_cents' => array('label' => 'Folio / Saldo', 'data_type' => 'currency', 'numeric' => true),
            'amount_cents' => array('label' => 'Monto', 'data_type' => 'currency', 'numeric' => true),
            'paid_cents' => array('label' => 'Cantidad pagada', 'data_type' => 'currency', 'numeric' => true),
            'quantity' => array('label' => 'Cantidad', 'data_type' => 'number', 'numeric' => true),
            'unit_price_cents' => array('label' => 'Precio unitario', 'data_type' => 'currency', 'numeric' => true),
        );
    }
}

if (!function_exists('reports_v2_metric_is_valid')) {
    function reports_v2_metric_is_valid($metricCode)
    {
        $metricCode = trim((string)$metricCode);
        if ($metricCode === '') {
            return false;
        }
        $metricOptions = reports_v2_metric_options();
        return isset($metricOptions[$metricCode]);
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

if (!function_exists('reports_v2_prepare_editable_input_catalog')) {
    function reports_v2_prepare_editable_input_catalog(PDO $pdo, $companyId)
    {
        static $cache = array();
        $cacheKey = (int)$companyId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $catalog = function_exists('pms_report_reservation_editable_field_catalog')
            ? pms_report_reservation_editable_field_catalog()
            : array();

        $guestOptions = array(array('value' => '0', 'label' => 'Sin huesped'));
        try {
            $stmt = $pdo->prepare(
                'SELECT
                    g.id_guest,
                    COALESCE(
                        NULLIF(TRIM(g.full_name), \'\'),
                        NULLIF(TRIM(CONCAT_WS(\' \', g.names, g.last_name, g.maiden_name)), \'\'),
                        CONCAT(\'Huesped #\', g.id_guest)
                    ) AS guest_label
                 FROM guest g
                 WHERE g.deleted_at IS NULL
                 ORDER BY guest_label, g.id_guest'
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
                if ($guestId <= 0) {
                    continue;
                }
                $guestOptions[] = array(
                    'value' => (string)$guestId,
                    'label' => trim((string)(isset($row['guest_label']) ? $row['guest_label'] : ('Huesped #' . $guestId))),
                );
            }
        } catch (Exception $e) {
            $guestOptions = array(array('value' => '0', 'label' => 'Sin huesped'));
        }

        $propertyOptions = array();
        try {
            $stmt = $pdo->prepare(
                'SELECT p.id_property, p.code, p.name
                 FROM property p
                 WHERE p.id_company = ?
                   AND p.deleted_at IS NULL
                 ORDER BY p.order_index, p.name, p.code'
            );
            $stmt->execute(array((int)$companyId));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $propertyId = isset($row['id_property']) ? (int)$row['id_property'] : 0;
                if ($propertyId <= 0) {
                    continue;
                }
                $label = trim((string)(isset($row['name']) ? $row['name'] : ''));
                if ($label === '') {
                    $label = trim((string)(isset($row['code']) ? $row['code'] : ''));
                }
                $propertyOptions[] = array(
                    'value' => (string)$propertyId,
                    'label' => $label !== '' ? $label : ('Propiedad #' . $propertyId),
                );
            }
        } catch (Exception $e) {
            $propertyOptions = array();
        }

        $roomOptions = array(array('value' => '0', 'label' => 'Sin habitacion'));
        try {
            $stmt = $pdo->prepare(
                'SELECT
                    r.id_room,
                    COALESCE(NULLIF(TRIM(p.name), \'\'), p.code, \'\') AS property_label,
                    COALESCE(NULLIF(TRIM(r.name), \'\'), r.code, CONCAT(\'Habitacion #\', r.id_room)) AS room_label
                 FROM room r
                 JOIN property p
                   ON p.id_property = r.id_property
                  AND p.deleted_at IS NULL
                 WHERE p.id_company = ?
                   AND r.deleted_at IS NULL
                 ORDER BY property_label, room_label, r.id_room'
            );
            $stmt->execute(array((int)$companyId));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $roomId = isset($row['id_room']) ? (int)$row['id_room'] : 0;
                if ($roomId <= 0) {
                    continue;
                }
                $roomOptions[] = array(
                    'value' => (string)$roomId,
                    'label' => trim((string)$row['property_label']) . ' / ' . trim((string)$row['room_label']),
                );
            }
        } catch (Exception $e) {
            $roomOptions = array(array('value' => '0', 'label' => 'Sin habitacion'));
        }

        $categoryOptions = array(array('value' => '0', 'label' => 'Sin categoria'));
        try {
            $stmt = $pdo->prepare(
                'SELECT
                    rc.id_category,
                    COALESCE(NULLIF(TRIM(p.name), \'\'), p.code, \'\') AS property_label,
                    COALESCE(NULLIF(TRIM(rc.name), \'\'), rc.code, CONCAT(\'Categoria #\', rc.id_category)) AS category_label
                 FROM roomcategory rc
                 JOIN property p
                   ON p.id_property = rc.id_property
                  AND p.deleted_at IS NULL
                 WHERE p.id_company = ?
                   AND rc.deleted_at IS NULL
                 ORDER BY property_label, category_label, rc.id_category'
            );
            $stmt->execute(array((int)$companyId));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $categoryId = isset($row['id_category']) ? (int)$row['id_category'] : 0;
                if ($categoryId <= 0) {
                    continue;
                }
                $categoryOptions[] = array(
                    'value' => (string)$categoryId,
                    'label' => trim((string)$row['property_label']) . ' / ' . trim((string)$row['category_label']),
                );
            }
        } catch (Exception $e) {
            $categoryOptions = array(array('value' => '0', 'label' => 'Sin categoria'));
        }

        $rateplanOptions = array(array('value' => '0', 'label' => 'Sin tarifa'));
        try {
            $stmt = $pdo->prepare(
                'SELECT
                    rp.id_rateplan,
                    COALESCE(NULLIF(TRIM(p.name), \'\'), p.code, \'\') AS property_label,
                    COALESCE(NULLIF(TRIM(rp.name), \'\'), rp.code, CONCAT(\'Tarifa #\', rp.id_rateplan)) AS rateplan_label
                 FROM rateplan rp
                 JOIN property p
                   ON p.id_property = rp.id_property
                  AND p.deleted_at IS NULL
                 WHERE p.id_company = ?
                   AND rp.deleted_at IS NULL
                 ORDER BY property_label, rateplan_label, rp.id_rateplan'
            );
            $stmt->execute(array((int)$companyId));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rateplanId = isset($row['id_rateplan']) ? (int)$row['id_rateplan'] : 0;
                if ($rateplanId <= 0) {
                    continue;
                }
                $rateplanOptions[] = array(
                    'value' => (string)$rateplanId,
                    'label' => trim((string)$row['property_label']) . ' / ' . trim((string)$row['rateplan_label']),
                );
            }
        } catch (Exception $e) {
            $rateplanOptions = array(array('value' => '0', 'label' => 'Sin tarifa'));
        }

        foreach ($catalog as $fieldCode => $meta) {
            $source = isset($meta['options_source']) ? (string)$meta['options_source'] : '';
            if ($source === 'guests') {
                $catalog[$fieldCode]['options'] = $guestOptions;
            } elseif ($source === 'properties') {
                $catalog[$fieldCode]['options'] = $propertyOptions;
            } elseif ($source === 'rooms') {
                $catalog[$fieldCode]['options'] = $roomOptions;
            } elseif ($source === 'categories') {
                $catalog[$fieldCode]['options'] = $categoryOptions;
            } elseif ($source === 'rateplans') {
                $catalog[$fieldCode]['options'] = $rateplanOptions;
            }
        }

        $cache[$cacheKey] = $catalog;
        return $catalog;
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
        $categoryNameSelect = reports_v2_report_template_has_category_name_column($pdo)
            ? 'rt.category_name'
            : 'NULL AS category_name';
        $lineItemTypeScopeSelect = reports_v2_report_template_has_line_item_type_scope_column($pdo)
            ? 'rt.line_item_type_scope'
            : 'NULL AS line_item_type_scope';
        $subdivideByFieldSelect = reports_v2_report_template_has_subdivide_by_field_id_column($pdo)
            ? 'rt.subdivide_by_field_id'
            : 'NULL AS subdivide_by_field_id';
        $subdivideByFieldLevel2Select = reports_v2_report_template_has_subdivide_by_field_id_level_2_column($pdo)
            ? 'rt.subdivide_by_field_id_level_2'
            : 'NULL AS subdivide_by_field_id_level_2';
        $subdivideByFieldLevel3Select = reports_v2_report_template_has_subdivide_by_field_id_level_3_column($pdo)
            ? 'rt.subdivide_by_field_id_level_3'
            : 'NULL AS subdivide_by_field_id_level_3';
        $subdivideShowTotalsLevel1Select = reports_v2_report_template_has_subdivide_show_totals_level_1_column($pdo)
            ? 'rt.subdivide_show_totals_level_1'
            : '1 AS subdivide_show_totals_level_1';
        $subdivideShowTotalsLevel2Select = reports_v2_report_template_has_subdivide_show_totals_level_2_column($pdo)
            ? 'rt.subdivide_show_totals_level_2'
            : '1 AS subdivide_show_totals_level_2';
        $subdivideShowTotalsLevel3Select = reports_v2_report_template_has_subdivide_show_totals_level_3_column($pdo)
            ? 'rt.subdivide_show_totals_level_3'
            : '1 AS subdivide_show_totals_level_3';
        $groupByColumns = array(
            'rt.id_report_template',
            'rt.report_key',
            'rt.report_name',
            'rt.category_name',
            'rt.description',
            'rt.row_source',
            'rt.is_active',
        );
        if (!reports_v2_report_template_has_category_name_column($pdo)) {
            $groupByColumns = array_values(array_filter($groupByColumns, function ($column) {
                return $column !== 'rt.category_name';
            }));
        }
        if (reports_v2_report_template_has_line_item_type_scope_column($pdo)) {
            $groupByColumns[] = 'rt.line_item_type_scope';
        }
        if (reports_v2_report_template_has_subdivide_by_field_id_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_by_field_id';
        }
        if (reports_v2_report_template_has_subdivide_by_field_id_level_2_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_by_field_id_level_2';
        }
        if (reports_v2_report_template_has_subdivide_by_field_id_level_3_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_by_field_id_level_3';
        }
        if (reports_v2_report_template_has_subdivide_show_totals_level_1_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_show_totals_level_1';
        }
        if (reports_v2_report_template_has_subdivide_show_totals_level_2_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_show_totals_level_2';
        }
        if (reports_v2_report_template_has_subdivide_show_totals_level_3_column($pdo)) {
            $groupByColumns[] = 'rt.subdivide_show_totals_level_3';
        }
        $stmt = $pdo->prepare(
            'SELECT
                rt.id_report_template,
                rt.report_key,
                rt.report_name,
                ' . $categoryNameSelect . ',
                rt.description,
                rt.row_source,
                ' . $lineItemTypeScopeSelect . ',
                ' . $subdivideByFieldSelect . ',
                ' . $subdivideByFieldLevel2Select . ',
                ' . $subdivideByFieldLevel3Select . ',
                ' . $subdivideShowTotalsLevel1Select . ',
                ' . $subdivideShowTotalsLevel2Select . ',
                ' . $subdivideShowTotalsLevel3Select . ',
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
                ' . implode(",\n                ", $groupByColumns) . '
             ORDER BY
                rt.is_active DESC,
                COALESCE(NULLIF(TRIM(' . (reports_v2_report_template_has_category_name_column($pdo) ? 'rt.category_name' : '\'\'') . '), \'\'), \'Sin categoria\'),
                rt.report_name,
                rt.id_report_template'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_report_template_has_column')) {
    function reports_v2_report_template_has_column(PDO $pdo, $columnName)
    {
        static $cache = array();
        $columnName = trim((string)$columnName);
        if ($columnName === '') {
            return false;
        }
        if (array_key_exists($columnName, $cache)) {
            return $cache[$columnName];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?'
            );
            $stmt->execute(array('report_template', $columnName));
            $cache[$columnName] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $cache[$columnName] = false;
        }
        return $cache[$columnName];
    }
}

if (!function_exists('reports_v2_report_template_has_category_name_column')) {
    function reports_v2_report_template_has_category_name_column(PDO $pdo)
    {
        return reports_v2_report_template_has_column($pdo, 'category_name');
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
        $isEditableSelect = reports_v2_template_field_has_is_editable_column($pdo)
            ? 'rtf.is_editable'
            : '0 AS is_editable';
        $allowMultipleCatalogsSelect = reports_v2_template_field_has_allow_multiple_catalogs_column($pdo)
            ? 'rtf.allow_multiple_catalogs'
            : '0 AS allow_multiple_catalogs';
        $calculateTotalSelect = reports_v2_template_field_has_calculate_total_column($pdo)
            ? 'rtf.calculate_total'
            : '0 AS calculate_total';
        $stmt = $pdo->prepare(
            'SELECT
                rtf.id_report_template_field,
                rtf.id_report_template,
                rtf.field_type,
                rtf.display_name,
                ' . $isEditableSelect . ',
                ' . $calculateTotalSelect . ',
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
        $fields = reports_v2_attach_template_field_catalog_links($pdo, $fields);
        return reports_v2_prepare_template_field_display_names($pdo, $fields);
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

if (!function_exists('reports_v2_report_template_has_line_item_type_scope_column')) {
    function reports_v2_report_template_has_line_item_type_scope_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'line_item_type_scope'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_by_field_id_column')) {
    function reports_v2_report_template_has_subdivide_by_field_id_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_by_field_id'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_by_field_id_level_2_column')) {
    function reports_v2_report_template_has_subdivide_by_field_id_level_2_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_by_field_id_level_2'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_by_field_id_level_3_column')) {
    function reports_v2_report_template_has_subdivide_by_field_id_level_3_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_by_field_id_level_3'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_show_totals_level_1_column')) {
    function reports_v2_report_template_has_subdivide_show_totals_level_1_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_show_totals_level_1'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_show_totals_level_2_column')) {
    function reports_v2_report_template_has_subdivide_show_totals_level_2_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_show_totals_level_2'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_has_subdivide_show_totals_level_3_column')) {
    function reports_v2_report_template_has_subdivide_show_totals_level_3_column(PDO $pdo)
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
            $stmt->execute(array('report_template', 'subdivide_show_totals_level_3'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_report_template_row_source_supports_value')) {
    function reports_v2_report_template_row_source_supports_value(PDO $pdo, $rowSourceValue)
    {
        static $supports = array();
        $rowSourceValue = trim((string)$rowSourceValue);
        if ($rowSourceValue === '') {
            return false;
        }
        if (array_key_exists($rowSourceValue, $supports)) {
            return $supports[$rowSourceValue];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COLUMN_TYPE
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                  LIMIT 1'
            );
            $stmt->execute(array('report_template', 'row_source'));
            $columnType = (string)$stmt->fetchColumn();
            $supports[$rowSourceValue] = stripos($columnType, "'" . $rowSourceValue . "'") !== false
                || stripos($columnType, $rowSourceValue) !== false;
        } catch (Exception $e) {
            $supports[$rowSourceValue] = false;
        }
        return $supports[$rowSourceValue];
    }
}

if (!function_exists('reports_v2_report_template_row_source_supports_line_item')) {
    function reports_v2_report_template_row_source_supports_line_item(PDO $pdo)
    {
        return reports_v2_report_template_row_source_supports_value($pdo, 'line_item');
    }
}

if (!function_exists('reports_v2_report_template_row_source_supports_combined')) {
    function reports_v2_report_template_row_source_supports_combined(PDO $pdo)
    {
        return reports_v2_report_template_row_source_supports_value($pdo, 'combined');
    }
}

if (!function_exists('reports_v2_template_field_has_is_editable_column')) {
    function reports_v2_template_field_has_is_editable_column(PDO $pdo)
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
            $stmt->execute(array('report_template_field', 'is_editable'));
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

if (!function_exists('reports_v2_template_field_has_calculate_total_column')) {
    function reports_v2_template_field_has_calculate_total_column(PDO $pdo)
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
            $stmt->execute(array('report_template_field', 'calculate_total'));
            $hasColumn = ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            $hasColumn = false;
        }
        return $hasColumn;
    }
}

if (!function_exists('reports_v2_template_field_source_metric_supports_extended_values')) {
    function reports_v2_template_field_source_metric_supports_extended_values(PDO $pdo)
    {
        static $supportsExtended = null;
        if ($supportsExtended !== null) {
            return $supportsExtended;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT DATA_TYPE, COLUMN_TYPE
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                  LIMIT 1'
            );
            $stmt->execute(array('report_template_field', 'source_metric'));
            $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $dataType = isset($columnInfo['DATA_TYPE']) ? strtolower(trim((string)$columnInfo['DATA_TYPE'])) : '';
            $columnType = isset($columnInfo['COLUMN_TYPE']) ? (string)$columnInfo['COLUMN_TYPE'] : '';
            $supportsExtended = (
                in_array($dataType, array('varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'), true)
                || ($columnType !== '' && stripos($columnType, '\'item_name\'') !== false)
            );
        } catch (Exception $e) {
            $supportsExtended = false;
        }
        return $supportsExtended;
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
            $fields[$index]['primary_catalog_category_name'] = '';
            $fields[$index]['primary_catalog_subcategory_name'] = '';
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
                $index = $indexByFieldId[$fieldId];
                $itemName = trim((string)(isset($row['item_name']) ? $row['item_name'] : ''));
                $rowCatalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
                $primaryCatalogId = isset($fields[$index]['id_line_item_catalog']) ? (int)$fields[$index]['id_line_item_catalog'] : 0;
                if ($rowCatalogId > 0 && $rowCatalogId === $primaryCatalogId) {
                    $fields[$index]['primary_catalog_category_name'] = trim((string)(isset($row['category_name']) ? $row['category_name'] : ''));
                    $fields[$index]['primary_catalog_subcategory_name'] = trim((string)(isset($row['subcategory_name']) ? $row['subcategory_name'] : ''));
                }
                $fields[$index]['linked_catalog_ids'][] = (int)$row['id_line_item_catalog'];
                if ($itemName !== '') {
                    $fields[$index]['linked_catalog_labels'][] = $itemName;
                }
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

        foreach ($fields as $index => $field) {
            $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
            if ($fieldType !== 'line_item') {
                continue;
            }
            $displayName = trim((string)(isset($field['display_name']) ? $field['display_name'] : ''));
            $itemName = trim((string)(isset($field['line_item_name']) ? $field['line_item_name'] : ''));
            $categoryName = trim((string)(isset($field['primary_catalog_category_name']) ? $field['primary_catalog_category_name'] : ''));
            $subcategoryName = trim((string)(isset($field['primary_catalog_subcategory_name']) ? $field['primary_catalog_subcategory_name'] : ''));
            $legacyParentLabels = array();
            if ($itemName !== '' && $categoryName !== '') {
                $legacyParentLabels[] = $itemName . ' - ' . $categoryName;
            }
            if ($itemName !== '' && $subcategoryName !== '' && $subcategoryName !== $categoryName) {
                $legacyParentLabels[] = $itemName . ' - ' . $subcategoryName;
            }
            if ($displayName !== '' && in_array($displayName, $legacyParentLabels, true)) {
                $fields[$index]['display_name'] = 'Nombre - nombre padre';
            }
        }

        return $fields;
    }
}

if (!function_exists('reports_v2_line_item_name_parent_token_prefix')) {
    function reports_v2_line_item_name_parent_token_prefix()
    {
        return '__AUTO_NAME_PARENT__::';
    }
}

if (!function_exists('reports_v2_line_item_name_parent_token_build')) {
    function reports_v2_line_item_name_parent_token_build($baseName)
    {
        return reports_v2_line_item_name_parent_token_prefix() . trim((string)$baseName);
    }
}

if (!function_exists('reports_v2_line_item_name_parent_token_extract')) {
    function reports_v2_line_item_name_parent_token_extract($displayName)
    {
        $displayName = (string)$displayName;
        $prefix = reports_v2_line_item_name_parent_token_prefix();
        if (strpos($displayName, $prefix) !== 0) {
            return null;
        }
        return trim(substr($displayName, strlen($prefix)));
    }
}

if (!function_exists('reports_v2_sample_parent_item_name_for_catalogs')) {
    function reports_v2_sample_parent_item_name_for_catalogs(PDO $pdo, array $catalogIds)
    {
        static $cache = array();
        $catalogIds = array_values(array_filter(array_map('intval', $catalogIds)));
        $catalogIds = array_values(array_unique($catalogIds));
        if (empty($catalogIds)) {
            return '';
        }
        $cacheKey = implode(',', $catalogIds);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $placeholders = implode(',', array_fill(0, count($catalogIds), '?'));

        $stmt = $pdo->prepare(
            'SELECT
                li.id_line_item_catalog AS child_catalog_id,
                COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name, \'\') AS parent_item_name
             FROM line_item li
             JOIN line_item_hierarchy lih
               ON lih.id_line_item_child = li.id_line_item
              AND lih.deleted_at IS NULL
              AND COALESCE(lih.is_active, 1) = 1
             JOIN line_item li_parent
               ON li_parent.id_line_item = lih.id_line_item_parent
              AND li_parent.deleted_at IS NULL
              AND COALESCE(li_parent.is_active, 1) = 1
             LEFT JOIN line_item_catalog parent_lic
               ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
              AND parent_lic.deleted_at IS NULL
             WHERE li.deleted_at IS NULL
               AND COALESCE(li.is_active, 1) = 1
               AND li.id_line_item_catalog IN (' . $placeholders . ')
             ORDER BY li.id_line_item DESC'
        );
        $stmt->execute($catalogIds);
        $hierarchyParents = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $childCatalogId = isset($row['child_catalog_id']) ? (int)$row['child_catalog_id'] : 0;
            $parentName = trim((string)(isset($row['parent_item_name']) ? $row['parent_item_name'] : ''));
            if ($childCatalogId <= 0 || $parentName === '' || isset($hierarchyParents[$childCatalogId])) {
                continue;
            }
            $hierarchyParents[$childCatalogId] = $parentName;
        }
        $parentName = '';
        foreach ($catalogIds as $catalogId) {
            if (!empty($hierarchyParents[$catalogId])) {
                $parentName = $hierarchyParents[$catalogId];
                break;
            }
        }

        if ($parentName === '') {
            $stmt = $pdo->prepare(
                'SELECT rel.child_catalog_id, parent_lic.item_name AS parent_item_name
                 FROM (
                    SELECT
                        lcp.id_sale_item_catalog AS child_catalog_id,
                        lcp.id_parent_sale_item_catalog AS parent_catalog_id,
                        1 AS rel_priority
                    FROM line_item_catalog_parent lcp
                    WHERE lcp.deleted_at IS NULL
                      AND COALESCE(lcp.is_active, 1) = 1
                      AND lcp.id_sale_item_catalog IN (' . $placeholders . ')
                    UNION ALL
                    SELECT
                        lcc.id_component_line_item_catalog AS child_catalog_id,
                        lcc.id_parent_line_item_catalog AS parent_catalog_id,
                        2 AS rel_priority
                    FROM line_item_catalog_calc lcc
                    WHERE lcc.deleted_at IS NULL
                      AND COALESCE(lcc.is_active, 1) = 1
                      AND lcc.id_component_line_item_catalog IN (' . $placeholders . ')
                 ) rel
                 JOIN line_item_catalog parent_lic
                   ON parent_lic.id_line_item_catalog = rel.parent_catalog_id
                  AND parent_lic.deleted_at IS NULL
                 ORDER BY rel.rel_priority ASC, rel.child_catalog_id ASC'
            );
            $stmt->execute(array_merge($catalogIds, $catalogIds));
            $catalogParents = array();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $childCatalogId = isset($row['child_catalog_id']) ? (int)$row['child_catalog_id'] : 0;
                $candidateParentName = trim((string)(isset($row['parent_item_name']) ? $row['parent_item_name'] : ''));
                if ($childCatalogId <= 0 || $candidateParentName === '' || isset($catalogParents[$childCatalogId])) {
                    continue;
                }
                $catalogParents[$childCatalogId] = $candidateParentName;
            }
            foreach ($catalogIds as $catalogId) {
                if (!empty($catalogParents[$catalogId])) {
                    $parentName = $catalogParents[$catalogId];
                    break;
                }
            }
        }

        $cache[$cacheKey] = $parentName;
        return $parentName;
    }
}

if (!function_exists('reports_v2_resolve_field_display_name')) {
    function reports_v2_resolve_field_display_name(PDO $pdo, array $field)
    {
        $displayName = trim((string)(isset($field['display_name']) ? $field['display_name'] : ''));
        $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
        if ($fieldType !== 'line_item') {
            return $displayName;
        }

        $tokenBase = reports_v2_line_item_name_parent_token_extract($displayName);
        $lineItemName = trim((string)(isset($field['line_item_name']) ? $field['line_item_name'] : ''));
        $baseName = $tokenBase !== null ? $tokenBase : $lineItemName;
        if ($baseName === '' && !empty($field['linked_catalog_labels']) && is_array($field['linked_catalog_labels'])) {
            $baseName = trim((string)$field['linked_catalog_labels'][0]);
        }

        $legacyGeneric = $displayName === 'Nombre - nombre padre'
            || ($lineItemName !== '' && $displayName === ($lineItemName . ' - nombre padre'));

        if ($tokenBase === null && !$legacyGeneric) {
            return $displayName;
        }

        $catalogIds = array();
        $primaryCatalogId = isset($field['id_line_item_catalog']) ? (int)$field['id_line_item_catalog'] : 0;
        if ($primaryCatalogId > 0) {
            $catalogIds[] = $primaryCatalogId;
        }
        foreach (reports_v2_field_catalog_ids($field) as $catalogId) {
            $catalogId = (int)$catalogId;
            if ($catalogId > 0 && !in_array($catalogId, $catalogIds, true)) {
                $catalogIds[] = $catalogId;
            }
        }
        $parentName = reports_v2_sample_parent_item_name_for_catalogs($pdo, $catalogIds);
        if ($baseName !== '' && $parentName !== '') {
            return $baseName . ' - ' . $parentName;
        }
        if ($baseName !== '') {
            return $baseName;
        }
        return 'Nombre - nombre padre';
    }
}

if (!function_exists('reports_v2_prepare_template_field_display_names')) {
    function reports_v2_prepare_template_field_display_names(PDO $pdo, array $fields)
    {
        foreach ($fields as $index => $field) {
            $fields[$index]['display_name_resolved'] = reports_v2_resolve_field_display_name($pdo, $field);
            $tokenBase = reports_v2_line_item_name_parent_token_extract(isset($field['display_name']) ? $field['display_name'] : '');
            if ($tokenBase !== null) {
                $fields[$index]['display_name_input'] = $fields[$index]['display_name_resolved'];
            } else {
                $fields[$index]['display_name_input'] = isset($field['display_name']) ? (string)$field['display_name'] : '';
            }
        }
        return $fields;
    }
}

if (!function_exists('reports_v2_template_editable_reservation_fields')) {
    function reports_v2_template_editable_reservation_fields(array $fields)
    {
        $editableFields = array();
        foreach ($fields as $field) {
            $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
            $fieldCode = isset($field['reservation_field_code']) ? (string)$field['reservation_field_code'] : '';
            if ($fieldType !== 'reservation' || empty($field['is_editable'])) {
                continue;
            }
            if (!pms_report_reservation_field_is_inline_editable($fieldCode)) {
                continue;
            }
            $editableFields[] = $field;
        }
        return $editableFields;
    }
}

if (!function_exists('reports_v2_value_is_empty_or_zero')) {
    function reports_v2_value_is_empty_or_zero($rawValue, array $meta)
    {
        if ($rawValue === null) {
            return true;
        }
        if (function_exists('pms_report_is_entity_reference_value') && pms_report_is_entity_reference_value($rawValue)) {
            return trim((string)(isset($rawValue['label']) ? $rawValue['label'] : '')) === '';
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

if (!function_exists('reports_v2_field_calculates_total')) {
    function reports_v2_field_calculates_total(array $field)
    {
        return !empty($field['calculate_total']);
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
        if (!reports_v2_metric_is_valid($metricCode)) {
            return array(
                'raw' => '',
                'display' => '[Metrica invalida]',
                'meta' => array('data_type' => 'text', 'numeric' => false),
                'error' => 'Metrica line item invalida o no guardada: ' . ($metricCode !== '' ? $metricCode : '(vacia)'),
                'catalog_id' => (int)$catalogId,
            );
        }
        $meta = array(
            'data_type' => $metricMeta['data_type'],
            'numeric' => !empty($metricMeta['numeric']),
        );
        $effectiveFormatHint = $formatHint;
        if (empty($metricMeta['numeric'])) {
            $effectiveFormatHint = isset($metricMeta['data_type']) ? (string)$metricMeta['data_type'] : 'text';
        }
        if ($catalogId > 0 && isset($lineMetrics[$catalogId]) && array_key_exists($metricCode, $lineMetrics[$catalogId])) {
            $rawValue = $lineMetrics[$catalogId][$metricCode];
        } else {
            $rawValue = !empty($metricMeta['numeric']) ? 0 : '';
        }
        return array(
            'raw' => $rawValue,
            'display' => pms_report_format_value($rawValue, $meta, $currency, $effectiveFormatHint),
            'meta' => $meta,
            'error' => '',
            'catalog_id' => (int)$catalogId,
        );
    }
}

if (!function_exists('reports_v2_current_line_item_metric_value')) {
    function reports_v2_current_line_item_metric_value(array $baseRow, $metricCode)
    {
        switch ((string)$metricCode) {
            case 'item_name':
                return isset($baseRow['base_line_item_name']) ? (string)$baseRow['base_line_item_name'] : '';
            case 'item_name_parent':
                return isset($baseRow['base_line_item_name_parent']) ? (string)$baseRow['base_line_item_name_parent'] : '';
            case 'item_type':
                return isset($baseRow['base_line_item_type']) ? (string)$baseRow['base_line_item_type'] : '';
            case 'service_date':
                return isset($baseRow['base_line_item_service_date']) ? (string)$baseRow['base_line_item_service_date'] : '';
            case 'folio_id':
                return isset($baseRow['base_line_item_folio_id']) ? (int)$baseRow['base_line_item_folio_id'] : 0;
            case 'folio_name':
                $folioId = isset($baseRow['base_line_item_folio_id']) ? (int)$baseRow['base_line_item_folio_id'] : 0;
                $folioName = isset($baseRow['base_line_item_folio_name']) ? trim((string)$baseRow['base_line_item_folio_name']) : '';
                if ($folioId > 0 && $folioName !== '') {
                    return $folioId . ' - ' . $folioName;
                }
                if ($folioId > 0) {
                    return (string)$folioId;
                }
                return $folioName;
            case 'folio_status':
                return isset($baseRow['base_line_item_folio_status']) ? (string)$baseRow['base_line_item_folio_status'] : '';
            case 'folio_total_cents':
                return isset($baseRow['base_line_item_folio_total_cents']) ? (int)$baseRow['base_line_item_folio_total_cents'] : 0;
            case 'folio_balance_cents':
                return isset($baseRow['base_line_item_folio_balance_cents']) ? (int)$baseRow['base_line_item_folio_balance_cents'] : 0;
            case 'paid_cents':
                return isset($baseRow['base_line_item_paid_cents']) ? (int)$baseRow['base_line_item_paid_cents'] : 0;
            case 'quantity':
                return isset($baseRow['base_line_item_quantity']) ? (float)$baseRow['base_line_item_quantity'] : 0.0;
            case 'unit_price_cents':
                return isset($baseRow['base_line_item_unit_price_cents']) ? (int)$baseRow['base_line_item_unit_price_cents'] : 0;
            case 'amount_cents':
            default:
                return isset($baseRow['base_line_item_amount_cents']) ? (int)$baseRow['base_line_item_amount_cents'] : 0;
        }
    }
}

if (!function_exists('reports_v2_build_current_line_item_cell')) {
    function reports_v2_build_current_line_item_cell(array $field, array $metricMeta, array $baseRow, $currency, $formatHint)
    {
        $metricCode = isset($field['source_metric']) ? (string)$field['source_metric'] : 'amount_cents';
        if (!reports_v2_metric_is_valid($metricCode)) {
            return array(
                'raw' => '',
                'display' => '[Metrica invalida]',
                'meta' => array('data_type' => 'text', 'numeric' => false),
                'error' => 'Metrica line item invalida o no guardada: ' . ($metricCode !== '' ? $metricCode : '(vacia)'),
                'catalog_id' => 0,
            );
        }

        $meta = array(
            'data_type' => $metricMeta['data_type'],
            'numeric' => !empty($metricMeta['numeric']),
        );
        $effectiveFormatHint = $formatHint;
        if (empty($metricMeta['numeric'])) {
            $effectiveFormatHint = isset($metricMeta['data_type']) ? (string)$metricMeta['data_type'] : 'text';
        }

        $catalogId = isset($baseRow['base_line_item_catalog_id']) ? (int)$baseRow['base_line_item_catalog_id'] : 0;
        $allowedCatalogIds = reports_v2_field_catalog_ids($field);
        $catalogIsAllowed = empty($allowedCatalogIds) || in_array($catalogId, $allowedCatalogIds, true);
        if ($catalogId <= 0 || !$catalogIsAllowed) {
            $rawValue = '';
        } else {
            $rawValue = reports_v2_current_line_item_metric_value($baseRow, $metricCode);
        }

        return array(
            'raw' => $rawValue,
            'display' => $rawValue === '' ? '' : pms_report_format_value($rawValue, $meta, $currency, $effectiveFormatHint),
            'meta' => $meta,
            'error' => '',
            'catalog_id' => $catalogId,
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

if (!function_exists('reports_v2_filter_line_item_catalogs_for_template')) {
    function reports_v2_filter_line_item_catalogs_for_template(array $lineItemCatalogs, array $templateFields)
    {
        if (empty($templateFields)) {
            return array();
        }

        $allowedCatalogIds = array();
        foreach ($templateFields as $field) {
            $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
            if ($fieldType !== 'line_item') {
                continue;
            }
            foreach (reports_v2_field_catalog_ids($field) as $catalogId) {
                $catalogId = (int)$catalogId;
                if ($catalogId > 0) {
                    $allowedCatalogIds[$catalogId] = true;
                }
            }
        }

        if (empty($allowedCatalogIds)) {
            return array();
        }

        $filtered = array();
        foreach ($lineItemCatalogs as $catalog) {
            $catalogId = isset($catalog['id_line_item_catalog']) ? (int)$catalog['id_line_item_catalog'] : 0;
            if ($catalogId > 0 && isset($allowedCatalogIds[$catalogId])) {
                $filtered[] = $catalog;
            }
        }

        return $filtered;
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
    function reports_v2_fetch_report_base_rows(PDO $pdo, $companyId, array $filters, $limit = 500, $rowSource = 'reservation', $lineItemTypeScope = '')
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
            $searchParts = array(
                'r.code LIKE ?',
                'g.full_name LIKE ?',
                'g.names LIKE ?',
                'rm.name LIKE ?',
                'rm.code LIKE ?',
            );
            if ($rowSource === 'line_item') {
                $searchParts[] = 'COALESCE(li.item_name, lic.item_name, \'\') LIKE ?';
                $searchParts[] = '(EXISTS ('
                    . ' SELECT 1'
                    . ' FROM line_item_hierarchy h_search'
                    . ' JOIN line_item li_parent_search'
                    . '   ON li_parent_search.id_line_item = h_search.id_line_item_parent'
                    . '  AND li_parent_search.deleted_at IS NULL'
                    . '  AND COALESCE(li_parent_search.is_active, 1) = 1'
                    . ' WHERE h_search.id_line_item_child = li.id_line_item'
                    . '   AND h_search.deleted_at IS NULL'
                    . '   AND COALESCE(h_search.is_active, 1) = 1'
                    . '   AND COALESCE(li_parent_search.item_name, \'\') LIKE ?'
                    . '))';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            if ($rowSource === 'line_item') {
                $params[] = $searchLike;
                $params[] = $searchLike;
            }
        }

        $dateType = isset($filters['date_type']) ? trim((string)$filters['date_type']) : 'check_in_date';
        $dateTypeOptions = reports_v2_date_type_options();
        if (!isset($dateTypeOptions[$dateType])) {
            $dateType = 'check_in_date';
        }
        $dateFrom = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
        $dateTo = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
        if ($dateFrom !== '' || $dateTo !== '') {
            if ($rowSource === 'line_item') {
                if ($dateType === 'created_at') {
                    $dateColumn = 'DATE(li.created_at)';
                } elseif ($dateType === 'service_date') {
                    $dateColumn = 'li.service_date';
                } elseif ($dateType === 'check_out_date') {
                    $dateColumn = 'r.check_out_date';
                } else {
                    $dateColumn = 'r.check_in_date';
                }
                if ($dateFrom !== '') {
                    $where[] = $dateColumn . ' >= ?';
                    $params[] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $where[] = $dateColumn . ' <= ?';
                    $params[] = $dateTo;
                }
            } else {
                if ($dateType === 'created_at') {
                    $dateColumn = 'DATE(r.created_at)';
                    if ($dateFrom !== '') {
                        $where[] = $dateColumn . ' >= ?';
                        $params[] = $dateFrom;
                    }
                    if ($dateTo !== '') {
                        $where[] = $dateColumn . ' <= ?';
                        $params[] = $dateTo;
                    }
                } elseif ($dateType === 'service_date') {
                    $serviceDateExistsSql = 'EXISTS (
                        SELECT 1
                          FROM folio f_date
                          JOIN line_item li_date
                            ON li_date.id_folio = f_date.id_folio
                           AND li_date.deleted_at IS NULL
                           AND COALESCE(li_date.is_active, 1) = 1
                         WHERE f_date.id_reservation = r.id_reservation
                           AND f_date.deleted_at IS NULL
                           AND COALESCE(f_date.is_active, 1) = 1
                           AND (
                                f_date.status IS NULL
                                OR LOWER(TRIM(f_date.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )';
                    if ($dateFrom !== '') {
                        $serviceDateExistsSql .= ' AND li_date.service_date >= ?';
                        $params[] = $dateFrom;
                    }
                    if ($dateTo !== '') {
                        $serviceDateExistsSql .= ' AND li_date.service_date <= ?';
                        $params[] = $dateTo;
                    }
                    $serviceDateExistsSql .= ')';
                    $where[] = $serviceDateExistsSql;
                } else {
                    $dateColumn = $dateType === 'check_out_date' ? 'r.check_out_date' : 'r.check_in_date';
                    if ($dateFrom !== '') {
                        $where[] = $dateColumn . ' >= ?';
                        $params[] = $dateFrom;
                    }
                    if ($dateTo !== '') {
                        $where[] = $dateColumn . ' <= ?';
                        $params[] = $dateTo;
                    }
                }
            }
        }

        if ($rowSource === 'line_item') {
            $lineItemTypeOptions = reports_v2_line_item_row_type_options();
            $lineItemTypeScope = trim((string)$lineItemTypeScope);
            if (
                $lineItemTypeScope !== ''
                && $lineItemTypeScope !== 'all'
                && isset($lineItemTypeOptions[$lineItemTypeScope])
            ) {
                $where[] = 'COALESCE(li.item_type, \'\') = ?';
                $params[] = $lineItemTypeScope;
            }
            $where[] = 'f.deleted_at IS NULL';
            $where[] = 'COALESCE(f.is_active, 1) = 1';
            $where[] = '(f.status IS NULL OR LOWER(TRIM(f.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\'))';
            $where[] = 'li.deleted_at IS NULL';
            $where[] = 'COALESCE(li.is_active, 1) = 1';
            $where[] = 'li.id_line_item_catalog IS NOT NULL';
            $where[] = '(li.status IS NULL OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\'))';

            $sql = 'SELECT
                        r.id_reservation,
                        r.id_guest,
                        r.id_property AS property_id,
                        r.id_room AS room_id,
                        r.id_category AS category_id,
                        r.id_rateplan AS rateplan_id,
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
                        (
                            SELECT COUNT(*)
                              FROM folio f2
                             WHERE f2.id_reservation = r.id_reservation
                               AND f2.deleted_at IS NULL
                               AND COALESCE(f2.is_active, 1) = 1
                               AND (
                                    f2.status IS NULL
                                    OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                               )
                        ) AS reservation_folio_count,
                        (
                            SELECT GROUP_CONCAT(
                                DISTINCT COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\')
                                ORDER BY COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\') SEPARATOR \' | \'
                            )
                              FROM folio f2
                             WHERE f2.id_reservation = r.id_reservation
                               AND f2.deleted_at IS NULL
                               AND COALESCE(f2.is_active, 1) = 1
                               AND (
                                    f2.status IS NULL
                                    OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                               )
                        ) AS reservation_folio_names,
                        (
                            SELECT GROUP_CONCAT(
                                DISTINCT COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\')
                                ORDER BY COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\') SEPARATOR \' | \'
                            )
                              FROM folio f2
                             WHERE f2.id_reservation = r.id_reservation
                               AND f2.deleted_at IS NULL
                               AND COALESCE(f2.is_active, 1) = 1
                               AND (
                                    f2.status IS NULL
                                    OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                               )
                        ) AS reservation_folio_statuses,
                        (
                            SELECT COALESCE(SUM(COALESCE(f2.total_cents, 0)), 0)
                              FROM folio f2
                             WHERE f2.id_reservation = r.id_reservation
                               AND f2.deleted_at IS NULL
                               AND COALESCE(f2.is_active, 1) = 1
                               AND (
                                    f2.status IS NULL
                                    OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                               )
                        ) AS reservation_folio_total_cents,
                        (
                            SELECT COALESCE(SUM(COALESCE(f2.balance_cents, 0)), 0)
                              FROM folio f2
                             WHERE f2.id_reservation = r.id_reservation
                               AND f2.deleted_at IS NULL
                               AND COALESCE(f2.is_active, 1) = 1
                               AND (
                                    f2.status IS NULL
                                    OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                               )
                        ) AS reservation_folio_balance_cents,
                        r.created_at AS reservation_created_at,
                        DATE(r.created_at) AS reservation_created_date,
                        g.names AS guest_names,
                        g.last_name AS guest_last_name,
                        g.maiden_name AS guest_maiden_name,
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
                        rp.name AS rateplan_name,
                        li.id_line_item AS base_line_item_id,
                        li.id_line_item_catalog AS base_line_item_catalog_id,
                        f.id_folio AS base_line_item_folio_id,
                        COALESCE(NULLIF(TRIM(f.folio_name), \'\'), \'Principal\') AS base_line_item_folio_name,
                        COALESCE(NULLIF(TRIM(f.status), \'\'), \'open\') AS base_line_item_folio_status,
                        COALESCE(f.total_cents, 0) AS base_line_item_folio_total_cents,
                        COALESCE(f.balance_cents, 0) AS base_line_item_folio_balance_cents,
                        COALESCE(li.item_type, \'\') AS base_line_item_type,
                        li.service_date AS base_line_item_service_date,
                        DATE(li.created_at) AS base_line_item_created_date,
                        COALESCE(li.quantity, 0) AS base_line_item_quantity,
                        COALESCE(li.unit_price_cents, 0) AS base_line_item_unit_price_cents,
                        COALESCE(li.amount_cents, 0) AS base_line_item_amount_cents,
                        COALESCE(li.paid_cents, 0) AS base_line_item_paid_cents,
                        COALESCE(li.description, \'\') AS base_line_item_description,
                        COALESCE(li.item_name, lic.item_name, \'\') AS base_line_item_name,
                        CASE
                            WHEN COALESCE(li.item_name, lic.item_name, \'\') <> \'\' THEN TRIM(CONCAT(
                                COALESCE(li.item_name, lic.item_name, \'\'),
                                CASE
                                    WHEN COALESCE((
                                        SELECT COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name)
                                        FROM line_item_hierarchy h_parent
                                        JOIN line_item li_parent
                                          ON li_parent.id_line_item = h_parent.id_line_item_parent
                                         AND li_parent.deleted_at IS NULL
                                         AND COALESCE(li_parent.is_active, 1) = 1
                                        LEFT JOIN line_item_catalog parent_lic
                                          ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                                         AND parent_lic.deleted_at IS NULL
                                        WHERE h_parent.id_line_item_child = li.id_line_item
                                          AND h_parent.deleted_at IS NULL
                                          AND COALESCE(h_parent.is_active, 1) = 1
                                        ORDER BY h_parent.id_line_item_parent
                                        LIMIT 1
                                    ), \'\') <> \'\' THEN CONCAT(\' - \', (
                                        SELECT COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name)
                                        FROM line_item_hierarchy h_parent
                                        JOIN line_item li_parent
                                          ON li_parent.id_line_item = h_parent.id_line_item_parent
                                         AND li_parent.deleted_at IS NULL
                                         AND COALESCE(li_parent.is_active, 1) = 1
                                        LEFT JOIN line_item_catalog parent_lic
                                          ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                                         AND parent_lic.deleted_at IS NULL
                                        WHERE h_parent.id_line_item_child = li.id_line_item
                                          AND h_parent.deleted_at IS NULL
                                          AND COALESCE(h_parent.is_active, 1) = 1
                                        ORDER BY h_parent.id_line_item_parent
                                        LIMIT 1
                                    ))
                                    ELSE \'\'
                                END
                            ))
                            ELSE \'\'
                        END AS base_line_item_name_parent
                    FROM reservation r
                    JOIN property p
                      ON p.id_property = r.id_property
                    JOIN folio f
                      ON f.id_reservation = r.id_reservation
                    JOIN line_item li
                      ON li.id_folio = f.id_folio
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
                    LEFT JOIN line_item_catalog lic
                      ON lic.id_line_item_catalog = li.id_line_item_catalog
                     AND lic.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                    ORDER BY COALESCE(li.service_date, r.check_in_date) DESC, li.id_line_item DESC
                    LIMIT ' . (int)$limit;
        } else {
            $sql = 'SELECT
                    r.id_reservation,
                    r.id_guest,
                    r.id_property AS property_id,
                    r.id_room AS room_id,
                    r.id_category AS category_id,
                    r.id_rateplan AS rateplan_id,
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
                    (
                        SELECT COUNT(*)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_count,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\')
                            ORDER BY COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\') SEPARATOR \' | \'
                        )
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_names,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\')
                            ORDER BY COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\') SEPARATOR \' | \'
                        )
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_statuses,
                    (
                        SELECT COALESCE(SUM(COALESCE(f2.total_cents, 0)), 0)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_total_cents,
                    (
                        SELECT COALESCE(SUM(COALESCE(f2.balance_cents, 0)), 0)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_balance_cents,
                    r.created_at AS reservation_created_at,
                    DATE(r.created_at) AS reservation_created_date,
                    (
                        SELECT MIN(li2.service_date)
                          FROM folio f2
                          JOIN line_item li2
                            ON li2.id_folio = f2.id_folio
                           AND li2.deleted_at IS NULL
                           AND COALESCE(li2.is_active, 1) = 1
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_service_date,
                    g.names AS guest_names,
                    g.last_name AS guest_last_name,
                    g.maiden_name AS guest_maiden_name,
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
        }

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
                    MAX(COALESCE(NULLIF(TRIM(li.item_name), \'\'), lic.item_name, \'\')) AS item_name,
                    MAX(COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name, \'\')) AS item_parent_name,
                    CASE
                        WHEN COUNT(DISTINCT COALESCE(li.item_type, \'\')) = 1 THEN MAX(COALESCE(li.item_type, \'\'))
                        ELSE \'mixed\'
                    END AS item_type,
                    MIN(li.service_date) AS service_date,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(li.id_folio)
                        ELSE 0
                    END AS folio_id,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(NULLIF(TRIM(f.folio_name), \'\'), \'Principal\'))
                        ELSE \'Mixto\'
                    END AS folio_name,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(NULLIF(TRIM(f.status), \'\'), \'open\'))
                        ELSE \'Mixto\'
                    END AS folio_status,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(f.total_cents, 0))
                        ELSE 0
                    END AS folio_total_cents,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(f.balance_cents, 0))
                        ELSE 0
                    END AS folio_balance_cents,
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
                LEFT JOIN line_item_hierarchy h_parent
                  ON h_parent.id_line_item_child = li.id_line_item
                 AND h_parent.deleted_at IS NULL
                 AND COALESCE(h_parent.is_active, 1) = 1
                LEFT JOIN line_item li_parent
                  ON li_parent.id_line_item = h_parent.id_line_item_parent
                 AND li_parent.deleted_at IS NULL
                 AND COALESCE(li_parent.is_active, 1) = 1
                LEFT JOIN line_item_catalog lic
                  ON lic.id_line_item_catalog = li.id_line_item_catalog
                 AND lic.deleted_at IS NULL
                LEFT JOIN line_item_catalog parent_lic
                  ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                 AND parent_lic.deleted_at IS NULL
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
                'folio_id' => isset($row['folio_id']) ? (int)$row['folio_id'] : 0,
                'folio_name' => (
                    (isset($row['folio_id']) ? (int)$row['folio_id'] : 0) > 0
                    && trim((string)(isset($row['folio_name']) ? $row['folio_name'] : '')) !== ''
                )
                    ? ((int)$row['folio_id'] . ' - ' . trim((string)$row['folio_name']))
                    : (isset($row['folio_name']) ? (string)$row['folio_name'] : ''),
                'folio_status' => isset($row['folio_status']) ? (string)$row['folio_status'] : '',
                'folio_total_cents' => isset($row['folio_total_cents']) ? (int)$row['folio_total_cents'] : 0,
                'folio_balance_cents' => isset($row['folio_balance_cents']) ? (int)$row['folio_balance_cents'] : 0,
                'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0,
                'paid_cents' => isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0,
                'quantity' => isset($row['quantity']) ? (float)$row['quantity'] : 0.0,
                'unit_price_cents' => isset($row['unit_price_cents']) ? (int)$row['unit_price_cents'] : 0,
            );
        }
        return $metrics;
    }
}

if (!function_exists('reports_v2_fetch_line_item_base_rows_for_reservations')) {
    function reports_v2_fetch_line_item_base_rows_for_reservations(PDO $pdo, array $reservationIds, $lineItemTypeScope = '')
    {
        $reservationIds = array_values(array_filter(array_map('intval', $reservationIds)));
        if (empty($reservationIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $params = $reservationIds;
        $where = array(
            'r.id_reservation IN (' . $placeholders . ')',
            'f.deleted_at IS NULL',
            'COALESCE(f.is_active, 1) = 1',
            '(f.status IS NULL OR LOWER(TRIM(f.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\'))',
            'li.deleted_at IS NULL',
            'COALESCE(li.is_active, 1) = 1',
            'li.id_line_item_catalog IS NOT NULL',
            '(li.status IS NULL OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\'))',
        );
        $lineItemTypeScope = trim((string)$lineItemTypeScope);
        $lineItemTypeOptions = reports_v2_line_item_row_type_options();
        if (
            $lineItemTypeScope !== ''
            && $lineItemTypeScope !== 'all'
            && isset($lineItemTypeOptions[$lineItemTypeScope])
        ) {
            $where[] = 'COALESCE(li.item_type, \'\') = ?';
            $params[] = $lineItemTypeScope;
        }

        $sql = 'SELECT
                    r.id_reservation,
                    r.id_guest,
                    r.id_property AS property_id,
                    r.id_room AS room_id,
                    r.id_category AS category_id,
                    r.id_rateplan AS rateplan_id,
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
                    (
                        SELECT COUNT(*)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_count,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\')
                            ORDER BY COALESCE(NULLIF(TRIM(f2.folio_name), \'\'), \'Principal\') SEPARATOR \' | \'
                        )
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_names,
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\')
                            ORDER BY COALESCE(NULLIF(TRIM(f2.status), \'\'), \'open\') SEPARATOR \' | \'
                        )
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_statuses,
                    (
                        SELECT COALESCE(SUM(COALESCE(f2.total_cents, 0)), 0)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_total_cents,
                    (
                        SELECT COALESCE(SUM(COALESCE(f2.balance_cents, 0)), 0)
                          FROM folio f2
                         WHERE f2.id_reservation = r.id_reservation
                           AND f2.deleted_at IS NULL
                           AND COALESCE(f2.is_active, 1) = 1
                           AND (
                                f2.status IS NULL
                                OR LOWER(TRIM(f2.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                           )
                    ) AS reservation_folio_balance_cents,
                    r.created_at AS reservation_created_at,
                    DATE(r.created_at) AS reservation_created_date,
                    g.names AS guest_names,
                    g.last_name AS guest_last_name,
                    g.maiden_name AS guest_maiden_name,
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
                    rp.name AS rateplan_name,
                    li.id_line_item AS base_line_item_id,
                    li.id_line_item_catalog AS base_line_item_catalog_id,
                    f.id_folio AS base_line_item_folio_id,
                    COALESCE(NULLIF(TRIM(f.folio_name), \'\'), \'Principal\') AS base_line_item_folio_name,
                    COALESCE(NULLIF(TRIM(f.status), \'\'), \'open\') AS base_line_item_folio_status,
                    COALESCE(f.total_cents, 0) AS base_line_item_folio_total_cents,
                    COALESCE(f.balance_cents, 0) AS base_line_item_folio_balance_cents,
                    COALESCE(li.item_type, \'\') AS base_line_item_type,
                    li.service_date AS base_line_item_service_date,
                    DATE(li.created_at) AS base_line_item_created_date,
                    COALESCE(li.quantity, 0) AS base_line_item_quantity,
                    COALESCE(li.unit_price_cents, 0) AS base_line_item_unit_price_cents,
                    COALESCE(li.amount_cents, 0) AS base_line_item_amount_cents,
                    COALESCE(li.paid_cents, 0) AS base_line_item_paid_cents,
                    COALESCE(li.description, \'\') AS base_line_item_description,
                    COALESCE(li.item_name, lic.item_name, \'\') AS base_line_item_name,
                    CASE
                        WHEN COALESCE(li.item_name, lic.item_name, \'\') <> \'\' THEN TRIM(CONCAT(
                            COALESCE(li.item_name, lic.item_name, \'\'),
                            CASE
                                WHEN COALESCE((
                                    SELECT COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name)
                                    FROM line_item_hierarchy h_parent
                                    JOIN line_item li_parent
                                      ON li_parent.id_line_item = h_parent.id_line_item_parent
                                     AND li_parent.deleted_at IS NULL
                                     AND COALESCE(li_parent.is_active, 1) = 1
                                    LEFT JOIN line_item_catalog parent_lic
                                      ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                                     AND parent_lic.deleted_at IS NULL
                                    WHERE h_parent.id_line_item_child = li.id_line_item
                                      AND h_parent.deleted_at IS NULL
                                      AND COALESCE(h_parent.is_active, 1) = 1
                                    ORDER BY h_parent.id_line_item_parent
                                    LIMIT 1
                                ), \'\') <> \'\' THEN CONCAT(\' - \', (
                                    SELECT COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name)
                                    FROM line_item_hierarchy h_parent
                                    JOIN line_item li_parent
                                      ON li_parent.id_line_item = h_parent.id_line_item_parent
                                     AND li_parent.deleted_at IS NULL
                                     AND COALESCE(li_parent.is_active, 1) = 1
                                    LEFT JOIN line_item_catalog parent_lic
                                      ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                                     AND parent_lic.deleted_at IS NULL
                                    WHERE h_parent.id_line_item_child = li.id_line_item
                                      AND h_parent.deleted_at IS NULL
                                      AND COALESCE(h_parent.is_active, 1) = 1
                                    ORDER BY h_parent.id_line_item_parent
                                    LIMIT 1
                                ))
                                ELSE \'\'
                            END
                        ))
                        ELSE \'\'
                    END AS base_line_item_name_parent
                FROM reservation r
                JOIN property p
                  ON p.id_property = r.id_property
                JOIN folio f
                  ON f.id_reservation = r.id_reservation
                JOIN line_item li
                  ON li.id_folio = f.id_folio
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
                LEFT JOIN line_item_catalog lic
                  ON lic.id_line_item_catalog = li.id_line_item_catalog
                 AND lic.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.check_in_date DESC, r.id_reservation DESC, COALESCE(li.service_date, DATE(li.created_at)) ASC, li.id_line_item ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('reports_v2_fetch_line_item_tree_metrics')) {
    function reports_v2_fetch_line_item_tree_metrics(PDO $pdo, array $baseLineItemIds)
    {
        $baseLineItemIds = array_values(array_filter(array_map('intval', $baseLineItemIds)));
        if (empty($baseLineItemIds)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($baseLineItemIds), '?'));
        $sql = 'WITH RECURSIVE li_tree AS (
                    SELECT
                        li.id_line_item AS root_line_item_id,
                        li.id_line_item AS line_item_id
                    FROM line_item li
                    WHERE li.id_line_item IN (' . $placeholders . ')
                      AND li.deleted_at IS NULL
                      AND COALESCE(li.is_active, 1) = 1
                    UNION DISTINCT
                    SELECT
                        t.root_line_item_id,
                        h.id_line_item_child AS line_item_id
                    FROM li_tree t
                    JOIN line_item_hierarchy h
                      ON h.id_line_item_parent = t.line_item_id
                     AND h.deleted_at IS NULL
                     AND COALESCE(h.is_active, 1) = 1
                    JOIN line_item li_child
                      ON li_child.id_line_item = h.id_line_item_child
                     AND li_child.deleted_at IS NULL
                     AND COALESCE(li_child.is_active, 1) = 1
                    UNION DISTINCT
                    SELECT
                        t.root_line_item_id,
                        h.id_line_item_parent AS line_item_id
                    FROM li_tree t
                    JOIN line_item_hierarchy h
                      ON h.id_line_item_child = t.line_item_id
                     AND h.deleted_at IS NULL
                     AND COALESCE(h.is_active, 1) = 1
                    JOIN line_item li_parent
                      ON li_parent.id_line_item = h.id_line_item_parent
                     AND li_parent.deleted_at IS NULL
                     AND COALESCE(li_parent.is_active, 1) = 1
                )
                SELECT
                    t.root_line_item_id,
                    li.id_line_item_catalog,
                    MAX(COALESCE(NULLIF(TRIM(li.item_name), \'\'), lic.item_name, \'\')) AS item_name,
                    MAX(COALESCE(NULLIF(TRIM(li_parent.item_name), \'\'), parent_lic.item_name, \'\')) AS item_parent_name,
                    CASE
                        WHEN COUNT(DISTINCT COALESCE(li.item_type, \'\')) = 1 THEN MAX(COALESCE(li.item_type, \'\'))
                        ELSE \'mixed\'
                    END AS item_type,
                    MIN(li.service_date) AS service_date,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(li.id_folio)
                        ELSE 0
                    END AS folio_id,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(NULLIF(TRIM(f.folio_name), \'\'), \'Principal\'))
                        ELSE \'Mixto\'
                    END AS folio_name,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(NULLIF(TRIM(f.status), \'\'), \'open\'))
                        ELSE \'Mixto\'
                    END AS folio_status,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(f.total_cents, 0))
                        ELSE 0
                    END AS folio_total_cents,
                    CASE
                        WHEN COUNT(DISTINCT li.id_folio) = 1 THEN MAX(COALESCE(f.balance_cents, 0))
                        ELSE 0
                    END AS folio_balance_cents,
                    SUM(COALESCE(li.amount_cents, 0)) AS amount_cents,
                    SUM(COALESCE(li.paid_cents, 0)) AS paid_cents,
                    SUM(COALESCE(li.quantity, 0)) AS quantity,
                    CASE
                        WHEN ABS(SUM(COALESCE(li.quantity, 0))) > 0.000001 THEN ROUND(SUM(COALESCE(li.unit_price_cents, 0) * COALESCE(li.quantity, 0)) / SUM(COALESCE(li.quantity, 0)))
                        ELSE MAX(COALESCE(li.unit_price_cents, 0))
                    END AS unit_price_cents
                FROM li_tree t
                JOIN line_item li
                  ON li.id_line_item = t.line_item_id
                 AND li.deleted_at IS NULL
                 AND COALESCE(li.is_active, 1) = 1
                JOIN folio f
                  ON f.id_folio = li.id_folio
                 AND f.deleted_at IS NULL
                 AND COALESCE(f.is_active, 1) = 1
                LEFT JOIN line_item_hierarchy h_parent
                  ON h_parent.id_line_item_child = li.id_line_item
                 AND h_parent.deleted_at IS NULL
                 AND COALESCE(h_parent.is_active, 1) = 1
                LEFT JOIN line_item li_parent
                  ON li_parent.id_line_item = h_parent.id_line_item_parent
                 AND li_parent.deleted_at IS NULL
                 AND COALESCE(li_parent.is_active, 1) = 1
                LEFT JOIN line_item_catalog lic
                  ON lic.id_line_item_catalog = li.id_line_item_catalog
                 AND lic.deleted_at IS NULL
                LEFT JOIN line_item_catalog parent_lic
                  ON parent_lic.id_line_item_catalog = li_parent.id_line_item_catalog
                 AND parent_lic.deleted_at IS NULL
                WHERE li.id_line_item_catalog IS NOT NULL
                  AND (
                    f.status IS NULL
                    OR LOWER(TRIM(f.status)) NOT IN (\'void\', \'canceled\', \'cancelled\', \'deleted\')
                  )
                  AND (
                    li.status IS NULL
                    OR LOWER(TRIM(li.status)) NOT IN (\'void\', \'canceled\', \'cancelled\')
                  )
                GROUP BY t.root_line_item_id, li.id_line_item_catalog';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($baseLineItemIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $metrics = array();
        foreach ($rows as $row) {
            $rootLineItemId = isset($row['root_line_item_id']) ? (int)$row['root_line_item_id'] : 0;
            $catalogId = isset($row['id_line_item_catalog']) ? (int)$row['id_line_item_catalog'] : 0;
            if ($rootLineItemId <= 0 || $catalogId <= 0) {
                continue;
            }
            if (!isset($metrics[$rootLineItemId])) {
                $metrics[$rootLineItemId] = array();
            }
            $itemName = isset($row['item_name']) ? trim((string)$row['item_name']) : '';
            $itemParentName = isset($row['item_parent_name']) ? trim((string)$row['item_parent_name']) : '';
            $metrics[$rootLineItemId][$catalogId] = array(
                'item_name' => $itemName,
                'item_name_parent' => $itemName !== ''
                    ? trim($itemName . ($itemParentName !== '' ? ' - ' . $itemParentName : ''))
                    : '',
                'item_type' => isset($row['item_type']) ? (string)$row['item_type'] : '',
                'service_date' => isset($row['service_date']) ? (string)$row['service_date'] : '',
                'folio_id' => isset($row['folio_id']) ? (int)$row['folio_id'] : 0,
                'folio_name' => (
                    (isset($row['folio_id']) ? (int)$row['folio_id'] : 0) > 0
                    && trim((string)(isset($row['folio_name']) ? $row['folio_name'] : '')) !== ''
                )
                    ? ((int)$row['folio_id'] . ' - ' . trim((string)$row['folio_name']))
                    : (isset($row['folio_name']) ? (string)$row['folio_name'] : ''),
                'folio_status' => isset($row['folio_status']) ? (string)$row['folio_status'] : '',
                'folio_total_cents' => isset($row['folio_total_cents']) ? (int)$row['folio_total_cents'] : 0,
                'folio_balance_cents' => isset($row['folio_balance_cents']) ? (int)$row['folio_balance_cents'] : 0,
                'amount_cents' => isset($row['amount_cents']) ? (int)$row['amount_cents'] : 0,
                'paid_cents' => isset($row['paid_cents']) ? (int)$row['paid_cents'] : 0,
                'quantity' => isset($row['quantity']) ? (float)$row['quantity'] : 0.0,
                'unit_price_cents' => isset($row['unit_price_cents']) ? (int)$row['unit_price_cents'] : 0,
            );
        }
        return $metrics;
    }
}

if (!function_exists('reports_v2_fetch_editable_reservation_context')) {
    function reports_v2_fetch_editable_reservation_context(PDO $pdo, $companyId, $reservationId)
    {
        $stmt = $pdo->prepare(
            'SELECT
                r.id_reservation,
                r.id_guest,
                r.id_property,
                r.id_room,
                r.id_category,
                r.id_rateplan,
                r.code AS reservation_code,
                r.channel_ref,
                r.check_in_date,
                r.check_out_date,
                r.eta,
                r.etd,
                r.adults,
                r.children,
                r.infants,
                p.code AS property_code,
                p.name AS property_name,
                rm.code AS room_code,
                rm.name AS room_name,
                rc.code AS category_code,
                rc.name AS category_name,
                rp.code AS rateplan_code,
                rp.name AS rateplan_name,
                g.names AS guest_names,
                g.last_name AS guest_last_name,
                g.full_name AS guest_full_name,
                g.email AS guest_email,
                g.phone AS guest_phone,
                g.nationality AS guest_nationality,
                g.maiden_name AS guest_maiden_name
             FROM reservation r
             JOIN property p
               ON p.id_property = r.id_property
              AND p.deleted_at IS NULL
             LEFT JOIN room rm
               ON rm.id_room = r.id_room
              AND rm.deleted_at IS NULL
             LEFT JOIN roomcategory rc
               ON rc.id_category = r.id_category
              AND rc.deleted_at IS NULL
             LEFT JOIN rateplan rp
               ON rp.id_rateplan = r.id_rateplan
              AND rp.deleted_at IS NULL
             LEFT JOIN guest g
               ON g.id_guest = r.id_guest
              AND g.deleted_at IS NULL
             WHERE r.id_reservation = ?
               AND r.deleted_at IS NULL
               AND p.id_company = ?
             LIMIT 1'
        );
        $stmt->execute(array((int)$reservationId, (int)$companyId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

if (!function_exists('reports_v2_reservation_field_cell_payload')) {
    function reports_v2_reservation_field_cell_payload($rawValue, array $meta, $currency = 'MXN', $formatHint = 'auto')
    {
        if (function_exists('pms_report_is_entity_reference_value') && pms_report_is_entity_reference_value($rawValue)) {
            $entityId = isset($rawValue['id']) ? (int)$rawValue['id'] : 0;
            $displayValue = isset($rawValue['label']) ? (string)$rawValue['label'] : '';
            return array(
                'raw' => $entityId > 0 ? (string)$entityId : '0',
                'display' => $displayValue,
                'filter_value' => trim($displayValue) !== '' ? $displayValue : '__EMPTY__',
                'data_type' => isset($meta['data_type']) ? (string)$meta['data_type'] : 'text',
                'href' => isset($rawValue['href']) ? (string)$rawValue['href'] : '',
                'entity_type' => isset($rawValue['entity_type']) ? (string)$rawValue['entity_type'] : '',
            );
        }

        $displayValue = pms_report_format_value($rawValue, $meta, $currency, $formatHint);
        return array(
            'raw' => is_scalar($rawValue) || $rawValue === null ? (string)$rawValue : '',
            'display' => (string)$displayValue,
            'filter_value' => trim((string)$displayValue) !== '' ? (string)$displayValue : '__EMPTY__',
            'data_type' => isset($meta['data_type']) ? (string)$meta['data_type'] : 'text',
            'href' => '',
            'entity_type' => '',
        );
    }
}

if (!function_exists('reports_v2_build_inline_reservation_field_payload')) {
    function reports_v2_build_inline_reservation_field_payload(array $templateFields, array $context, $currency = 'MXN')
    {
        $reservationCatalog = pms_report_reservation_field_catalog();
        $payload = array();

        foreach ($templateFields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
            $fieldCode = isset($field['reservation_field_code']) ? (string)$field['reservation_field_code'] : '';
            if ($fieldId <= 0 || $fieldType !== 'reservation' || !isset($reservationCatalog[$fieldCode])) {
                continue;
            }

            $meta = $reservationCatalog[$fieldCode];
            $rawValue = pms_report_reservation_field_value($context, $fieldCode);
            $formatHint = isset($field['format_hint']) ? (string)$field['format_hint'] : 'auto';
            $cellPayload = reports_v2_reservation_field_cell_payload($rawValue, $meta, $currency, $formatHint);
            $defaultValue = isset($field['default_value']) ? trim((string)$field['default_value']) : '';
            if ($defaultValue !== '' && reports_v2_value_is_empty_or_zero($rawValue, $meta)) {
                $cellPayload['display'] = $defaultValue;
                $cellPayload['filter_value'] = $defaultValue;
                $cellPayload['href'] = '';
            }

            $payload[$fieldId] = array(
                'raw' => $cellPayload['raw'],
                'display' => $cellPayload['display'],
                'filter_value' => $cellPayload['filter_value'],
                'data_type' => $cellPayload['data_type'],
                'href' => $cellPayload['href'],
                'entity_type' => $cellPayload['entity_type'],
            );
        }

        return $payload;
    }
}

if (!function_exists('reports_v2_normalize_inline_edit_value')) {
    function reports_v2_normalize_inline_edit_value($fieldCode, $value, array $editableCatalog = array())
    {
        if (empty($editableCatalog)) {
            $editableCatalog = function_exists('pms_report_reservation_editable_field_catalog')
                ? pms_report_reservation_editable_field_catalog()
                : array();
        }
        $fieldCode = trim((string)$fieldCode);
        $rawValue = is_array($value) ? '' : trim((string)$value);
        if (!isset($editableCatalog[$fieldCode])) {
            return $rawValue;
        }

        $inputType = isset($editableCatalog[$fieldCode]['input_type']) ? (string)$editableCatalog[$fieldCode]['input_type'] : 'text';
        if ($inputType === 'number') {
            if ($rawValue === '') {
                return 0;
            }
            if (!preg_match('/^-?\d+$/', $rawValue)) {
                throw new RuntimeException('Valor numerico invalido para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            return max(0, (int)$rawValue);
        }
        if ($inputType === 'date') {
            if ($rawValue === '') {
                throw new RuntimeException('La fecha es obligatoria para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            $dt = DateTime::createFromFormat('Y-m-d', $rawValue);
            if (!$dt || $dt->format('Y-m-d') !== $rawValue) {
                throw new RuntimeException('Fecha invalida para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            return $rawValue;
        }
        if ($inputType === 'select') {
            if ($rawValue === '' || $rawValue === '0') {
                if (!empty($editableCatalog[$fieldCode]['allow_empty'])) {
                    return 0;
                }
                throw new RuntimeException('Selecciona una opcion valida para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            if (!preg_match('/^\d+$/', $rawValue)) {
                throw new RuntimeException('Seleccion invalida para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            $normalizedId = (int)$rawValue;
            $allowedValues = array();
            if (!empty($editableCatalog[$fieldCode]['options']) && is_array($editableCatalog[$fieldCode]['options'])) {
                foreach ($editableCatalog[$fieldCode]['options'] as $optionRow) {
                    $allowedValues[(string)(isset($optionRow['value']) ? $optionRow['value'] : '')] = true;
                }
            }
            if (!empty($allowedValues) && !isset($allowedValues[(string)$normalizedId])) {
                throw new RuntimeException('La opcion seleccionada ya no esta disponible para ' . $editableCatalog[$fieldCode]['label'] . '.');
            }
            return $normalizedId;
        }
        return $rawValue;
    }
}

if (!function_exists('reports_v2_save_report_row_edits')) {
    function reports_v2_save_report_row_edits(PDO $pdo, $companyId, $companyCode, $actorUserId, array $templateFields, $reservationId, array $postedValues)
    {
        $context = reports_v2_fetch_editable_reservation_context($pdo, $companyId, $reservationId);
        if (!$context) {
            throw new RuntimeException('No fue posible encontrar la reservacion a editar.');
        }

        $readContextValue = function (array $sourceContext, $fieldCode) {
            switch ((string)$fieldCode) {
                case 'reservation_code':
                    return isset($sourceContext['reservation_code']) ? trim((string)$sourceContext['reservation_code']) : '';
                case 'entity_guest':
                    return isset($sourceContext['id_guest']) ? (int)$sourceContext['id_guest'] : 0;
                case 'entity_property':
                    return isset($sourceContext['id_property']) ? (int)$sourceContext['id_property'] : 0;
                case 'entity_room':
                    return isset($sourceContext['id_room']) ? (int)$sourceContext['id_room'] : 0;
                case 'entity_category':
                    return isset($sourceContext['id_category']) ? (int)$sourceContext['id_category'] : 0;
                case 'entity_rateplan':
                    return isset($sourceContext['id_rateplan']) ? (int)$sourceContext['id_rateplan'] : 0;
                case 'reservation_channel_ref':
                    return isset($sourceContext['channel_ref']) ? trim((string)$sourceContext['channel_ref']) : '';
                case 'reservation_eta':
                    return isset($sourceContext['eta']) ? trim((string)$sourceContext['eta']) : '';
                case 'reservation_etd':
                    return isset($sourceContext['etd']) ? trim((string)$sourceContext['etd']) : '';
                case 'reservation_check_in_date':
                    return isset($sourceContext['check_in_date']) ? trim((string)$sourceContext['check_in_date']) : '';
                case 'reservation_check_out_date':
                    return isset($sourceContext['check_out_date']) ? trim((string)$sourceContext['check_out_date']) : '';
                case 'reservation_adults':
                    return isset($sourceContext['adults']) ? (int)$sourceContext['adults'] : 0;
                case 'reservation_children':
                    return isset($sourceContext['children']) ? (int)$sourceContext['children'] : 0;
                case 'reservation_infants':
                    return isset($sourceContext['infants']) ? (int)$sourceContext['infants'] : 0;
                case 'guest_names':
                    return isset($sourceContext['guest_names']) ? trim((string)$sourceContext['guest_names']) : '';
                case 'guest_last_name':
                    return isset($sourceContext['guest_last_name']) ? trim((string)$sourceContext['guest_last_name']) : '';
                case 'guest_maiden_name':
                    return isset($sourceContext['guest_maiden_name']) ? trim((string)$sourceContext['guest_maiden_name']) : '';
                case 'guest_email':
                    return isset($sourceContext['guest_email']) ? trim((string)$sourceContext['guest_email']) : '';
                case 'guest_phone':
                    return isset($sourceContext['guest_phone']) ? trim((string)$sourceContext['guest_phone']) : '';
                case 'guest_nationality':
                    return isset($sourceContext['guest_nationality']) ? trim((string)$sourceContext['guest_nationality']) : '';
                default:
                    return '';
            }
        };

        $editableCatalog = reports_v2_prepare_editable_input_catalog($pdo, $companyId);
        $reservationSpUpdates = array();
        $reservationDirectUpdates = array();
        $guestUpdates = array();
        $needsReservationPermission = false;
        $needsGuestPermission = false;
        $expectedPersistedValues = array();

        foreach ($templateFields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $fieldCode = isset($field['reservation_field_code']) ? (string)$field['reservation_field_code'] : '';
            if ($fieldId <= 0 || empty($field['is_editable']) || !isset($editableCatalog[$fieldCode])) {
                continue;
            }
            if (!array_key_exists($fieldId, $postedValues)) {
                continue;
            }

            $meta = $editableCatalog[$fieldCode];
            $normalizedValue = reports_v2_normalize_inline_edit_value($fieldCode, $postedValues[$fieldId], $editableCatalog);
            $entity = isset($meta['entity']) ? (string)$meta['entity'] : 'reservation';
            $currentValue = $readContextValue($context, $fieldCode);

            if ((string)$currentValue === (string)$normalizedValue) {
                continue;
            }
            $expectedPersistedValues[$fieldCode] = $normalizedValue;

            if ($entity === 'reservation_sp') {
                $reservationSpUpdates[$fieldCode] = $normalizedValue;
                $needsReservationPermission = true;
            } elseif ($entity === 'reservation') {
                $reservationDirectUpdates[(string)$meta['column']] = $normalizedValue;
                $needsReservationPermission = true;
            } elseif ($entity === 'guest') {
                $guestUpdates[(string)$meta['column']] = $normalizedValue;
                $needsGuestPermission = true;
            }
        }

        if (empty($reservationSpUpdates) && empty($reservationDirectUpdates) && empty($guestUpdates)) {
            return false;
        }

        $propertyCode = isset($context['property_code']) ? (string)$context['property_code'] : '';
        if ($needsReservationPermission) {
            pms_require_permission('reservations.edit', $propertyCode);
        }
        if ($needsGuestPermission) {
            pms_require_permission('guests.edit', $propertyCode);
            if ((int)(isset($context['id_guest']) ? $context['id_guest'] : 0) <= 0) {
                throw new RuntimeException('La reservacion no tiene huesped ligado para editar esos campos.');
            }
        }

        $checkInDate = isset($reservationSpUpdates['reservation_check_in_date'])
            ? (string)$reservationSpUpdates['reservation_check_in_date']
            : (string)$context['check_in_date'];
        $checkOutDate = isset($reservationSpUpdates['reservation_check_out_date'])
            ? (string)$reservationSpUpdates['reservation_check_out_date']
            : (string)$context['check_out_date'];
        if ($checkInDate !== '' && $checkOutDate !== '' && strcmp($checkOutDate, $checkInDate) <= 0) {
            throw new RuntimeException('Check out debe ser posterior a check in.');
        }

        $pdo->beginTransaction();
        try {
            if (!empty($reservationSpUpdates)) {
                $stmtReservation = $pdo->prepare('CALL sp_reservation_update(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmtReservation->execute(array(
                    (string)$companyCode,
                    (int)$reservationId,
                    null,
                    null,
                    null,
                    null,
                    isset($reservationSpUpdates['reservation_check_in_date']) ? (string)$reservationSpUpdates['reservation_check_in_date'] : null,
                    isset($reservationSpUpdates['reservation_check_out_date']) ? (string)$reservationSpUpdates['reservation_check_out_date'] : null,
                    array_key_exists('reservation_adults', $reservationSpUpdates) ? (int)$reservationSpUpdates['reservation_adults'] : null,
                    array_key_exists('reservation_children', $reservationSpUpdates) ? (int)$reservationSpUpdates['reservation_children'] : null,
                    null,
                    null,
                    (int)$actorUserId
                ));
                while ($stmtReservation->nextRowset()) {
                }
                $stmtReservation->closeCursor();
            }

            if (!empty($reservationDirectUpdates)) {
                $setParts = array();
                $params = array();
                foreach ($reservationDirectUpdates as $column => $value) {
                    $setParts[] = $column . ' = ?';
                    $params[] = ($value === '' ? null : $value);
                }
                $params[] = (int)$reservationId;
                $stmtUpdateReservation = $pdo->prepare(
                    'UPDATE reservation
                     SET ' . implode(', ', $setParts) . ',
                         updated_at = NOW()
                     WHERE id_reservation = ?'
                );
                $stmtUpdateReservation->execute($params);
            }

            if (!empty($guestUpdates)) {
                $guestNames = array_key_exists('names', $guestUpdates)
                    ? (string)$guestUpdates['names']
                    : (string)$context['guest_names'];
                $guestLastName = array_key_exists('last_name', $guestUpdates)
                    ? (string)$guestUpdates['last_name']
                    : (string)$context['guest_last_name'];
                $guestMaidenName = array_key_exists('maiden_name', $guestUpdates)
                    ? (string)$guestUpdates['maiden_name']
                    : (isset($context['guest_maiden_name']) ? (string)$context['guest_maiden_name'] : '');
                $fullName = trim(implode(' ', array_filter(array(
                    trim($guestNames),
                    trim($guestLastName),
                    trim($guestMaidenName),
                ))));

                $setParts = array();
                $params = array();
                foreach ($guestUpdates as $column => $value) {
                    $setParts[] = $column . ' = ?';
                    $params[] = ($value === '' ? null : $value);
                }
                $setParts[] = 'full_name = ?';
                $params[] = ($fullName !== '' ? $fullName : null);
                $params[] = (int)$context['id_guest'];
                $stmtUpdateGuest = $pdo->prepare(
                    'UPDATE guest
                     SET ' . implode(', ', $setParts) . ',
                         updated_at = NOW()
                     WHERE id_guest = ?'
                );
                $stmtUpdateGuest->execute($params);
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $refreshedContext = reports_v2_fetch_editable_reservation_context($pdo, $companyId, $reservationId);
        if (!$refreshedContext) {
            throw new RuntimeException('No fue posible confirmar los cambios guardados.');
        }
        $notPersistedLabels = array();
        foreach ($expectedPersistedValues as $fieldCode => $expectedValue) {
            $persistedValue = $readContextValue($refreshedContext, $fieldCode);
            if ((string)$persistedValue !== (string)$expectedValue) {
                $notPersistedLabels[] = isset($editableCatalog[$fieldCode]['label']) ? (string)$editableCatalog[$fieldCode]['label'] : (string)$fieldCode;
            }
        }
        if (!empty($notPersistedLabels)) {
            throw new RuntimeException('No se pudo confirmar el guardado real de: ' . implode(', ', $notPersistedLabels) . '.');
        }

        return true;
    }
}

if (!function_exists('reports_v2_build_report_rows')) {
    function reports_v2_build_report_rows(array $baseRows, array $fields, array $calculationsById, array $lineItemMetrics, array $variableCatalog, array $template = array(), array $lineItemTreeMetrics = array())
    {
        $reservationCatalog = pms_report_reservation_field_catalog();
        $metricOptions = reports_v2_metric_options();
        $reportRows = array();
        $totals = array();
        $rowSource = isset($template['row_source']) && (string)$template['row_source'] === 'line_item'
            ? 'line_item'
            : 'reservation';

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
                    if (!isset($metricOptions[$metricCode])) {
                        $meta = array('data_type' => 'text', 'numeric' => false);
                        $rawValue = '';
                        $error = 'Metrica line item invalida o no guardada: ' . ($metricCode !== '' ? $metricCode : '(vacia)');
                        $baseCells[$fieldId] = array(
                            'raw' => $rawValue,
                            'display' => $error,
                            'meta' => $meta,
                            'error' => $error,
                        );
                        continue;
                    }
                    $metricMeta = $metricOptions[$metricCode];
                    $allowMultipleCatalogs = !empty($field['allow_multiple_catalogs']);
                    $catalogIds = reports_v2_field_catalog_ids($field);
                    if ($rowSource === 'line_item') {
                        $currentCatalogId = isset($baseRow['base_line_item_catalog_id']) ? (int)$baseRow['base_line_item_catalog_id'] : 0;
                        $currentLineItemId = isset($baseRow['base_line_item_id']) ? (int)$baseRow['base_line_item_id'] : 0;
                        $currentMatchesField = empty($catalogIds) || in_array($currentCatalogId, $catalogIds, true);
                        if ($currentMatchesField) {
                            $cell = reports_v2_build_current_line_item_cell($field, $metricMeta, $baseRow, $currency, $formatHint);
                            $meta = $cell['meta'];
                            $rawValue = $cell['raw'];
                        } else {
                            $treeMetrics = ($currentLineItemId > 0 && isset($lineItemTreeMetrics[$currentLineItemId]) && is_array($lineItemTreeMetrics[$currentLineItemId]))
                                ? $lineItemTreeMetrics[$currentLineItemId]
                                : array();
                            if ($allowMultipleCatalogs && count($catalogIds) > 1) {
                                $variantCells = reports_v2_line_item_variant_cells(
                                    $field,
                                    $metricMeta,
                                    $treeMetrics,
                                    $currency,
                                    $formatHint
                                );
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
                            $cell = reports_v2_build_line_item_cell(
                                $field,
                                $metricMeta,
                                $treeMetrics,
                                $catalogId,
                                $currency,
                                $formatHint
                            );
                            $meta = $cell['meta'];
                            $rawValue = $cell['raw'];
                            if ($catalogId <= 0 && !empty($metricMeta['numeric'])) {
                                $rawValue = 0;
                            }
                        }
                    } elseif ($allowMultipleCatalogs && count($catalogIds) > 1) {
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
                    if ($rowSource !== 'line_item') {
                        $catalogId = !empty($catalogIds) ? (int)$catalogIds[0] : 0;
                        $cell = reports_v2_build_line_item_cell($field, $metricMeta, $lineMetrics, $catalogId, $currency, $formatHint);
                        $meta = $cell['meta'];
                        $rawValue = $cell['raw'];
                        if ($catalogId <= 0 && !empty($metricMeta['numeric'])) {
                            $rawValue = 0;
                        }
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

                if ($fieldType === 'reservation' && $error === '') {
                    $cellPayload = reports_v2_reservation_field_cell_payload($rawValue, $meta, $currency, $formatHint);
                    $baseCells[$fieldId] = array(
                        'raw' => $cellPayload['raw'],
                        'display' => $cellPayload['display'],
                        'meta' => $meta,
                        'error' => '',
                        'href' => $cellPayload['href'],
                        'entity_type' => $cellPayload['entity_type'],
                    );
                } else {
                    $baseCells[$fieldId] = array(
                        'raw' => $rawValue,
                        'display' => $error !== ''
                            ? $error
                            : pms_report_format_value($rawValue, $meta, $currency, $formatHint),
                        'meta' => $meta,
                        'error' => $error,
                        'href' => '',
                        'entity_type' => '',
                    );
                }

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

if (!function_exists('reports_v2_build_combined_report_rows')) {
    function reports_v2_build_combined_report_rows(array $reservationBaseRows, array $lineItemBaseRows, array $fields, array $calculationsById, array $lineItemMetrics, array $variableCatalog, array $template = array(), array $lineItemTreeMetrics = array())
    {
        $reservationTemplate = $template;
        $reservationTemplate['row_source'] = 'reservation';
        $lineItemTemplate = $template;
        $lineItemTemplate['row_source'] = 'line_item';

        list($reservationRows, $reservationTotals) = reports_v2_build_report_rows(
            $reservationBaseRows,
            $fields,
            $calculationsById,
            $lineItemMetrics,
            $variableCatalog,
            $reservationTemplate,
            array()
        );
        list($lineItemRows, $lineItemTotals) = reports_v2_build_report_rows(
            $lineItemBaseRows,
            $fields,
            $calculationsById,
            $lineItemMetrics,
            $variableCatalog,
            $lineItemTemplate,
            $lineItemTreeMetrics
        );

        $blankCell = function (array $cell) {
            $cell['raw'] = '';
            $cell['display'] = '';
            $cell['error'] = '';
            $cell['href'] = '';
            return $cell;
        };

        foreach ($reservationRows as $rowIndex => $row) {
            foreach ($fields as $field) {
                $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
                if ($fieldId <= 0 || !isset($reservationRows[$rowIndex]['cells'][$fieldId])) {
                    continue;
                }
                if ((isset($field['field_type']) ? (string)$field['field_type'] : '') === 'line_item') {
                    $reservationRows[$rowIndex]['cells'][$fieldId] = $blankCell($reservationRows[$rowIndex]['cells'][$fieldId]);
                }
            }
            $reservationRows[$rowIndex]['row_kind'] = 'combined_reservation';
        }

        foreach ($lineItemRows as $rowIndex => $row) {
            $lineItemRows[$rowIndex]['row_kind'] = 'combined_line_item';
        }

        $lineItemsByReservation = array();
        foreach ($lineItemRows as $lineItemRow) {
            $reservationId = isset($lineItemRow['reservation_id']) ? (int)$lineItemRow['reservation_id'] : 0;
            if ($reservationId <= 0) {
                continue;
            }
            if (!isset($lineItemsByReservation[$reservationId])) {
                $lineItemsByReservation[$reservationId] = array();
            }
            $lineItemsByReservation[$reservationId][] = $lineItemRow;
        }

        $combinedRows = array();
        foreach ($reservationRows as $reservationRow) {
            $reservationId = isset($reservationRow['reservation_id']) ? (int)$reservationRow['reservation_id'] : 0;
            $combinedRows[] = $reservationRow;
            if ($reservationId > 0 && isset($lineItemsByReservation[$reservationId])) {
                foreach ($lineItemsByReservation[$reservationId] as $lineItemRow) {
                    $combinedRows[] = $lineItemRow;
                }
            }
        }

        $fieldTypeById = array();
        foreach ($fields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            if ($fieldId <= 0) {
                continue;
            }
            $fieldTypeById[$fieldId] = isset($field['field_type']) ? (string)$field['field_type'] : '';
        }

        $totals = array();
        foreach ($fieldTypeById as $fieldId => $fieldType) {
            if ($fieldType === 'line_item') {
                $totals[$fieldId] = isset($lineItemTotals[$fieldId]) ? (float)$lineItemTotals[$fieldId] : 0.0;
            } else {
                $totals[$fieldId] = isset($reservationTotals[$fieldId]) ? (float)$reservationTotals[$fieldId] : 0.0;
            }
        }

        return array($combinedRows, $totals);
    }
}

if (!function_exists('reports_v2_find_total_label_field_id')) {
    function reports_v2_find_total_label_field_id(array $fields)
    {
        foreach ($fields as $field) {
            if (!reports_v2_field_calculates_total($field)) {
                return isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            }
        }
        return !empty($fields) && isset($fields[0]['id_report_template_field'])
            ? (int)$fields[0]['id_report_template_field']
            : 0;
    }
}

if (!function_exists('reports_v2_compute_display_totals')) {
    function reports_v2_compute_display_totals(array $rows, array $fields, $label = 'Totales')
    {
        $currency = !empty($rows) && !empty($rows[0]['currency'])
            ? (string)$rows[0]['currency']
            : 'MXN';
        $labelFieldId = reports_v2_find_total_label_field_id($fields);
        $totals = array();

        foreach ($fields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $calculateTotal = reports_v2_field_calculates_total($field);
            $sampleMeta = array('data_type' => 'text', 'numeric' => false);
            foreach ($rows as $row) {
                if (!empty($row['cells'][$fieldId]['meta']) && is_array($row['cells'][$fieldId]['meta'])) {
                    $sampleMeta = $row['cells'][$fieldId]['meta'];
                    break;
                }
            }

            $cell = array(
                'raw' => '',
                'display' => ($fieldId === $labelFieldId ? (string)$label : ''),
                'meta' => $sampleMeta,
                'error' => '',
            );

            if ($calculateTotal && !empty($sampleMeta['numeric'])) {
                $sum = 0.0;
                foreach ($rows as $row) {
                    if (!isset($row['cells'][$fieldId])) {
                        continue;
                    }
                    $rowKind = isset($row['row_kind']) ? (string)$row['row_kind'] : '';
                    $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
                    if ($rowKind === 'combined_reservation' && $fieldType === 'line_item') {
                        continue;
                    }
                    if ($rowKind === 'combined_line_item' && $fieldType !== 'line_item') {
                        continue;
                    }
                    $rawValue = isset($row['cells'][$fieldId]['raw']) ? $row['cells'][$fieldId]['raw'] : null;
                    if (is_numeric($rawValue)) {
                        $sum += (float)$rawValue;
                    }
                }
                $formatHint = isset($field['format_hint']) ? (string)$field['format_hint'] : 'auto';
                $cell['raw'] = $sum;
                $cell['display'] = pms_report_format_value($sum, $sampleMeta, $currency, $formatHint);
            }

            $totals[$fieldId] = $cell;
        }

        return $totals;
    }
}

if (!function_exists('reports_v2_line_item_visualization_options')) {
    function reports_v2_line_item_visualization_options()
    {
        return array(
            'standard' => 'Tabla estandar',
            'folio' => 'Agrupar por folio',
        );
    }
}

if (!function_exists('reports_v2_line_item_folio_visualization_applicable')) {
    function reports_v2_line_item_folio_visualization_applicable(array $template)
    {
        $rowSource = isset($template['row_source']) ? (string)$template['row_source'] : '';
        $lineItemTypeScope = isset($template['line_item_type_scope']) ? (string)$template['line_item_type_scope'] : '';
        return $rowSource === 'line_item' && $lineItemTypeScope === 'all';
    }
}

if (!function_exists('reports_v2_prepare_line_item_rows_for_folio_visualization')) {
    function reports_v2_prepare_line_item_rows_for_folio_visualization(array $rows, array $fields)
    {
        $amountFieldIds = array();
        foreach ($fields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $fieldType = isset($field['field_type']) ? (string)$field['field_type'] : '';
            $metricCode = isset($field['source_metric']) ? (string)$field['source_metric'] : '';
            if ($fieldId > 0 && $fieldType === 'line_item' && $metricCode === 'amount_cents') {
                $amountFieldIds[] = $fieldId;
            }
        }
        if (empty($amountFieldIds)) {
            return $rows;
        }

        foreach ($rows as $rowIndex => $row) {
            $base = isset($row['base']) && is_array($row['base']) ? $row['base'] : array();
            $itemType = isset($base['base_line_item_type']) ? trim((string)$base['base_line_item_type']) : '';
            if ($itemType !== 'payment') {
                continue;
            }
            $currency = isset($row['currency']) ? (string)$row['currency'] : 'MXN';
            foreach ($amountFieldIds as $fieldId) {
                if (!isset($rows[$rowIndex]['cells'][$fieldId]) || !is_array($rows[$rowIndex]['cells'][$fieldId])) {
                    continue;
                }
                $cell = $rows[$rowIndex]['cells'][$fieldId];
                $rawValue = isset($cell['raw']) && is_numeric($cell['raw']) ? (float)$cell['raw'] : null;
                if ($rawValue === null) {
                    continue;
                }
                $negativeValue = -1 * abs($rawValue);
                $meta = isset($cell['meta']) && is_array($cell['meta']) ? $cell['meta'] : array('data_type' => 'currency', 'numeric' => true);
                $rows[$rowIndex]['cells'][$fieldId]['raw'] = $negativeValue;
                $rows[$rowIndex]['cells'][$fieldId]['display'] = pms_report_format_value($negativeValue, $meta, $currency, 'currency');
            }
        }

        return $rows;
    }
}

if (!function_exists('reports_v2_build_line_item_folio_visualization')) {
    function reports_v2_build_line_item_folio_visualization(array $rows, array $fields)
    {
        if (empty($rows)) {
            return array();
        }

        $typeLabels = reports_v2_line_item_row_type_options();
        $typeOrder = array(
            'sale_item' => 10,
            'tax_item' => 20,
            'obligation' => 30,
            'income' => 40,
            'payment' => 90,
        );
        $currencyMeta = array('data_type' => 'currency', 'numeric' => true);
        $folios = array();
        $folioOrder = array();

        foreach ($rows as $position => $row) {
            $base = isset($row['base']) && is_array($row['base']) ? $row['base'] : array();
            $folioId = isset($base['base_line_item_folio_id']) ? (int)$base['base_line_item_folio_id'] : 0;
            $folioName = trim((string)(isset($base['base_line_item_folio_name']) ? $base['base_line_item_folio_name'] : ''));
            $folioStatus = trim((string)(isset($base['base_line_item_folio_status']) ? $base['base_line_item_folio_status'] : ''));
            $currency = isset($row['currency']) ? (string)$row['currency'] : 'MXN';
            $folioKey = $folioId > 0 ? ('folio-' . $folioId) : ('folio-label-' . md5($folioName . '|' . $position));
            if (!isset($folios[$folioKey])) {
                $folioLabel = $folioId > 0
                    ? ('Folio ' . $folioId . ($folioName !== '' ? ' - ' . $folioName : ''))
                    : ($folioName !== '' ? $folioName : 'Folio sin nombre');
                if ($folioStatus !== '') {
                    $folioLabel .= ' (' . $folioStatus . ')';
                }
                $folios[$folioKey] = array(
                    'label' => $folioLabel,
                    'rows' => array(),
                    'children_map' => array(),
                    'charges_cents' => 0.0,
                    'payments_cents' => 0.0,
                    'currency' => $currency,
                    'position' => $position,
                );
                $folioOrder[] = $folioKey;
            }

            $folios[$folioKey]['rows'][] = $row;
            $itemType = trim((string)(isset($base['base_line_item_type']) ? $base['base_line_item_type'] : ''));
            $typeKey = $itemType !== '' ? $itemType : '__empty__';
            $typeLabel = $itemType !== ''
                ? (isset($typeLabels[$itemType]) ? $typeLabels[$itemType] : ucfirst(str_replace('_', ' ', $itemType)))
                : 'Sin tipo';
            if (!isset($folios[$folioKey]['children_map'][$typeKey])) {
                $folios[$folioKey]['children_map'][$typeKey] = array(
                    'level' => 2,
                    'field_id' => 0,
                    'label_kicker' => 'Tipo de line item',
                    'label' => $typeLabel,
                    'rows' => array(),
                    'show_totals' => false,
                    'children' => array(),
                    'sort_order' => isset($typeOrder[$itemType]) ? $typeOrder[$itemType] : 50,
                );
            }
            $folios[$folioKey]['children_map'][$typeKey]['rows'][] = $row;

            $amountCents = isset($base['base_line_item_amount_cents']) && is_numeric($base['base_line_item_amount_cents'])
                ? abs((float)$base['base_line_item_amount_cents'])
                : 0.0;
            if ($itemType === 'payment') {
                $folios[$folioKey]['payments_cents'] += $amountCents;
            } else {
                $folios[$folioKey]['charges_cents'] += $amountCents;
            }
        }

        $subdivisions = array();
        foreach ($folioOrder as $folioKey) {
            $folio = $folios[$folioKey];
            $children = array_values($folio['children_map']);
            usort($children, function ($left, $right) {
                $leftOrder = isset($left['sort_order']) ? (int)$left['sort_order'] : 50;
                $rightOrder = isset($right['sort_order']) ? (int)$right['sort_order'] : 50;
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }
                $leftLabel = isset($left['label']) ? (string)$left['label'] : '';
                $rightLabel = isset($right['label']) ? (string)$right['label'] : '';
                return strnatcasecmp($leftLabel, $rightLabel);
            });
            foreach ($children as $childIndex => $child) {
                unset($children[$childIndex]['sort_order']);
            }

            $balanceCents = (float)$folio['charges_cents'] - (float)$folio['payments_cents'];
            $subdivisions[] = array(
                'label' => $folio['label'],
                'rows' => $folio['rows'],
                'children' => $children,
                'show_totals' => true,
                'totals' => reports_v2_compute_display_totals($folio['rows'], $fields, 'Total folio'),
                'footer_summary' => array(
                    'charges_display' => pms_report_format_value($folio['charges_cents'], $currencyMeta, $folio['currency'], 'currency'),
                    'payments_display' => pms_report_format_value($folio['payments_cents'], $currencyMeta, $folio['currency'], 'currency'),
                    'balance_display' => pms_report_format_value($balanceCents, $currencyMeta, $folio['currency'], 'currency'),
                ),
            );
        }

        return $subdivisions;
    }
}

if (!function_exists('reports_v2_group_report_rows')) {
    function reports_v2_group_report_rows(array $rows, $fieldId)
    {
        $fieldId = (int)$fieldId;
        if ($fieldId <= 0) {
            return array();
        }

        $groups = array();
        $order = array();
        foreach ($rows as $row) {
            $cell = isset($row['cells'][$fieldId]) ? $row['cells'][$fieldId] : array();
            $label = isset($cell['display']) ? trim((string)$cell['display']) : '';
            if ($label === '') {
                $label = 'Sin valor';
            }
            if (!isset($groups[$label])) {
                $groups[$label] = array();
                $order[] = $label;
            }
            $groups[$label][] = $row;
        }

        $result = array();
        foreach ($order as $label) {
            $result[] = array(
                'label' => $label,
                'rows' => $groups[$label],
            );
        }
        return $result;
    }
}

if (!function_exists('reports_v2_export_headers')) {
    function reports_v2_export_headers(array $fields)
    {
        $headers = array();
        foreach ($fields as $field) {
            $headers[] = isset($field['display_name_resolved']) && trim((string)$field['display_name_resolved']) !== ''
                ? (string)$field['display_name_resolved']
                : (isset($field['display_name']) ? (string)$field['display_name'] : '');
        }
        return $headers;
    }
}

if (!function_exists('reports_v2_export_row_values')) {
    function reports_v2_export_row_values(array $row, array $fields)
    {
        $values = array();
        foreach ($fields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $cell = ($fieldId > 0 && isset($row['cells'][$fieldId]) && is_array($row['cells'][$fieldId]))
                ? $row['cells'][$fieldId]
                : array();
            $values[] = isset($cell['display']) ? (string)$cell['display'] : '';
        }
        return $values;
    }
}

if (!function_exists('reports_v2_export_total_values')) {
    function reports_v2_export_total_values(array $totals, array $fields)
    {
        $values = array();
        foreach ($fields as $field) {
            $fieldId = isset($field['id_report_template_field']) ? (int)$field['id_report_template_field'] : 0;
            $cell = ($fieldId > 0 && isset($totals[$fieldId]) && is_array($totals[$fieldId]))
                ? $totals[$fieldId]
                : array();
            $values[] = isset($cell['display']) ? (string)$cell['display'] : '';
        }
        return $values;
    }
}

if (!function_exists('reports_v2_export_subdivision_tree_rows')) {
    function reports_v2_export_subdivision_tree_rows(array $nodes, array $fields)
    {
        $rows = array();
        $columnCount = count($fields);
        foreach ($nodes as $node) {
            $markerRow = array_fill(0, $columnCount, '');
            if ($columnCount > 0) {
                $markerRow[0] = isset($node['label']) ? (string)$node['label'] : 'Sin valor';
            }
            $rows[] = $markerRow;

            if (!empty($node['children']) && is_array($node['children'])) {
                $rows = array_merge($rows, reports_v2_export_subdivision_tree_rows($node['children'], $fields));
            } else {
                foreach ((isset($node['rows']) && is_array($node['rows'])) ? $node['rows'] : array() as $reportRow) {
                    $rows[] = reports_v2_export_row_values($reportRow, $fields);
                }
            }

            if (!empty($node['show_totals'])) {
                $nodeTotals = reports_v2_compute_display_totals(
                    (isset($node['rows']) && is_array($node['rows'])) ? $node['rows'] : array(),
                    $fields,
                    'Subtotal'
                );
                $rows[] = reports_v2_export_total_values($nodeTotals, $fields);
            }

            $rows[] = array_fill(0, $columnCount, '');
        }

        return $rows;
    }
}

if (!function_exists('reports_v2_build_export_rows')) {
    function reports_v2_build_export_rows(array $fields, array $reportRows, array $reportSubdivisions = array(), array $reportDisplayTotals = array(), $includeTotals = true)
    {
        $rows = array();
        $columnCount = count($fields);

        if (!empty($reportSubdivisions)) {
            foreach ($reportSubdivisions as $subdivision) {
                $titleRow = array_fill(0, $columnCount, '');
                if ($columnCount > 0) {
                    $titleRow[0] = isset($subdivision['label']) ? (string)$subdivision['label'] : 'Subdivision';
                }
                $rows[] = $titleRow;

                if (!empty($subdivision['children']) && is_array($subdivision['children'])) {
                    $rows = array_merge($rows, reports_v2_export_subdivision_tree_rows($subdivision['children'], $fields));
                } else {
                    foreach ((isset($subdivision['rows']) && is_array($subdivision['rows'])) ? $subdivision['rows'] : array() as $reportRow) {
                        $rows[] = reports_v2_export_row_values($reportRow, $fields);
                    }
                }

                if (!empty($subdivision['show_totals']) && !empty($subdivision['totals'])) {
                    $rows[] = reports_v2_export_total_values($subdivision['totals'], $fields);
                }

                $rows[] = array_fill(0, $columnCount, '');
            }
        } else {
            foreach ($reportRows as $reportRow) {
                $rows[] = reports_v2_export_row_values($reportRow, $fields);
            }
        }

        if ($includeTotals && !empty($reportDisplayTotals)) {
            $rows[] = reports_v2_export_total_values($reportDisplayTotals, $fields);
        }

        return $rows;
    }
}

if (!function_exists('reports_v2_send_csv_download')) {
    function reports_v2_send_csv_download($filename, array $headers, array $rows)
    {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$filename) . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('No fue posible iniciar la descarga CSV.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }
}

if (!function_exists('reports_v2_send_excel_html_download')) {
    function reports_v2_send_excel_html_download($filename, array $headers, array $rows, $sheetTitle = 'Reporte')
    {
        if (!headers_sent()) {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$filename) . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        echo "<html><head><meta charset=\"UTF-8\"><title>" . reports_v2_h($sheetTitle) . "</title></head><body>";
        echo '<table border="1">';
        echo '<thead><tr>';
        foreach ($headers as $headerCell) {
            echo '<th>' . reports_v2_h($headerCell) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . reports_v2_h($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
    }
}

if (!function_exists('reports_v2_compare_group_values')) {
    function reports_v2_compare_group_values(array $left, array $right, $direction = 'asc')
    {
        $direction = strtolower(trim((string)$direction)) === 'desc' ? 'desc' : 'asc';
        $leftType = isset($left['sort_type']) ? (string)$left['sort_type'] : 'text';
        $rightType = isset($right['sort_type']) ? (string)$right['sort_type'] : 'text';
        $sortType = $leftType !== 'text' ? $leftType : $rightType;
        $leftRaw = isset($left['sort_value']) ? $left['sort_value'] : '';
        $rightRaw = isset($right['sort_value']) ? $right['sort_value'] : '';
        $comparison = 0;

        if (in_array($sortType, array('currency', 'number', 'integer'), true)) {
            $leftNumber = $leftRaw !== '' && is_numeric($leftRaw) ? (float)$leftRaw : null;
            $rightNumber = $rightRaw !== '' && is_numeric($rightRaw) ? (float)$rightRaw : null;
            if ($leftNumber === null && $rightNumber === null) {
                $comparison = 0;
            } elseif ($leftNumber === null) {
                $comparison = 1;
            } elseif ($rightNumber === null) {
                $comparison = -1;
            } else {
                $comparison = $leftNumber <=> $rightNumber;
            }
        } elseif (in_array($sortType, array('date', 'datetime'), true)) {
            $leftDate = trim((string)$leftRaw);
            $rightDate = trim((string)$rightRaw);
            if ($leftDate === '' && $rightDate === '') {
                $comparison = 0;
            } elseif ($leftDate === '') {
                $comparison = 1;
            } elseif ($rightDate === '') {
                $comparison = -1;
            } else {
                $comparison = strcmp(substr($leftDate, 0, 19), substr($rightDate, 0, 19));
            }
        } else {
            $comparison = strnatcasecmp(
                isset($left['label']) ? (string)$left['label'] : '',
                isset($right['label']) ? (string)$right['label'] : ''
            );
        }

        if ($comparison === 0) {
            $comparison = ((int)(isset($left['position']) ? $left['position'] : 0)) <=> ((int)(isset($right['position']) ? $right['position'] : 0));
        }

        return $direction === 'desc' ? ($comparison * -1) : $comparison;
    }
}

if (!function_exists('reports_v2_build_report_subdivision_tree')) {
    function reports_v2_build_report_subdivision_tree(array $rows, array $fieldIds, array $showTotalsByLevel = array(), array $sortDirectionsByLevel = array(), $level = 1)
    {
        $index = $level - 1;
        $fieldId = isset($fieldIds[$index]) ? (int)$fieldIds[$index] : 0;
        if ($fieldId <= 0) {
            return array();
        }

        $groups = array();
        $position = 0;
        foreach ($rows as $row) {
            $cell = isset($row['cells'][$fieldId]) ? $row['cells'][$fieldId] : array();
            $label = isset($cell['display']) ? trim((string)$cell['display']) : '';
            if ($label === '') {
                $label = 'Sin valor';
            }
            $sortValue = isset($cell['raw']) ? $cell['raw'] : $label;
            $sortType = isset($cell['meta']['data_type']) ? (string)$cell['meta']['data_type'] : 'text';
            if (!isset($groups[$label])) {
                $groups[$label] = array(
                    'label' => $label,
                    'sort_value' => $sortValue,
                    'sort_type' => $sortType,
                    'position' => $position++,
                    'rows' => array(),
                );
            }
            $groups[$label]['rows'][] = $row;
        }

        $direction = isset($sortDirectionsByLevel[$level]) ? (string)$sortDirectionsByLevel[$level] : 'asc';
        $groupEntries = array_values($groups);
        usort($groupEntries, function ($left, $right) use ($direction) {
            return reports_v2_compare_group_values($left, $right, $direction);
        });

        $nodes = array();
        foreach ($groupEntries as $groupEntry) {
            $groupRows = isset($groupEntry['rows']) ? $groupEntry['rows'] : array();
            $node = array(
                'level' => $level,
                'field_id' => $fieldId,
                'label_kicker' => '',
                'label' => isset($groupEntry['label']) ? $groupEntry['label'] : 'Sin valor',
                'sort_direction' => $direction,
                'rows' => $groupRows,
                'show_totals' => !empty($showTotalsByLevel[$level]),
                'children' => array(),
            );
            $childNodes = reports_v2_build_report_subdivision_tree($groupRows, $fieldIds, $showTotalsByLevel, $sortDirectionsByLevel, $level + 1);
            if (!empty($childNodes)) {
                $node['children'] = $childNodes;
            }
            $nodes[] = $node;
        }

        return $nodes;
    }
}

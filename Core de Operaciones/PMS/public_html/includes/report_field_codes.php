<?php

if (!function_exists('pms_report_reservation_field_catalog')) {
    function pms_report_reservation_field_catalog()
    {
        return array(
            'entity_reservation' => array('label' => 'Reservacion', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'entity_guest' => array('label' => 'Huesped', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'entity_property' => array('label' => 'Propiedad', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'entity_room' => array('label' => 'Habitacion', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'entity_category' => array('label' => 'Categoria', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'entity_rateplan' => array('label' => 'Tarifa', 'group' => 'Entidades', 'data_type' => 'text', 'numeric' => false, 'entity' => true),
            'reservation_code' => array('label' => 'Codigo', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_status' => array('label' => 'Estatus', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_source' => array('label' => 'Origen', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_channel_ref' => array('label' => 'Referencia canal', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_check_in_date' => array('label' => 'Check in', 'group' => 'Reservacion', 'data_type' => 'date', 'numeric' => false),
            'reservation_check_out_date' => array('label' => 'Check out', 'group' => 'Reservacion', 'data_type' => 'date', 'numeric' => false),
            'reservation_nights' => array('label' => 'Noches', 'group' => 'Reservacion', 'data_type' => 'integer', 'numeric' => true),
            'reservation_eta' => array('label' => 'ETA', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_etd' => array('label' => 'ETD', 'group' => 'Reservacion', 'data_type' => 'text', 'numeric' => false),
            'reservation_checkin_at' => array('label' => 'Check in real', 'group' => 'Reservacion', 'data_type' => 'datetime', 'numeric' => false),
            'reservation_checkout_at' => array('label' => 'Check out real', 'group' => 'Reservacion', 'data_type' => 'datetime', 'numeric' => false),
            'reservation_adults' => array('label' => 'Adultos', 'group' => 'Reservacion', 'data_type' => 'integer', 'numeric' => true),
            'reservation_children' => array('label' => 'Ninos', 'group' => 'Reservacion', 'data_type' => 'integer', 'numeric' => true),
            'reservation_infants' => array('label' => 'Infantes', 'group' => 'Reservacion', 'data_type' => 'integer', 'numeric' => true),
            'reservation_total_price_cents' => array('label' => 'Total reserva', 'group' => 'Montos', 'data_type' => 'currency', 'numeric' => true),
            'reservation_balance_due_cents' => array('label' => 'Balance pendiente', 'group' => 'Montos', 'data_type' => 'currency', 'numeric' => true),
            'reservation_deposit_due_cents' => array('label' => 'Deposito pendiente', 'group' => 'Montos', 'data_type' => 'currency', 'numeric' => true),
            'reservation_folio_count' => array('label' => 'Cantidad de folios', 'group' => 'Folios', 'data_type' => 'integer', 'numeric' => true),
            'reservation_folio_names' => array('label' => 'Folios', 'group' => 'Folios', 'data_type' => 'text', 'numeric' => false),
            'reservation_folio_statuses' => array('label' => 'Estatus de folios', 'group' => 'Folios', 'data_type' => 'text', 'numeric' => false),
            'reservation_folio_total_cents' => array('label' => 'Total folios', 'group' => 'Folios', 'data_type' => 'currency', 'numeric' => true),
            'reservation_folio_balance_cents' => array('label' => 'Saldo folios', 'group' => 'Folios', 'data_type' => 'currency', 'numeric' => true),
            'reservation_created_at' => array('label' => 'Creada', 'group' => 'Reservacion', 'data_type' => 'datetime', 'numeric' => false),
            'reservation_origin_type' => array('label' => 'Tipo de origen', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_source_catalog_name' => array('label' => 'Origen catalogo', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_source_catalog_code' => array('label' => 'Codigo origen catalogo', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_ota_name' => array('label' => 'OTA', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_ota_platform' => array('label' => 'Plataforma OTA', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_ota_external_code' => array('label' => 'Codigo OTA', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'reservation_ota_contact_email' => array('label' => 'Email OTA', 'group' => 'Origen', 'data_type' => 'text', 'numeric' => false),
            'guest_names' => array('label' => 'Nombre(s)', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_last_name' => array('label' => 'Apellido', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_maiden_name' => array('label' => 'Apellido materno', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_full_name' => array('label' => 'Nombre completo', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_email' => array('label' => 'Email', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_phone' => array('label' => 'Telefono', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'guest_nationality' => array('label' => 'Nacionalidad', 'group' => 'Huesped', 'data_type' => 'text', 'numeric' => false),
            'property_code' => array('label' => 'Codigo propiedad', 'group' => 'Propiedad', 'data_type' => 'text', 'numeric' => false),
            'property_name' => array('label' => 'Propiedad', 'group' => 'Propiedad', 'data_type' => 'text', 'numeric' => false),
            'property_city' => array('label' => 'Ciudad propiedad', 'group' => 'Propiedad', 'data_type' => 'text', 'numeric' => false),
            'property_state' => array('label' => 'Estado propiedad', 'group' => 'Propiedad', 'data_type' => 'text', 'numeric' => false),
            'room_code' => array('label' => 'Codigo habitacion', 'group' => 'Habitacion', 'data_type' => 'text', 'numeric' => false),
            'room_name' => array('label' => 'Habitacion', 'group' => 'Habitacion', 'data_type' => 'text', 'numeric' => false),
            'room_floor' => array('label' => 'Piso', 'group' => 'Habitacion', 'data_type' => 'text', 'numeric' => false),
            'category_code' => array('label' => 'Codigo categoria', 'group' => 'Categoria', 'data_type' => 'text', 'numeric' => false),
            'category_name' => array('label' => 'Categoria', 'group' => 'Categoria', 'data_type' => 'text', 'numeric' => false),
            'category_base_occupancy' => array('label' => 'Ocupacion base', 'group' => 'Categoria', 'data_type' => 'integer', 'numeric' => true),
            'category_max_occupancy' => array('label' => 'Ocupacion maxima', 'group' => 'Categoria', 'data_type' => 'integer', 'numeric' => true),
            'rateplan_code' => array('label' => 'Codigo tarifa', 'group' => 'Tarifa', 'data_type' => 'text', 'numeric' => false),
            'rateplan_name' => array('label' => 'Plan tarifario', 'group' => 'Tarifa', 'data_type' => 'text', 'numeric' => false),
        );
    }
}

if (!function_exists('pms_report_reservation_editable_field_catalog')) {
    function pms_report_reservation_editable_field_catalog()
    {
        return array(
            'reservation_code' => array('label' => 'Codigo', 'entity' => 'reservation', 'column' => 'code', 'input_type' => 'text'),
            'entity_guest' => array('label' => 'Huesped', 'entity' => 'reservation', 'column' => 'id_guest', 'input_type' => 'select', 'options_source' => 'guests', 'allow_empty' => true),
            'entity_property' => array('label' => 'Propiedad', 'entity' => 'reservation', 'column' => 'id_property', 'input_type' => 'select', 'options_source' => 'properties', 'allow_empty' => false),
            'entity_room' => array('label' => 'Habitacion', 'entity' => 'reservation', 'column' => 'id_room', 'input_type' => 'select', 'options_source' => 'rooms', 'allow_empty' => true),
            'entity_category' => array('label' => 'Categoria', 'entity' => 'reservation', 'column' => 'id_category', 'input_type' => 'select', 'options_source' => 'categories', 'allow_empty' => true),
            'entity_rateplan' => array('label' => 'Tarifa', 'entity' => 'reservation', 'column' => 'id_rateplan', 'input_type' => 'select', 'options_source' => 'rateplans', 'allow_empty' => true),
            'reservation_channel_ref' => array('label' => 'Referencia canal', 'entity' => 'reservation', 'column' => 'channel_ref', 'input_type' => 'text'),
            'reservation_eta' => array('label' => 'ETA', 'entity' => 'reservation', 'column' => 'eta', 'input_type' => 'text'),
            'reservation_etd' => array('label' => 'ETD', 'entity' => 'reservation', 'column' => 'etd', 'input_type' => 'text'),
            'reservation_check_in_date' => array('label' => 'Check in', 'entity' => 'reservation_sp', 'column' => 'check_in_date', 'input_type' => 'date'),
            'reservation_check_out_date' => array('label' => 'Check out', 'entity' => 'reservation_sp', 'column' => 'check_out_date', 'input_type' => 'date'),
            'reservation_adults' => array('label' => 'Adultos', 'entity' => 'reservation_sp', 'column' => 'adults', 'input_type' => 'number', 'step' => '1', 'min' => '0'),
            'reservation_children' => array('label' => 'Ninos', 'entity' => 'reservation_sp', 'column' => 'children', 'input_type' => 'number', 'step' => '1', 'min' => '0'),
            'reservation_infants' => array('label' => 'Infantes', 'entity' => 'reservation', 'column' => 'infants', 'input_type' => 'number', 'step' => '1', 'min' => '0'),
            'guest_names' => array('label' => 'Nombre(s)', 'entity' => 'guest', 'column' => 'names', 'input_type' => 'text'),
            'guest_last_name' => array('label' => 'Apellido', 'entity' => 'guest', 'column' => 'last_name', 'input_type' => 'text'),
            'guest_maiden_name' => array('label' => 'Apellido materno', 'entity' => 'guest', 'column' => 'maiden_name', 'input_type' => 'text'),
            'guest_email' => array('label' => 'Email', 'entity' => 'guest', 'column' => 'email', 'input_type' => 'email'),
            'guest_phone' => array('label' => 'Telefono', 'entity' => 'guest', 'column' => 'phone', 'input_type' => 'tel'),
            'guest_nationality' => array('label' => 'Nacionalidad', 'entity' => 'guest', 'column' => 'nationality', 'input_type' => 'text'),
        );
    }
}

if (!function_exists('pms_report_entity_reference_value')) {
    function pms_report_entity_reference_value($id, $label, $href = '', $entityType = '')
    {
        $entityId = (int)$id;
        $entityLabel = trim((string)$label);
        if ($entityId <= 0 && $entityLabel === '') {
            return array(
                'id' => 0,
                'label' => '',
                'href' => '',
                'entity_type' => (string)$entityType,
            );
        }
        return array(
            'id' => $entityId,
            'label' => $entityLabel,
            'href' => trim((string)$href),
            'entity_type' => (string)$entityType,
        );
    }
}

if (!function_exists('pms_report_is_entity_reference_value')) {
    function pms_report_is_entity_reference_value($value)
    {
        return is_array($value) && array_key_exists('label', $value) && array_key_exists('href', $value);
    }
}

if (!function_exists('pms_report_reservation_field_is_inline_editable')) {
    function pms_report_reservation_field_is_inline_editable($fieldCode)
    {
        $catalog = pms_report_reservation_editable_field_catalog();
        $code = trim((string)$fieldCode);
        return isset($catalog[$code]);
    }
}

if (!function_exists('pms_report_reservation_field_value')) {
    function pms_report_reservation_field_value(array $row, $fieldCode)
    {
        switch ((string)$fieldCode) {
            case 'entity_reservation':
                $reservationId = isset($row['id_reservation']) ? (int)$row['id_reservation'] : 0;
                $reservationCode = isset($row['reservation_code']) ? trim((string)$row['reservation_code']) : '';
                return pms_report_entity_reference_value(
                    $reservationId,
                    $reservationCode !== '' ? $reservationCode : ('Reservacion #' . $reservationId),
                    $reservationId > 0 ? ('index.php?view=reservations&open_reservation=' . $reservationId) : '',
                    'reservation'
                );
            case 'entity_guest':
                $guestId = isset($row['id_guest']) ? (int)$row['id_guest'] : 0;
                $guestLabel = isset($row['guest_full_name']) ? trim((string)$row['guest_full_name']) : '';
                if ($guestLabel === '') {
                    $guestLabel = trim(implode(' ', array_filter(array(
                        isset($row['guest_names']) ? trim((string)$row['guest_names']) : '',
                        isset($row['guest_last_name']) ? trim((string)$row['guest_last_name']) : '',
                        isset($row['guest_maiden_name']) ? trim((string)$row['guest_maiden_name']) : '',
                    ))));
                }
                return pms_report_entity_reference_value(
                    $guestId,
                    $guestLabel,
                    $guestId > 0 ? ('index.php?view=guests&guest_id=' . $guestId) : '',
                    'guest'
                );
            case 'entity_property':
                $propertyId = isset($row['property_id']) ? (int)$row['property_id'] : 0;
                $propertyCode = isset($row['property_code']) ? trim((string)$row['property_code']) : '';
                $propertyLabel = isset($row['property_name']) ? trim((string)$row['property_name']) : '';
                return pms_report_entity_reference_value(
                    $propertyId,
                    $propertyLabel !== '' ? $propertyLabel : $propertyCode,
                    $propertyCode !== '' ? ('index.php?view=properties&open_property=' . rawurlencode($propertyCode)) : 'index.php?view=properties',
                    'property'
                );
            case 'entity_room':
                $roomId = isset($row['room_id']) ? (int)$row['room_id'] : 0;
                $roomCode = isset($row['room_code']) ? trim((string)$row['room_code']) : '';
                $roomLabel = isset($row['room_name']) ? trim((string)$row['room_name']) : '';
                $roomPropertyCode = isset($row['property_code']) ? trim((string)$row['property_code']) : '';
                $roomHref = 'index.php?view=rooms';
                if ($roomId > 0) {
                    $roomHref .= '&open_room=' . $roomId;
                }
                if ($roomPropertyCode !== '') {
                    $roomHref .= '&rooms_filter_property=' . rawurlencode($roomPropertyCode);
                }
                return pms_report_entity_reference_value(
                    $roomId,
                    $roomLabel !== '' ? $roomLabel : $roomCode,
                    $roomId > 0 ? $roomHref : 'index.php?view=rooms',
                    'room'
                );
            case 'entity_category':
                $categoryId = isset($row['category_id']) ? (int)$row['category_id'] : 0;
                $categoryCode = isset($row['category_code']) ? trim((string)$row['category_code']) : '';
                $categoryLabel = isset($row['category_name']) ? trim((string)$row['category_name']) : '';
                $categoryPropertyCode = isset($row['property_code']) ? trim((string)$row['property_code']) : '';
                $categoryHref = 'index.php?view=categories';
                if ($categoryCode !== '') {
                    $categoryHref .= '&open_category=' . rawurlencode($categoryCode);
                }
                if ($categoryPropertyCode !== '') {
                    $categoryHref .= '&categories_filter_property=' . rawurlencode($categoryPropertyCode);
                }
                return pms_report_entity_reference_value(
                    $categoryId,
                    $categoryLabel !== '' ? $categoryLabel : $categoryCode,
                    $categoryId > 0 ? $categoryHref : 'index.php?view=categories',
                    'category'
                );
            case 'entity_rateplan':
                $rateplanId = isset($row['rateplan_id']) ? (int)$row['rateplan_id'] : 0;
                $rateplanCode = isset($row['rateplan_code']) ? trim((string)$row['rateplan_code']) : '';
                $rateplanLabel = isset($row['rateplan_name']) ? trim((string)$row['rateplan_name']) : '';
                $rateplanPropertyCode = isset($row['property_code']) ? trim((string)$row['property_code']) : '';
                $rateplanHref = 'index.php?view=rateplans';
                if ($rateplanCode !== '') {
                    $rateplanHref .= '&open_rateplan=' . rawurlencode($rateplanCode);
                }
                if ($rateplanPropertyCode !== '') {
                    $rateplanHref .= '&rateplans_filter_property=' . rawurlencode($rateplanPropertyCode);
                }
                return pms_report_entity_reference_value(
                    $rateplanId,
                    $rateplanLabel !== '' ? $rateplanLabel : $rateplanCode,
                    $rateplanId > 0 ? $rateplanHref : 'index.php?view=rateplans',
                    'rateplan'
                );
            case 'reservation_code':
                return isset($row['reservation_code']) ? (string)$row['reservation_code'] : '';
            case 'reservation_status':
                return isset($row['reservation_status']) ? (string)$row['reservation_status'] : '';
            case 'reservation_source':
                return isset($row['reservation_source']) ? (string)$row['reservation_source'] : '';
            case 'reservation_channel_ref':
                return isset($row['reservation_channel_ref']) ? (string)$row['reservation_channel_ref'] : '';
            case 'reservation_check_in_date':
                return isset($row['reservation_check_in_date']) ? (string)$row['reservation_check_in_date'] : '';
            case 'reservation_check_out_date':
                return isset($row['reservation_check_out_date']) ? (string)$row['reservation_check_out_date'] : '';
            case 'reservation_nights':
                return isset($row['reservation_nights']) ? (int)$row['reservation_nights'] : 0;
            case 'reservation_eta':
                return isset($row['reservation_eta']) ? (string)$row['reservation_eta'] : '';
            case 'reservation_etd':
                return isset($row['reservation_etd']) ? (string)$row['reservation_etd'] : '';
            case 'reservation_checkin_at':
                return isset($row['reservation_checkin_at']) ? (string)$row['reservation_checkin_at'] : '';
            case 'reservation_checkout_at':
                return isset($row['reservation_checkout_at']) ? (string)$row['reservation_checkout_at'] : '';
            case 'reservation_adults':
                return isset($row['reservation_adults']) ? (int)$row['reservation_adults'] : 0;
            case 'reservation_children':
                return isset($row['reservation_children']) ? (int)$row['reservation_children'] : 0;
            case 'reservation_infants':
                return isset($row['reservation_infants']) ? (int)$row['reservation_infants'] : 0;
            case 'reservation_total_price_cents':
                return isset($row['reservation_total_price_cents']) ? (int)$row['reservation_total_price_cents'] : 0;
            case 'reservation_balance_due_cents':
                return isset($row['reservation_balance_due_cents']) ? (int)$row['reservation_balance_due_cents'] : 0;
            case 'reservation_deposit_due_cents':
                return isset($row['reservation_deposit_due_cents']) ? (int)$row['reservation_deposit_due_cents'] : 0;
            case 'reservation_folio_count':
                return isset($row['reservation_folio_count']) ? (int)$row['reservation_folio_count'] : 0;
            case 'reservation_folio_names':
                return isset($row['reservation_folio_names']) ? (string)$row['reservation_folio_names'] : '';
            case 'reservation_folio_statuses':
                return isset($row['reservation_folio_statuses']) ? (string)$row['reservation_folio_statuses'] : '';
            case 'reservation_folio_total_cents':
                return isset($row['reservation_folio_total_cents']) ? (int)$row['reservation_folio_total_cents'] : 0;
            case 'reservation_folio_balance_cents':
                return isset($row['reservation_folio_balance_cents']) ? (int)$row['reservation_folio_balance_cents'] : 0;
            case 'reservation_created_at':
                return isset($row['reservation_created_at']) ? (string)$row['reservation_created_at'] : '';
            case 'reservation_origin_type':
                return isset($row['reservation_origin_type']) ? (string)$row['reservation_origin_type'] : '';
            case 'reservation_source_catalog_name':
                return isset($row['reservation_source_catalog_name']) ? (string)$row['reservation_source_catalog_name'] : '';
            case 'reservation_source_catalog_code':
                return isset($row['reservation_source_catalog_code']) ? (string)$row['reservation_source_catalog_code'] : '';
            case 'reservation_ota_name':
                return isset($row['reservation_ota_name']) ? (string)$row['reservation_ota_name'] : '';
            case 'reservation_ota_platform':
                return isset($row['reservation_ota_platform']) ? (string)$row['reservation_ota_platform'] : '';
            case 'reservation_ota_external_code':
                return isset($row['reservation_ota_external_code']) ? (string)$row['reservation_ota_external_code'] : '';
            case 'reservation_ota_contact_email':
                return isset($row['reservation_ota_contact_email']) ? (string)$row['reservation_ota_contact_email'] : '';
            case 'guest_names':
                return isset($row['guest_names']) ? (string)$row['guest_names'] : '';
            case 'guest_last_name':
                return isset($row['guest_last_name']) ? (string)$row['guest_last_name'] : '';
            case 'guest_maiden_name':
                return isset($row['guest_maiden_name']) ? (string)$row['guest_maiden_name'] : '';
            case 'guest_full_name':
                return isset($row['guest_full_name']) ? (string)$row['guest_full_name'] : '';
            case 'guest_email':
                return isset($row['guest_email']) ? (string)$row['guest_email'] : '';
            case 'guest_phone':
                return isset($row['guest_phone']) ? (string)$row['guest_phone'] : '';
            case 'guest_nationality':
                return isset($row['guest_nationality']) ? (string)$row['guest_nationality'] : '';
            case 'property_code':
                return isset($row['property_code']) ? (string)$row['property_code'] : '';
            case 'property_name':
                return isset($row['property_name']) ? (string)$row['property_name'] : '';
            case 'property_city':
                return isset($row['property_city']) ? (string)$row['property_city'] : '';
            case 'property_state':
                return isset($row['property_state']) ? (string)$row['property_state'] : '';
            case 'room_code':
                return isset($row['room_code']) ? (string)$row['room_code'] : '';
            case 'room_name':
                return isset($row['room_name']) ? (string)$row['room_name'] : '';
            case 'room_floor':
                return isset($row['room_floor']) ? (string)$row['room_floor'] : '';
            case 'category_code':
                return isset($row['category_code']) ? (string)$row['category_code'] : '';
            case 'category_name':
                return isset($row['category_name']) ? (string)$row['category_name'] : '';
            case 'category_base_occupancy':
                return isset($row['category_base_occupancy']) ? (int)$row['category_base_occupancy'] : 0;
            case 'category_max_occupancy':
                return isset($row['category_max_occupancy']) ? (int)$row['category_max_occupancy'] : 0;
            case 'rateplan_code':
                return isset($row['rateplan_code']) ? (string)$row['rateplan_code'] : '';
            case 'rateplan_name':
                return isset($row['rateplan_name']) ? (string)$row['rateplan_name'] : '';
        }

        return null;
    }
}

if (!function_exists('pms_report_slugify')) {
    function pms_report_slugify($value)
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim((string)$value, '_');
        return $value !== '' ? $value : 'reporte';
    }
}

if (!function_exists('pms_report_format_money')) {
    function pms_report_format_money($cents, $currency)
    {
        return '$' . number_format(((int)$cents) / 100, 2) . ' ' . ($currency !== '' ? $currency : 'MXN');
    }
}

if (!function_exists('pms_report_format_value')) {
    function pms_report_format_value($value, array $meta, $currency = 'MXN', $formatHint = 'auto')
    {
        $type = $formatHint !== 'auto' ? $formatHint : (isset($meta['data_type']) ? $meta['data_type'] : 'text');
        if ($value === null) {
            return '';
        }
        if (pms_report_is_entity_reference_value($value)) {
            return isset($value['label']) ? (string)$value['label'] : '';
        }
        switch ($type) {
            case 'currency':
                return pms_report_format_money((int)$value, $currency);
            case 'integer':
                return (string)(int)$value;
            case 'number':
                return number_format((float)$value, 2, '.', ',');
            case 'date':
                if ((string)$value === '') {
                    return '';
                }
                $ts = strtotime((string)$value);
                return $ts ? date('d/m/Y', $ts) : (string)$value;
            case 'datetime':
                if ((string)$value === '') {
                    return '';
                }
                $ts = strtotime((string)$value);
                return $ts ? date('d/m/Y H:i', $ts) : (string)$value;
            default:
                return (string)$value;
        }
    }
}

if (!function_exists('pms_report_extract_expression_variables')) {
    function pms_report_extract_expression_variables($expression)
    {
        $matches = array();
        preg_match_all('/\\b[A-Za-z_][A-Za-z0-9_]*\\b/', (string)$expression, $matches);
        return array_values(array_unique(isset($matches[0]) ? $matches[0] : array()));
    }
}

if (!function_exists('pms_report_safe_eval_expression')) {
    function pms_report_safe_eval_expression($expression, array $variables, &$error = '')
    {
        $error = '';
        $normalized = (string)$expression;
        if (trim($normalized) === '') {
            $error = 'La expresion esta vacia.';
            return null;
        }

        $tokens = pms_report_extract_expression_variables($normalized);
        foreach ($tokens as $token) {
            if (!array_key_exists($token, $variables)) {
                $error = 'Variable desconocida en la expresion: ' . $token;
                return null;
            }
            $replacement = (float)$variables[$token];
            $normalized = preg_replace('/\\b' . preg_quote($token, '/') . '\\b/', (string)$replacement, $normalized);
        }

        if (!preg_match('/^[0-9\\s\\+\\-\\*\\/\\(\\)\\.%]+$/', $normalized)) {
            $error = 'La expresion contiene caracteres no permitidos.';
            return null;
        }

        try {
            $value = eval('return (' . $normalized . ');');
        } catch (Throwable $e) {
            $error = 'No fue posible evaluar la expresion.';
            return null;
        }

        if (!is_numeric($value) || is_nan((float)$value) || is_infinite((float)$value)) {
            $error = 'La expresion no produjo un numero valido.';
            return null;
        }

        return (float)$value;
    }
}

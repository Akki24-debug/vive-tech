<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
list($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath) = ai_tunnel_boot_shared('reservations');
$daysBack = isset($config['reservation_window_days_back']) ? max(0, (int)$config['reservation_window_days_back']) : 30;
$daysForward = isset($config['reservation_window_days_forward']) ? max(1, (int)$config['reservation_window_days_forward']) : 30;
$range = ai_tunnel_resolve_range_shared(
    $filters['date_from'],
    $filters['date_to'],
    date('Y-m-d', strtotime('-' . $daysBack . ' day')),
    date('Y-m-d', strtotime('+' . $daysForward . ' day'))
);
$activeFilters = ai_tunnel_active_filters_summary_shared($filters);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Tunnel - Reservaciones detalle</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>Reservaciones, folios, pagos y line items</h1>
      <p class="muted">Ventana efectiva: <?php echo ai_tunnel_h_shared($range[0]); ?> a <?php echo ai_tunnel_h_shared($range[1]); ?></p>
      <div>
        <span class="pill">Empresa: <?php echo ai_tunnel_h_shared(isset($propertyRows[0]['company_name']) ? $propertyRows[0]['company_name'] : $companyCode); ?></span>
        <?php foreach ($activeFilters as $item): ?><span class="pill"><?php echo ai_tunnel_h_shared($item); ?></span><?php endforeach; ?>
        <?php if (!$activeFilters): ?><span class="pill">Sin filtros activos</span><?php endif; ?>
      </div>
    </section>
    <?php if (!$propertyRows): ?>
      <section class="card"><p class="muted">No se encontraron propiedades con esos filtros.</p></section>
    <?php endif; ?>
    <?php foreach ($propertyRows as $propertyRow): ?>
      <?php $data = ai_tunnel_collect_property_reservations_shared($pdo, $companyCode, $propertyRow, $filters, $range); ?>
      <section class="card">
        <h2><?php echo ai_tunnel_h_shared($propertyRow['property_name']); ?></h2>
        <p class="muted">Codigo: <?php echo ai_tunnel_h_shared($propertyRow['property_code']); ?></p>
        <div class="section-grid">
          <div>
            <h3>Reservaciones</h3>
            <?php ai_tunnel_render_table_shared($data['reservations'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped principal'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'source', 'label' => 'Origen'),
                array('key' => 'ota_name', 'label' => 'OTA'),
                array('key' => 'check_in_date', 'label' => 'Check-in'),
                array('key' => 'check_out_date', 'label' => 'Check-out'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'category_code', 'label' => 'Codigo categoria'),
                array('key' => 'category_name', 'label' => 'Categoria'),
                array('key' => 'rateplan_name', 'label' => 'Rateplan'),
                array('key' => 'total_price', 'label' => 'Total'),
                array('key' => 'balance_due', 'label' => 'Balance')
            ), 'Sin reservaciones visibles con esos filtros.'); ?>
          </div>
          <div>
            <h3>Folios</h3>
            <?php ai_tunnel_render_table_shared($data['folios'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'id_folio', 'label' => 'ID folio'),
                array('key' => 'folio_name', 'label' => 'Nombre folio'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'total', 'label' => 'Total'),
                array('key' => 'balance', 'label' => 'Balance')
            ), 'Sin folios visibles.'); ?>
          </div>
          <div>
            <h3>Pagos</h3>
            <?php ai_tunnel_render_table_shared($data['payments'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'id_payment', 'label' => 'ID pago'),
                array('key' => 'id_folio', 'label' => 'ID folio'),
                array('key' => 'folio_name', 'label' => 'Folio'),
                array('key' => 'payment_catalog', 'label' => 'Catalogo pago'),
                array('key' => 'method', 'label' => 'Metodo'),
                array('key' => 'amount', 'label' => 'Monto'),
                array('key' => 'reference', 'label' => 'Referencia'),
                array('key' => 'service_date', 'label' => 'Fecha servicio'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'refunded_total', 'label' => 'Reembolsado')
            ), 'Sin pagos visibles.'); ?>
          </div>
          <div>
            <h3>Line items</h3>
            <?php ai_tunnel_render_table_shared($data['line_items'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'id_sale_item', 'label' => 'ID line item'),
                array('key' => 'id_folio', 'label' => 'ID folio'),
                array('key' => 'folio_name', 'label' => 'Folio'),
                array('key' => 'item_type', 'label' => 'Tipo'),
                array('key' => 'item_name', 'label' => 'Concepto'),
                array('key' => 'service_date', 'label' => 'Fecha servicio'),
                array('key' => 'quantity', 'label' => 'Cantidad'),
                array('key' => 'amount', 'label' => 'Monto'),
                array('key' => 'status', 'label' => 'Estatus')
            ), 'Sin line items visibles.'); ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</body>
</html>

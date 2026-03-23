<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
list($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath) = ai_tunnel_boot_shared('integral');
$daysBack = isset($config['reservation_window_days_back']) ? max(0, (int)$config['reservation_window_days_back']) : 30;
$daysForward = isset($config['reservation_window_days_forward']) ? max(1, (int)$config['reservation_window_days_forward']) : 30;
$reservationRange = ai_tunnel_resolve_range_shared(
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
  <title>AI Tunnel - Solicitar estado actual</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>Solicitar estado actual</h1>
      <p class="muted">Vista integral de respaldo. Usa los mismos filtros compartidos que los endpoints especializados.</p>
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
      <?php
      $categories = ai_tunnel_fetch_property_categories_shared($pdo, $propertyRow, $filters);
      $rooms = ai_tunnel_fetch_property_rooms_shared($pdo, $propertyRow, $filters);
      $activity = ai_tunnel_fetch_property_activity_shared($pdo, $propertyRow, $filters);
      $guests = ai_tunnel_fetch_current_guests_shared($pdo, $propertyRow, $filters);
      $reservations = ai_tunnel_collect_property_reservations_shared($pdo, $companyCode, $propertyRow, $filters, $reservationRange);
      $availability = ai_tunnel_collect_property_availability_shared($pricingService, $propertyRow, $filters);
      ?>
      <section class="card">
        <h2><?php echo ai_tunnel_h_shared($propertyRow['property_name']); ?></h2>
        <p class="muted">Codigo: <?php echo ai_tunnel_h_shared($propertyRow['property_code']); ?> | Reservaciones: <?php echo ai_tunnel_h_shared($reservationRange[0]); ?> a <?php echo ai_tunnel_h_shared($reservationRange[1]); ?> | Disponibilidad: <?php echo ai_tunnel_h_shared($availability['date_from']); ?> a <?php echo ai_tunnel_h_shared($availability['date_to']); ?></p>
        <div class="section-grid">
          <div>
            <h3>Actividad de apoyo</h3>
            <?php ai_tunnel_render_table_shared($activity['in_house'], array(
                array('key' => 'reservation_code', 'label' => 'En casa'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'check_out_date', 'label' => 'Sale')
            ), 'Sin reservaciones en casa al inicio del rango.'); ?>
          </div>
          <div>
            <h3>Categorias</h3>
            <?php ai_tunnel_render_table_shared($categories, array(
                array('key' => 'category_code', 'label' => 'Codigo'),
                array('key' => 'category_name', 'label' => 'Nombre'),
                array('key' => 'max_occupancy', 'label' => 'Max'),
                array('key' => 'default_base_price', 'label' => 'Tarifa base'),
                array('key' => 'min_price', 'label' => 'Precio minimo')
            ), 'Sin categorias visibles.'); ?>
          </div>
          <div>
            <h3>Habitaciones</h3>
            <?php ai_tunnel_render_table_shared($rooms, array(
                array('key' => 'room_code', 'label' => 'Codigo'),
                array('key' => 'room_name', 'label' => 'Nombre'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'capacity_total', 'label' => 'Capacidad'),
                array('key' => 'status', 'label' => 'Estatus')
            ), 'Sin habitaciones visibles.'); ?>
          </div>
          <div>
            <h3>Huespedes hospedados</h3>
            <?php ai_tunnel_render_table_shared($guests['rows'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'check_in_date', 'label' => 'Check-in'),
                array('key' => 'check_out_date', 'label' => 'Check-out')
            ), 'Sin huespedes visibles.'); ?>
          </div>
          <div>
            <h3>Reservaciones</h3>
            <?php ai_tunnel_render_table_shared($reservations['reservations'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'total_price', 'label' => 'Total'),
                array('key' => 'balance_due', 'label' => 'Balance')
            ), 'Sin reservaciones visibles.'); ?>
          </div>
          <div>
            <h3>Folios</h3>
            <?php ai_tunnel_render_table_shared($reservations['folios'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'id_folio', 'label' => 'ID folio'),
                array('key' => 'folio_name', 'label' => 'Folio'),
                array('key' => 'total', 'label' => 'Total'),
                array('key' => 'balance', 'label' => 'Balance')
            ), 'Sin folios visibles.'); ?>
          </div>
          <div>
            <h3>Pagos</h3>
            <?php ai_tunnel_render_table_shared($reservations['payments'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'method', 'label' => 'Metodo'),
                array('key' => 'amount', 'label' => 'Monto'),
                array('key' => 'reference', 'label' => 'Referencia'),
                array('key' => 'service_date', 'label' => 'Fecha servicio')
            ), 'Sin pagos visibles.'); ?>
          </div>
          <div>
            <h3>Line items</h3>
            <?php ai_tunnel_render_table_shared($reservations['line_items'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'item_type', 'label' => 'Tipo'),
                array('key' => 'item_name', 'label' => 'Concepto'),
                array('key' => 'service_date', 'label' => 'Fecha servicio'),
                array('key' => 'quantity', 'label' => 'Cantidad'),
                array('key' => 'amount', 'label' => 'Monto')
            ), 'Sin line items visibles.'); ?>
          </div>
          <div>
            <h3>Disponibilidad iniciando <?php echo ai_tunnel_h_shared($availability['date_from']); ?></h3>
            <?php ai_tunnel_render_table_shared($availability['starts_from'], array(
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'room_name', 'label' => 'Nombre'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'continuous_nights', 'label' => 'Noches continuas'),
                array('key' => 'nightly_prices', 'label' => 'Precios por noche')
            ), 'No hay habitaciones disponibles iniciando en la fecha base.'); ?>
          </div>
          <div>
            <h3>Matriz diaria</h3>
            <?php ai_tunnel_render_table_shared($availability['daily_rows'], $availability['daily_columns'], 'No se pudo construir la matriz diaria.'); ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</body>
</html>

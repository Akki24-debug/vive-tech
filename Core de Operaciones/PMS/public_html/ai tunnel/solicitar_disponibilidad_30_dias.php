<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
list($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath) = ai_tunnel_boot_shared('availability');
$activeFilters = ai_tunnel_active_filters_summary_shared($filters);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Tunnel - Disponibilidad filtrada</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>Disponibilidad y precios por fechas</h1>
      <p class="muted">Este endpoint ya no debe interpretarse como fijo a 30 dias. La ventana la controlan <code>date_from</code> y <code>date_to</code>.</p>
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
      <?php $availability = ai_tunnel_collect_property_availability_shared($pricingService, $propertyRow, $filters); ?>
      <section class="card">
        <h2><?php echo ai_tunnel_h_shared($propertyRow['property_name']); ?></h2>
        <p class="muted">Codigo: <?php echo ai_tunnel_h_shared($propertyRow['property_code']); ?> | Ventana visible: <?php echo ai_tunnel_h_shared($availability['date_from']); ?> a <?php echo ai_tunnel_h_shared($availability['date_to']); ?></p>
        <div class="section-grid">
          <div>
            <h3>Disponibilidad iniciando <?php echo ai_tunnel_h_shared($availability['date_from']); ?></h3>
            <?php ai_tunnel_render_table_shared($availability['starts_from'], array(
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'room_name', 'label' => 'Nombre'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'available_from', 'label' => 'Disponible desde'),
                array('key' => 'continuous_nights', 'label' => 'Noches continuas'),
                array('key' => 'nightly_prices', 'label' => 'Precios por noche')
            ), 'No hay habitaciones disponibles iniciando en la fecha base.'); ?>
          </div>
          <div>
            <h3>Disponibilidad iniciando <?php echo ai_tunnel_h_shared($availability['next_date'] !== '' ? $availability['next_date'] : 'el siguiente dia'); ?></h3>
            <?php ai_tunnel_render_table_shared($availability['starts_next_date'], array(
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'room_name', 'label' => 'Nombre'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'available_from', 'label' => 'Disponible desde'),
                array('key' => 'continuous_nights', 'label' => 'Noches continuas'),
                array('key' => 'nightly_prices', 'label' => 'Precios por noche')
            ), 'No hay habitaciones disponibles iniciando el siguiente dia visible.'); ?>
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

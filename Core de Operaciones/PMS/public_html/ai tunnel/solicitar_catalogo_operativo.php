<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
list($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath) = ai_tunnel_boot_shared('bootstrap');
$activeFilters = ai_tunnel_active_filters_summary_shared($filters);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Tunnel - Catalogo operativo</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>Catalogo operativo</h1>
      <p class="muted">Bootstrap canonico para que la IA descubra nombres, codigos y filtros permitidos antes de pedir datos mas finos.</p>
      <div>
        <span class="pill">Empresa: <?php echo ai_tunnel_h_shared(isset($propertyRows[0]['company_name']) ? $propertyRows[0]['company_name'] : $companyCode); ?></span>
        <span class="pill">Propiedades devueltas: <?php echo count($propertyRows); ?></span>
        <?php foreach ($activeFilters as $item): ?><span class="pill"><?php echo ai_tunnel_h_shared($item); ?></span><?php endforeach; ?>
        <?php if (!$activeFilters): ?><span class="pill">Sin filtros activos</span><?php endif; ?>
      </div>
    </section>

    <section class="card">
      <h2>Contrato de filtros</h2>
      <p class="muted">Todos los enlaces requieren <code>credential</code>. Los codigos son canonicos y el filtro CSV opera como OR dentro del mismo parametro.</p>
      <?php ai_tunnel_render_contract_shared($basePath, $contracts); ?>
    </section>

    <?php if (!$propertyRows): ?>
      <section class="card"><p class="muted">No se encontraron propiedades con esos filtros.</p></section>
    <?php endif; ?>

    <?php foreach ($propertyRows as $propertyRow): ?>
      <?php
      $categories = ai_tunnel_fetch_property_categories_shared($pdo, $propertyRow, $filters);
      $rooms = ai_tunnel_fetch_property_rooms_shared($pdo, $propertyRow, $filters);
      $activity = ai_tunnel_fetch_property_activity_shared($pdo, $propertyRow, $filters);
      $propertyInfo = array(array(
          'id_property' => $propertyRow['id_property'],
          'property_code' => $propertyRow['property_code'],
          'property_name' => $propertyRow['property_name'],
          'currency' => ai_tunnel_text_shared(isset($propertyRow['currency']) ? $propertyRow['currency'] : ''),
          'timezone' => ai_tunnel_text_shared(isset($propertyRow['timezone']) ? $propertyRow['timezone'] : ''),
          'email' => ai_tunnel_text_shared(isset($propertyRow['property_email']) ? $propertyRow['property_email'] : ''),
          'phone' => ai_tunnel_text_shared(isset($propertyRow['property_phone']) ? $propertyRow['property_phone'] : ''),
          'website' => ai_tunnel_text_shared(isset($propertyRow['property_website']) ? $propertyRow['property_website'] : ''),
          'city' => ai_tunnel_text_shared(isset($propertyRow['city']) ? $propertyRow['city'] : ''),
          'state' => ai_tunnel_text_shared(isset($propertyRow['state']) ? $propertyRow['state'] : ''),
          'country' => ai_tunnel_text_shared(isset($propertyRow['country']) ? $propertyRow['country'] : '')
      ));
      ?>
      <section class="card">
        <h2><?php echo ai_tunnel_h_shared($propertyRow['property_name']); ?></h2>
        <p class="muted">Codigo canónico: <?php echo ai_tunnel_h_shared($propertyRow['property_code']); ?></p>
        <div class="section-grid">
          <div>
            <h3>Propiedad</h3>
            <?php ai_tunnel_render_table_shared($propertyInfo, array(
                array('key' => 'id_property', 'label' => 'ID'),
                array('key' => 'property_code', 'label' => 'Codigo'),
                array('key' => 'property_name', 'label' => 'Nombre'),
                array('key' => 'currency', 'label' => 'Moneda'),
                array('key' => 'timezone', 'label' => 'Zona horaria'),
                array('key' => 'email', 'label' => 'Email'),
                array('key' => 'phone', 'label' => 'Telefono'),
                array('key' => 'website', 'label' => 'Website'),
                array('key' => 'city', 'label' => 'Ciudad'),
                array('key' => 'state', 'label' => 'Estado'),
                array('key' => 'country', 'label' => 'Pais')
            ), 'Sin datos de propiedad.'); ?>
          </div>
          <div>
            <h3>Actividad de apoyo</h3>
            <p class="muted">Rango pedido: <?php echo ai_tunnel_h_shared($activity['date_from']); ?> a <?php echo ai_tunnel_h_shared($activity['date_to']); ?></p>
            <?php ai_tunnel_render_table_shared($activity['in_house'], array(
                array('key' => 'reservation_code', 'label' => 'Reservacion'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'category_code', 'label' => 'Categoria'),
                array('key' => 'check_in_date', 'label' => 'Check-in'),
                array('key' => 'check_out_date', 'label' => 'Check-out')
            ), 'Sin reservaciones en casa para el inicio del rango.'); ?>
            <?php ai_tunnel_render_table_shared($activity['arrivals'], array(
                array('key' => 'reservation_code', 'label' => 'Llegadas'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'check_in_date', 'label' => 'Fecha')
            ), 'Sin llegadas visibles dentro del rango.'); ?>
            <?php ai_tunnel_render_table_shared($activity['departures'], array(
                array('key' => 'reservation_code', 'label' => 'Salidas'),
                array('key' => 'guest_name', 'label' => 'Huesped'),
                array('key' => 'room_code', 'label' => 'Habitacion'),
                array('key' => 'check_out_date', 'label' => 'Fecha')
            ), 'Sin salidas visibles dentro del rango.'); ?>
          </div>
          <div>
            <h3>Categorias</h3>
            <?php ai_tunnel_render_table_shared($categories, array(
                array('key' => 'id_category', 'label' => 'ID categoria'),
                array('key' => 'category_code', 'label' => 'Codigo'),
                array('key' => 'category_name', 'label' => 'Nombre'),
                array('key' => 'description', 'label' => 'Descripcion'),
                array('key' => 'base_occupancy', 'label' => 'Base'),
                array('key' => 'max_occupancy', 'label' => 'Max'),
                array('key' => 'default_base_price', 'label' => 'Tarifa base'),
                array('key' => 'min_price', 'label' => 'Precio minimo'),
                array('key' => 'price_floor', 'label' => 'Floor'),
                array('key' => 'price_ceiling', 'label' => 'Ceiling')
            ), 'Sin categorias visibles con esos filtros.'); ?>
          </div>
          <div>
            <h3>Habitaciones</h3>
            <?php ai_tunnel_render_table_shared($rooms, array(
                array('key' => 'id_room', 'label' => 'ID habitacion'),
                array('key' => 'room_code', 'label' => 'Codigo'),
                array('key' => 'room_name', 'label' => 'Nombre'),
                array('key' => 'category_code', 'label' => 'Codigo categoria'),
                array('key' => 'category_name', 'label' => 'Categoria'),
                array('key' => 'capacity_total', 'label' => 'Capacidad'),
                array('key' => 'max_adults', 'label' => 'Max adultos'),
                array('key' => 'max_children', 'label' => 'Max menores'),
                array('key' => 'status', 'label' => 'Estatus'),
                array('key' => 'housekeeping_status', 'label' => 'Housekeeping'),
                array('key' => 'bed_config', 'label' => 'Camas')
            ), 'Sin habitaciones visibles con esos filtros.'); ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</body>
</html>

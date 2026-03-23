<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
list($config, $companyCode, $pdo, $pricingService, $propertyRows, $filters, $contracts, $basePath) = ai_tunnel_boot_shared('guests');
$activeFilters = ai_tunnel_active_filters_summary_shared($filters);
$resolvedDateAt = $filters['date_at'] ? $filters['date_at'] : date('Y-m-d');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Tunnel - Huespedes en casa</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>Huespedes hospedados</h1>
      <p class="muted">Fecha de consulta: <?php echo ai_tunnel_h_shared($resolvedDateAt); ?>. Si no se manda <code>date_at</code>, se usa hoy.</p>
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
      <?php $guestData = ai_tunnel_fetch_current_guests_shared($pdo, $propertyRow, $filters); ?>
      <section class="card">
        <h2><?php echo ai_tunnel_h_shared($propertyRow['property_name']); ?></h2>
        <p class="muted">Codigo: <?php echo ai_tunnel_h_shared($propertyRow['property_code']); ?> | Fecha evaluada: <?php echo ai_tunnel_h_shared($guestData['date_at']); ?></p>
        <?php ai_tunnel_render_table_shared($guestData['rows'], array(
            array('key' => 'reservation_code', 'label' => 'Reservacion'),
            array('key' => 'reservation_status', 'label' => 'Estatus'),
            array('key' => 'id_guest', 'label' => 'ID huesped'),
            array('key' => 'guest_name', 'label' => 'Nombre huesped'),
            array('key' => 'email', 'label' => 'Email'),
            array('key' => 'phone', 'label' => 'Telefono'),
            array('key' => 'room_code', 'label' => 'Habitacion'),
            array('key' => 'room_name', 'label' => 'Nombre habitacion'),
            array('key' => 'category_code', 'label' => 'Codigo categoria'),
            array('key' => 'category_name', 'label' => 'Categoria'),
            array('key' => 'check_in_date', 'label' => 'Check-in'),
            array('key' => 'check_out_date', 'label' => 'Check-out'),
            array('key' => 'adults', 'label' => 'Adultos'),
            array('key' => 'children', 'label' => 'Menores'),
            array('key' => 'infants', 'label' => 'Infantes'),
            array('key' => 'nationality', 'label' => 'Nacionalidad'),
            array('key' => 'country_residence', 'label' => 'Pais residencia'),
            array('key' => 'doc_type', 'label' => 'Tipo documento'),
            array('key' => 'doc_number', 'label' => 'Documento'),
            array('key' => 'language', 'label' => 'Idioma'),
            array('key' => 'notes_guest', 'label' => 'Notas huesped'),
            array('key' => 'notes_internal', 'label' => 'Notas internas')
        ), 'No hay huespedes visibles para esta propiedad con esos filtros.'); ?>
      </section>
    <?php endforeach; ?>
  </div>
</body>
</html>

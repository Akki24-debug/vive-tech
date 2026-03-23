<?php
require_once __DIR__ . '/ai_tunnel_shared.php';
$contracts = ai_tunnel_endpoint_contracts_shared();
$basePath = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/') : '/ai tunnel';
if ($basePath === '') {
    $basePath = '/ai tunnel';
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Tunnel</title>
  <style><?php echo ai_tunnel_base_styles_shared(); ?></style>
</head>
<body>
  <div class="page">
    <section class="card">
      <h1>AI Tunnel</h1>
      <p class="muted">Zona de pruebas de solo lectura para agentes y GPTs. El punto de arranque recomendado es el bootstrap <code>solicitar_catalogo_operativo.php</code>.</p>
      <?php ai_tunnel_render_contract_shared($basePath, $contracts); ?>
      <p class="muted">Documentacion: <a href="AI_TUNNEL.md">AI_TUNNEL.md</a></p>
    </section>
  </div>
</body>
</html>

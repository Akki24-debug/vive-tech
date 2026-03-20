<?php
require __DIR__ . '/includes/db.php';

$currentUser = pms_current_user();
if ($currentUser) {
    header('Location: index.php');
    exit;
}

$error = null;
$errorDetail = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($emailValue === '' || $password === '') {
        $error = 'Ingresa tu correo y contraseña.';
    } else {
        try {
            if (pms_authenticate($emailValue, $password)) {
                header('Location: index.php');
                exit;
            }
            $error = 'Credenciales inválidas.';
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log('[PMS login] ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PMS · Acceso</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .login-card { max-width: 340px; width: 100%; }
    .login-card small { color: #94a3b8; }
  </style>
</head>
<body>
  <section class="card login-card">
    <h1>Iniciar sesión</h1>
    <form method="post" class="form-grid">
      <label>
        Correo electrónico
        <input type="email" name="email" value="<?php echo htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
      </label>
      <label>
        Contraseña
        <input type="password" name="password" required>
      </label>
      <button type="submit">Entrar</button>
    </form>
    <?php if ($error): ?>
      <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($errorDetail): ?>
        <pre class="error" style="white-space:pre-wrap;">Detalles: <?php echo htmlspecialchars($errorDetail, ENT_QUOTES, 'UTF-8'); ?></pre>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</body>
</html>

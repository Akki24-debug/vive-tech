<?php
require __DIR__ . '/includes/db.php';
pms_logout();
header('Location: login.php');
exit;

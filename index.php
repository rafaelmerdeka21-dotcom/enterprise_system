<?php

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user'])) {
    $destination = ($_SESSION['user']['role'] ?? '') === 'admin'
        ? 'pages/dashboard.php'
        : 'pages/sales/index.php';
    header('Location: ' . BASE_URL . '/' . $destination);
} else {
    header('Location: ' . BASE_URL . '/login.php');
}

exit;
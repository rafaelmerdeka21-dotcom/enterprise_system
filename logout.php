<?php

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrfValid()) {
    http_response_code(403);
    exit('Permintaan logout tidak valid. Silakan muat ulang halaman dan coba lagi.');
}

// Hapus seluruh data session.
$_SESSION = [];

// Hapus cookie session dari browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan session.
session_destroy();

// Start a fresh anonymous session so the logout message survives the redirect.
session_id('');
session_start();
setFlashNotification(
    'Anda berhasil logout',
    'Sesi Anda sudah berakhir dengan aman. Sampai jumpa kembali!',
    'info'
);

header('Location: ' . BASE_URL . '/login.php');
exit;
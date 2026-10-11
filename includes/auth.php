
<?php

if (!defined('BASE_URL')) {
    define('BASE_URL', '/enterprise_system');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);

    session_start();
}

function wajibLogin(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function wajibAdmin(): void
{
    wajibLogin();

    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus admin.');
    }
}

function h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function setFlashNotification(string $title, string $message, string $type = 'success'): void
{
    $allowedTypes = ['success', 'info', 'warning', 'error'];

    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Jenis notifikasi tidak valid.');
    }

    $_SESSION['flash_notification'] = [
        'title' => $title,
        'message' => $message,
        'type' => $type,
    ];
}

function takeFlashNotification(): ?array
{
    $notification = $_SESSION['flash_notification'] ?? null;
    unset($_SESSION['flash_notification']);

    if (
        !is_array($notification) ||
        !isset($notification['title'], $notification['message'], $notification['type']) ||
        !in_array($notification['type'], ['success', 'info', 'warning', 'error'], true)
    ) {
        return null;
    }

    return $notification;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfValid(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

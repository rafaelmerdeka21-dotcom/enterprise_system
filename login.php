<?php

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, langsung ke dashboard.
if (!empty($_SESSION['user'])) {
    $destination = ($_SESSION['user']['role'] ?? '') === 'admin'
        ? 'pages/dashboard.php'
        : 'pages/sales/index.php';
    header('Location: ' . BASE_URL . '/' . $destination);
    exit;
}

$error = '';

if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['login_token'], $token)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, name, username, password, role
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (
                $user &&
                password_verify($password, $user['password'])
            ) {
                // Cegah session fixation.
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];

                unset($_SESSION['login_token']);
                setFlashNotification(
                    'Selamat datang, ' . $user['name'] . '!',
                    'Anda berhasil masuk. Semoga aktivitas Anda hari ini berjalan lancar.',
                    'success'
                );

                $destination = $user['role'] === 'admin'
                    ? 'pages/dashboard.php'
                    : 'pages/sales/index.php';
                header('Location: ' . BASE_URL . '/' . $destination);
                exit;
            }

            $error = 'Username atau password salah.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Enterprise System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-white to-teal-50 p-6">
<?php require __DIR__ . '/includes/flash_notification.php'; ?>

<div class="w-full max-w-md overflow-hidden rounded-3xl border border-white bg-white shadow-2xl shadow-slate-900/10">
    <div class="h-2 bg-gradient-to-r from-teal-600 via-cyan-500 to-blue-500"></div>
    <div class="p-8">
    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-cyan-700 text-white shadow-lg shadow-teal-900/20">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
            <path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/><path d="M3 12l9 4.5 9-4.5M3 16.5 12 21l9-4.5"/>
        </svg>
    </div>
    <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-teal-700">Enterprise System</p>

    <h1 class="text-3xl font-bold text-slate-800">
        Selamat Datang
    </h1>

    <p class="text-slate-500 mt-2 mb-6">
        Login untuk mengelola stok dan penjualan.
    </p>

    <?php if ($error !== ''): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="space-y-4">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars(
                   $_SESSION['login_token'],
                   ENT_QUOTES,
                   'UTF-8'
               ) ?>">

        <div>
            <label for="username" class="block mb-1 font-medium">
                Username
            </label>
            <input id="username" name="username" type="text"
                   required autocomplete="username"
                   class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-teal-600">
        </div>

        <div>
            <label for="password" class="block mb-1 font-medium">
                Password
            </label>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password"
                   class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-teal-600">
        </div>

        <button type="submit"
                class="w-full bg-teal-700 text-white rounded-lg p-3 font-semibold hover:bg-teal-800 transition">
            Login
        </button>
    </form>
    </div>
</div>

</body>
</html>
<?php

require_once __DIR__ . '/config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token CSRF untuk form setup.
if (empty($_SESSION['setup_token'])) {
    $_SESSION['setup_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

// Periksa apakah admin sudah ada.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users WHERE role = ?"
);
$stmt->execute(['admin']);

$adminExists = (int) $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['setup_token'], $token)) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $username === '' || $password === '') {
            $error = 'Semua kolom wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
            $error = 'Username harus 4–30 karakter, berupa huruf, angka, atau underscore.';
        } elseif (strlen($password) < 12) {
            $error = 'Password harus minimal 12 karakter.';
        } else {
            try {
                $pdo->beginTransaction();

                // Kunci pemeriksaan agar dua proses setup
                // tidak membuat admin awal secara bersamaan.
                $stmt = $pdo->query(
                    "SELECT id FROM users
                     WHERE role = 'admin'
                     LIMIT 1 FOR UPDATE"
                );

                if ($stmt->fetch()) {
                    $pdo->rollBack();
                    $error = 'Admin sudah dibuat.';
                } else {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                            (name, username, password, role)
                         VALUES (?, ?, ?, 'admin')"
                    );

                    $stmt->execute([
                        $name,
                        $username,
                        $passwordHash
                    ]);

                    $pdo->commit();
                    $success = true;

                    unset($_SESSION['setup_token']);
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                if ($e->getCode() === '23000') {
                    $error = 'Username sudah digunakan.';
                } else {
                    $error = 'Gagal membuat admin. Periksa konfigurasi database.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Admin Awal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">

<div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-slate-800 mb-2">
        Setup Admin
    </h1>

    <p class="text-slate-500 mb-6">
        Buat akun administrator pertama Enterprise System.
    </p>

    <?php if ($adminExists): ?>

        <div class="bg-amber-50 text-amber-800 p-4 rounded-lg">
            Akun admin sudah tersedia. Halaman setup tidak dapat
            digunakan lagi.
        </div>

    <?php elseif ($success): ?>

        <div class="bg-green-50 text-green-800 p-4 rounded-lg">
            Akun admin berhasil dibuat.
            <a href="login.php" class="underline font-semibold">
                Login sekarang
            </a>.
        </div>

    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars(
                       $_SESSION['setup_token'],
                       ENT_QUOTES,
                       'UTF-8'
                   ) ?>">

            <div>
                <label class="block mb-1">Nama lengkap</label>
                <input type="text" name="name" required
                       maxlength="100"
                       class="w-full border rounded-lg p-3">
            </div>

            <div>
                <label class="block mb-1">Username</label>
                <input type="text" name="username" required
                       minlength="4" maxlength="30"
                       autocomplete="username"
                       class="w-full border rounded-lg p-3">
            </div>

            <div>
                <label class="block mb-1">Password</label>
                <input type="password" name="password" required
                       minlength="12"
                       autocomplete="new-password"
                       class="w-full border rounded-lg p-3">
                <p class="text-xs text-slate-500 mt-1">
                    Minimal 12 karakter.
                </p>
            </div>

            <button type="submit"
                    class="w-full bg-teal-700 text-white rounded-lg p-3 font-semibold hover:bg-teal-800">
                Buat Akun Admin
            </button>
        </form>

    <?php endif; ?>
</div>

</body>
</html>
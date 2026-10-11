<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';
                if ($name === '' || strlen($name) > 100) {
                    throw new InvalidArgumentException('Nama wajib diisi (maksimal 100 karakter).');
                }
                if (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                    throw new InvalidArgumentException('Username harus 4–50 karakter: huruf, angka, atau underscore.');
                }
                if (strlen($password) < 12) {
                    throw new InvalidArgumentException('Password harus minimal 12 karakter.');
                }
                if (!in_array($role, ['admin', 'staff'], true)) {
                    throw new InvalidArgumentException('Peran pengguna tidak valid.');
                }
                $stmt = $pdo->prepare('INSERT INTO users (name,username,password,role) VALUES (?,?,?,?)');
                $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                $success = 'Pengguna baru berhasil ditambahkan.';
                $successTitle = 'Pengguna berhasil ditambahkan';
            } elseif ($action === 'role') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                $role = $_POST['role'] ?? '';
                if (!$id || $id < 1 || !in_array($role, ['admin', 'staff'], true)) {
                    throw new InvalidArgumentException('Pengguna atau peran tidak valid.');
                }
                if ($id === (int) $_SESSION['user']['id'] && $role !== 'admin') {
                    throw new InvalidArgumentException('Anda tidak dapat menghapus hak admin dari akun yang sedang digunakan.');
                }
                $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    throw new InvalidArgumentException('Pengguna tidak ditemukan.');
                }
                if ($existing['role'] === 'admin' && $role !== 'admin') {
                    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                    if ($adminCount < 2) {
                        throw new InvalidArgumentException('Sistem harus selalu memiliki minimal satu admin.');
                    }
                }
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$role, $id]);
                $success = 'Peran pengguna berhasil diperbarui.';
                $successTitle = 'Hak akses berhasil diperbarui';
            } elseif ($action === 'password') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                $password = $_POST['password'] ?? '';
                if (!$id || $id < 1 || strlen($password) < 12) {
                    throw new InvalidArgumentException('Password baru harus minimal 12 karakter.');
                }
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                $success = 'Password berhasil diubah.';
                $successTitle = 'Password berhasil diubah';
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = $e->getCode() === '23000'
                ? 'Username tersebut sudah digunakan.'
                : 'Perubahan pengguna gagal disimpan.';
        }
        if ($success !== '') {
            redirectTo('/pages/users/index.php', $success, $successTitle);
        }
    }
}
$users = $pdo->query('SELECT id,name,username,role,created_at FROM users ORDER BY created_at DESC,id DESC')->fetchAll();
$pageTitle = 'Pengguna & Hak Akses';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6"><h1 class="text-2xl font-bold">Pengguna & Hak Akses</h1><p class="mt-1 text-slate-500">Admin mengelola seluruh data; staf dapat mencatat penjualan.</p></div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="mb-6 grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-2">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="create">
    <input name="name" required maxlength="100" placeholder="Nama lengkap" class="rounded-lg border p-3">
    <input name="username" required minlength="4" maxlength="50" placeholder="Username" class="rounded-lg border p-3">
    <input type="password" name="password" required minlength="12" autocomplete="new-password" placeholder="Password (minimal 12 karakter)" class="rounded-lg border p-3">
    <select name="role" class="rounded-lg border p-3"><option value="staff">Staf</option><option value="admin">Admin</option></select>
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white md:col-span-2">Tambah Pengguna</button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Nama</th><th class="p-4">Username</th><th class="p-4">Peran</th><th class="p-4">Dibuat</th><th class="p-4">Ubah Peran</th><th class="p-4">Reset Password</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($users as $user): ?>
            <tr>
                <td class="p-4 font-medium"><?= h($user['name']) ?><?= (int) $user['id'] === (int) $_SESSION['user']['id'] ? ' (Anda)' : '' ?></td>
                <td class="p-4"><?= h($user['username']) ?></td>
                <td class="p-4"><?= h(ucfirst($user['role'])) ?></td>
                <td class="p-4"><?= h($user['created_at']) ?></td>
                <td class="p-4">
                    <form method="post" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                        <select name="role" class="rounded border p-2"><option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staf</option></select>
                        <button class="font-semibold text-teal-700">Simpan</button>
                    </form>
                </td>
                <td class="p-4">
                    <form method="post" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                        <input type="password" name="password" minlength="12" required autocomplete="new-password" placeholder="Password baru" class="w-36 rounded border p-2">
                        <button class="font-semibold text-teal-700">Reset</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
$editing = null;
$editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                $code = trim($_POST['supplier_code'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                if ($code === '' || $name === '' || strlen($code) > 30 || strlen($name) > 150) {
                    throw new InvalidArgumentException('Kode (maks. 30) dan nama pemasok (maks. 150) wajib diisi.');
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Alamat email tidak valid.');
                }
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE suppliers SET supplier_code=?, name=?, phone=?, email=?, address=? WHERE id=?');
                    $stmt->execute([$code, $name, $phone, $email, $address, $id]);
                    $notificationMessage = 'Perubahan pemasok ' . $name . ' berhasil disimpan.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO suppliers (supplier_code, name, phone, email, address) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$code, $name, $phone, $email, $address]);
                    $notificationMessage = 'Pemasok ' . $name . ' berhasil ditambahkan.';
                }
            } elseif ($action === 'delete') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                if (!$id || $id < 1) {
                    throw new InvalidArgumentException('Pemasok tidak valid.');
                }
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM purchases WHERE supplier_id = ?');
                $stmt->execute([$id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Pemasok masih memiliki riwayat pembelian.');
                }
                $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
                $stmt->execute([$id]);
                $notificationMessage = 'Data pemasok berhasil dihapus.';
            }
            redirectTo(
                '/pages/suppliers/index.php',
                $notificationMessage ?? 'Perubahan pemasok berhasil disimpan.'
            );
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = $e->getCode() === '23000'
                ? 'Kode pemasok sudah digunakan.'
                : 'Data pemasok gagal disimpan. Periksa database.';
        }
    }
}

$suppliers = $pdo->query(
    'SELECT s.*, COUNT(p.id) AS purchase_count
     FROM suppliers s LEFT JOIN purchases p ON p.supplier_id = s.id
     GROUP BY s.id ORDER BY s.created_at DESC, s.id DESC'
)->fetchAll();
$pageTitle = 'Pemasok';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold">Pemasok</h1>
    <p class="mt-1 text-slate-500">Kelola mitra dan riwayat pemasok perusahaan.</p>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="mb-6 grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-2">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <input name="supplier_code" required maxlength="30" placeholder="Kode pemasok"
           value="<?= h($_POST['supplier_code'] ?? $editing['supplier_code'] ?? '') ?>" class="rounded-lg border p-3">
    <input name="name" required maxlength="150" placeholder="Nama pemasok"
           value="<?= h($_POST['name'] ?? $editing['name'] ?? '') ?>" class="rounded-lg border p-3">
    <input name="phone" maxlength="30" placeholder="Telepon"
           value="<?= h($_POST['phone'] ?? $editing['phone'] ?? '') ?>" class="rounded-lg border p-3">
    <input name="email" type="email" maxlength="150" placeholder="Email"
           value="<?= h($_POST['email'] ?? $editing['email'] ?? '') ?>" class="rounded-lg border p-3">
    <textarea name="address" placeholder="Alamat" class="rounded-lg border p-3 md:col-span-2"><?= h($_POST['address'] ?? $editing['address'] ?? '') ?></textarea>
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white md:col-span-2">
        <?= $editing ? 'Simpan Perubahan' : 'Tambah Pemasok' ?>
    </button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Kode</th><th class="p-4">Nama</th><th class="p-4">Kontak</th><th class="p-4">Riwayat</th><th class="p-4">Aksi</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($suppliers as $supplier): ?>
            <tr>
                <td class="p-4"><?= h($supplier['supplier_code']) ?></td>
                <td class="p-4 font-medium"><?= h($supplier['name']) ?></td>
                <td class="p-4"><?= h($supplier['phone'] ?? '') ?><br><?= h($supplier['email'] ?? '') ?></td>
                <td class="p-4"><?= (int) $supplier['purchase_count'] ?> pembelian</td>
                <td class="p-4">
                    <a class="mr-3 font-semibold text-blue-700" href="?edit=<?= (int) $supplier['id'] ?>">Edit</a>
                    <?php if ((int) $supplier['purchase_count'] === 0): ?>
                        <form method="post" class="inline" onsubmit="return confirm('Hapus pemasok ini?')">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                            <button class="font-semibold text-red-700">Hapus</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$suppliers): ?><tr><td class="p-5 text-center text-slate-500" colspan="5">Belum ada pemasok.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

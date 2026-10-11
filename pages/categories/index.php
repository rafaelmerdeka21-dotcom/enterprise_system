<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
$editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);
$editing = null;
if ($editId && $editId > 0) {
    $stmt = $pdo->prepare('SELECT id, name, description FROM categories WHERE id = ?');
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
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                if ($name === '' || strlen($name) > 100) {
                    throw new InvalidArgumentException('Nama kategori wajib diisi (maksimal 100 karakter).');
                }
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                    $stmt->execute([$name, $description, $id]);
                    $notificationMessage = 'Perubahan kategori ' . $name . ' berhasil disimpan.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                    $stmt->execute([$name, $description]);
                    $notificationMessage = 'Kategori ' . $name . ' berhasil ditambahkan.';
                }
            } elseif ($action === 'delete') {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
                if (!$id || $id < 1) {
                    throw new InvalidArgumentException('Kategori tidak valid.');
                }
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
                $stmt->execute([$id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Kategori masih digunakan oleh barang.');
                }
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                $stmt->execute([$id]);
                $notificationMessage = 'Kategori berhasil dihapus.';
            }
            redirectTo(
                '/pages/categories/index.php',
                $notificationMessage ?? 'Perubahan kategori berhasil disimpan.'
            );
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = $e->getCode() === '23000'
                ? 'Nama kategori tersebut sudah terdaftar.'
                : 'Kategori gagal disimpan. Periksa database.';
        }
    }
}

$categories = $pdo->query(
    'SELECT c.id, c.name, c.description, c.created_at, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.created_at DESC, c.id DESC'
)->fetchAll();
$pageTitle = 'Kategori Barang';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold">Kategori Barang</h1>
    <p class="mt-1 text-slate-500">Kelompokkan barang agar inventaris lebih mudah ditelusuri.</p>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="mb-6 grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-[1fr_2fr_auto]">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <input name="name" required maxlength="100" placeholder="Nama kategori"
           value="<?= h($_POST['name'] ?? $editing['name'] ?? '') ?>" class="rounded-lg border p-3">
    <input name="description" placeholder="Keterangan (opsional)"
           value="<?= h($_POST['description'] ?? $editing['description'] ?? '') ?>" class="rounded-lg border p-3">
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white"><?= $editing ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Kategori</th><th class="p-4">Keterangan</th><th class="p-4">Barang</th><th class="p-4">Dibuat</th><th class="p-4">Aksi</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($categories as $category): ?>
            <tr>
                <td class="p-4 font-medium"><?= h($category['name']) ?></td>
                <td class="p-4"><?= h($category['description'] ?? '') ?></td>
                <td class="p-4"><?= (int) $category['product_count'] ?></td>
                <td class="p-4"><?= h($category['created_at']) ?></td>
                <td class="p-4">
                    <a href="?edit=<?= (int) $category['id'] ?>" class="mr-3 font-semibold text-blue-700">Edit</a>
                    <?php if ((int) $category['product_count'] === 0): ?>
                        <form method="post" onsubmit="return confirm('Hapus kategori ini?')">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                            <button class="font-semibold text-red-700">Hapus</button>
                        </form>
                    <?php else: ?><span class="text-slate-400">Digunakan</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?><tr><td class="p-5 text-center text-slate-500" colspan="5">Belum ada kategori.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

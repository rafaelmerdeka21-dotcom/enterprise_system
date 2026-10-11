<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        $newStock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
        $note = trim($_POST['note'] ?? '');
        if (!$productId || $productId < 1 || $newStock === false || $newStock < 0) {
            $error = 'Barang dan jumlah stok baru harus valid.';
        } elseif ($note === '' || strlen($note) > 255) {
            $error = 'Alasan penyesuaian wajib diisi (maksimal 255 karakter).';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                if (!$product) {
                    throw new InvalidArgumentException('Barang tidak ditemukan.');
                }
                $oldStock = (int) $product['stock'];
                if ($oldStock !== $newStock) {
                    $update = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
                    $update->execute([$newStock, $productId]);
                    $movement = $pdo->prepare(
                        "INSERT INTO stock_movements
                         (product_id, user_id, movement_type, quantity, reference_note)
                         VALUES (?, ?, 'adjustment', ?, ?)"
                    );
                    $movement->execute([
                        $productId,
                        (int) $_SESSION['user']['id'],
                        abs($newStock - $oldStock),
                        "{$note} (stok {$oldStock} menjadi {$newStock})",
                    ]);
                }
                $pdo->commit();
                redirectTo(
                    '/pages/stock/index.php',
                    'Stok fisik barang berhasil disesuaikan. Riwayat mutasi sudah tercatat.'
                );
            } catch (InvalidArgumentException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $error = 'Penyesuaian stok gagal disimpan.';
            }
        }
    }
}

try {
    [$from, $to] = dateRangeFromRequest();
} catch (InvalidArgumentException $e) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
    $error = $e->getMessage();
}
$stmt = $pdo->prepare(
    'SELECT m.created_at, m.movement_type, m.quantity, m.reference_note,
            p.code, p.name AS product_name, u.name AS user_name
     FROM stock_movements m
     JOIN products p ON p.id = m.product_id
     LEFT JOIN users u ON u.id = m.user_id
     WHERE m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY m.created_at DESC, m.id DESC'
);
$stmt->execute([$from, $to]);
$movements = $stmt->fetchAll();
$products = $pdo->query('SELECT id, code, name, stock FROM products ORDER BY name')->fetchAll();
$pageTitle = 'Mutasi Stok';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold">Mutasi & Penyesuaian Stok</h1>
    <p class="mt-1 text-slate-500">Telusuri perubahan stok dan koreksi stok fisik dengan catatan audit.</p>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="mb-6 grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-2">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <label class="text-sm font-medium">Barang
        <select name="product_id" required class="mt-1 w-full rounded-lg border p-3">
            <option value="">Pilih barang</option>
            <?php foreach ($products as $product): ?>
                <option value="<?= (int) $product['id'] ?>"><?= h($product['code'] . ' - ' . $product['name'] . ' (stok ' . $product['stock'] . ')') ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium">Stok fisik terbaru
        <input type="number" name="stock" min="0" step="1" required class="mt-1 w-full rounded-lg border p-3">
    </label>
    <label class="text-sm font-medium md:col-span-2">Alasan penyesuaian
        <input name="note" maxlength="255" required class="mt-1 w-full rounded-lg border p-3" placeholder="Contoh: hasil opname gudang">
    </label>
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white md:col-span-2">Simpan Penyesuaian</button>
</form>
<form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
    <label class="text-sm">Dari<input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <label class="text-sm">Sampai<input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white">Terapkan</button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Waktu</th><th class="p-4">Barang</th><th class="p-4">Tipe</th><th class="p-4">Jumlah</th><th class="p-4">Catatan</th><th class="p-4">Pengguna</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($movements as $movement): ?>
            <tr>
                <td class="p-4"><?= h($movement['created_at']) ?></td>
                <td class="p-4"><?= h($movement['code'] . ' - ' . $movement['product_name']) ?></td>
                <td class="p-4"><?= h(ucfirst($movement['movement_type'])) ?></td>
                <td class="p-4"><?= (int) $movement['quantity'] ?></td>
                <td class="p-4"><?= h($movement['reference_note'] ?? '') ?></td>
                <td class="p-4"><?= h($movement['user_name'] ?? 'Pengguna dihapus') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$movements): ?><tr><td class="p-5 text-center text-slate-500" colspan="6">Tidak ada mutasi pada rentang waktu ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

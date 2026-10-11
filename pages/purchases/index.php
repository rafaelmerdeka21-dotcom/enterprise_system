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
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        if (!$id || $id < 1 || !in_array($action, ['receive', 'cancel'], true)) {
            $error = 'Permintaan perubahan status tidak valid.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT status FROM purchases WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $purchase = $stmt->fetch();
                if (!$purchase || $purchase['status'] !== 'pending') {
                    throw new InvalidArgumentException('Hanya pembelian tertunda yang dapat diproses.');
                }
                if ($action === 'receive') {
                    $details = $pdo->prepare('SELECT product_id, quantity FROM purchase_details WHERE purchase_id = ?');
                    $details->execute([$id]);
                    foreach ($details->fetchAll() as $detail) {
                        $update = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                        $update->execute([$detail['quantity'], $detail['product_id']]);
                        $movement = $pdo->prepare(
                            "INSERT INTO stock_movements (product_id,user_id,movement_type,quantity,reference_note)
                             VALUES (?,?,'in',?,?)"
                        );
                        $movement->execute([
                            $detail['product_id'],
                            (int) $_SESSION['user']['id'],
                            $detail['quantity'],
                            'Penerimaan pembelian #' . $id,
                        ]);
                    }
                    $newStatus = 'received';
                } else {
                    $newStatus = 'cancelled';
                }
                $update = $pdo->prepare('UPDATE purchases SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $id]);
                $pdo->commit();
                $message = $action === 'receive'
                    ? 'Pembelian diterima dan stok barang berhasil diperbarui.'
                    : 'Pesanan pembelian berhasil dibatalkan.';
                redirectTo('/pages/purchases/index.php', $message);
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
                $error = 'Status pembelian gagal diperbarui.';
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
    'SELECT p.id, p.invoice_number, p.purchase_date, p.total_amount, p.status,
            s.name AS supplier_name, u.name AS user_name,
            GROUP_CONCAT(CONCAT(d.product_name, " × ", d.quantity) ORDER BY d.id SEPARATOR ", ") AS items
     FROM purchases p
     JOIN suppliers s ON s.id = p.supplier_id
     LEFT JOIN users u ON u.id = p.user_id
     LEFT JOIN purchase_details d ON d.purchase_id = p.id
     WHERE p.purchase_date >= ? AND p.purchase_date < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY p.id ORDER BY p.purchase_date DESC, p.id DESC'
);
$stmt->execute([$from, $to]);
$purchases = $stmt->fetchAll();
$pageTitle = 'Pembelian';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Pembelian</h1><p class="mt-1 text-slate-500">Catat pesanan pemasok dan proses penerimaan stok.</p></div>
    <a href="<?= BASE_URL ?>/pages/purchases/create.php" class="rounded-lg bg-teal-700 px-4 py-3 font-semibold text-white">+ Pembelian Baru</a>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
    <label class="text-sm">Dari<input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <label class="text-sm">Sampai<input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white">Terapkan</button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Faktur</th><th class="p-4">Tanggal</th><th class="p-4">Pemasok / Barang</th><th class="p-4">Total</th><th class="p-4">Status</th><th class="p-4">Aksi</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($purchases as $purchase): ?>
            <tr>
                <td class="p-4 font-medium"><?= h($purchase['invoice_number']) ?></td>
                <td class="p-4"><?= h($purchase['purchase_date']) ?></td>
                <td class="p-4"><?= h($purchase['supplier_name']) ?><br><span class="text-slate-500"><?= h($purchase['items'] ?? '') ?></span></td>
                <td class="p-4">Rp <?= number_format((float) $purchase['total_amount'], 0, ',', '.') ?></td>
                <td class="p-4"><?= h(ucfirst($purchase['status'])) ?></td>
                <td class="p-4">
                    <?php if ($purchase['status'] === 'pending'): ?>
                        <form method="post" class="flex flex-wrap gap-2">
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $purchase['id'] ?>">
                            <button name="action" value="receive" class="font-semibold text-teal-700">Terima</button>
                            <button name="action" value="cancel" class="font-semibold text-red-700" onclick="return confirm('Batalkan pesanan ini?')">Batalkan</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$purchases): ?><tr><td class="p-5 text-center text-slate-500" colspan="6">Belum ada transaksi pembelian pada rentang ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

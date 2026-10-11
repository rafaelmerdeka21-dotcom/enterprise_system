<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibLogin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $paymentMethod = $_POST['payment_method'] ?? '';
        $customerId = $_POST['customer_id'] ?? '';
        if (!is_array($productIds) || !is_array($quantities) ||
            !in_array($paymentMethod, ['cash', 'transfer', 'qris'], true)
        ) {
            $error = 'Data pembayaran tidak valid.';
        } else {
            $requested = [];
            foreach ($productIds as $i => $productId) {
                $quantity = filter_var($quantities[$i] ?? '', FILTER_VALIDATE_INT);
                if ($productId === '' && ($quantities[$i] ?? '') === '') {
                    continue;
                }
                $productId = filter_var($productId, FILTER_VALIDATE_INT);
                if (!$productId || $productId < 1 || $quantity === false || $quantity < 1) {
                    $error = 'Setiap baris barang harus memiliki barang dan jumlah minimal satu.';
                    break;
                }
                $requested[$productId] = ($requested[$productId] ?? 0) + $quantity;
            }
            $customerId = $customerId === '' ? null : filter_var($customerId, FILTER_VALIDATE_INT);
            if ($customerId === false || ($customerId !== null && $customerId < 1)) {
                $error = 'Pelanggan tidak valid.';
            } elseif (!$requested && $error === '') {
                $error = 'Tambahkan sekurangnya satu barang.';
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();
                    if ($customerId !== null) {
                        $check = $pdo->prepare('SELECT id FROM customers WHERE id = ?');
                        $check->execute([$customerId]);
                        if (!$check->fetch()) {
                            throw new InvalidArgumentException('Pelanggan tidak ditemukan.');
                        }
                    }
                    $invoice = 'INV-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
                    $insert = $pdo->prepare(
                        "INSERT INTO sales (invoice_number,user_id,sale_date,total_amount,payment_method,status,customer_id)
                         VALUES (?,?,NOW(),0,?,'completed',?)"
                    );
                    $insert->execute([$invoice, (int) $_SESSION['user']['id'], $paymentMethod, $customerId]);
                    $saleId = (int) $pdo->lastInsertId();
                    $total = 0.0;
                    foreach ($requested as $productId => $quantity) {
                        $query = $pdo->prepare('SELECT name, selling_price, stock FROM products WHERE id = ? FOR UPDATE');
                        $query->execute([$productId]);
                        $product = $query->fetch();
                        if (!$product) {
                            throw new InvalidArgumentException('Salah satu barang tidak ditemukan.');
                        }
                        if ((int) $product['stock'] < $quantity) {
                            throw new InvalidArgumentException('Stok ' . $product['name'] . ' tidak mencukupi.');
                        }
                        $price = (float) $product['selling_price'];
                        $subtotal = $price * $quantity;
                        $detail = $pdo->prepare(
                            'INSERT INTO sale_details (sale_id,product_id,product_name,quantity,price,subtotal)
                             VALUES (?,?,?,?,?,?)'
                        );
                        $detail->execute([$saleId, $productId, $product['name'], $quantity, $price, $subtotal]);
                        $decrease = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                        $decrease->execute([$quantity, $productId]);
                        $movement = $pdo->prepare(
                            "INSERT INTO stock_movements (product_id,user_id,movement_type,quantity,reference_note)
                             VALUES (?,?,'out',?,?)"
                        );
                        $movement->execute([$productId, (int) $_SESSION['user']['id'], $quantity, 'Penjualan ' . $invoice]);
                        $total += $subtotal;
                    }
                    $update = $pdo->prepare('UPDATE sales SET total_amount = ? WHERE id = ?');
                    $update->execute([$total, $saleId]);
                    $pdo->commit();
                    setFlashNotification(
                        'Penjualan berhasil disimpan',
                        'Faktur ' . $invoice . ' sudah dibuat dan stok barang diperbarui.'
                    );
                    redirectTo('/pages/sales/invoice.php?id=' . $saleId);
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
                    $error = 'Penjualan gagal disimpan.';
                }
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
    'SELECT s.id, s.invoice_number, s.sale_date, s.total_amount, s.payment_method, s.status,
            u.name AS user_name, c.name AS customer_name
     FROM sales s LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY s.sale_date DESC, s.id DESC'
);
$stmt->execute([$from, $to]);
$sales = $stmt->fetchAll();
$products = $pdo->query('SELECT id, code, name, selling_price, stock FROM products ORDER BY name')->fetchAll();
$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$pageTitle = 'Penjualan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6"><h1 class="text-2xl font-bold">Penjualan & Faktur</h1><p class="mt-1 text-slate-500">Catat transaksi, stok berkurang otomatis, dan cetak faktur.</p></div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="mb-6 space-y-4 rounded-xl bg-white p-5 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <div class="grid gap-3 md:grid-cols-2">
        <label class="text-sm font-medium">Pelanggan (opsional)
            <select name="customer_id" class="mt-1 w-full rounded-lg border p-3">
                <option value="">Pelanggan umum</option>
                <?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"><?= h($customer['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium">Pembayaran
            <select name="payment_method" required class="mt-1 w-full rounded-lg border p-3">
                <option value="cash">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option>
            </select>
        </label>
    </div>
    <div>
        <div class="mb-2 flex items-center justify-between"><h2 class="font-semibold">Barang terjual</h2><button id="add-item" type="button" class="text-sm font-semibold text-teal-700">+ Tambah baris</button></div>
        <div id="items" class="space-y-2">
            <div class="item-row grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
                <select name="product_ids[]" class="rounded-lg border p-3"><option value="">Pilih barang</option>
                    <?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= h($product['code'] . ' - ' . $product['name'] . ' (stok ' . $product['stock'] . ')') ?></option><?php endforeach; ?>
                </select>
                <input type="number" name="quantities[]" min="1" step="1" placeholder="Jumlah" class="rounded-lg border p-3">
                <button type="button" class="remove-item rounded-lg border px-3 text-red-700">Hapus</button>
            </div>
        </div>
    </div>
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white">Simpan Penjualan & Buat Faktur</button>
</form>
<template id="item-template">
    <div class="item-row grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
        <select name="product_ids[]" class="rounded-lg border p-3"><option value="">Pilih barang</option>
            <?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= h($product['code'] . ' - ' . $product['name'] . ' (stok ' . $product['stock'] . ')') ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="quantities[]" min="1" step="1" placeholder="Jumlah" class="rounded-lg border p-3">
        <button type="button" class="remove-item rounded-lg border px-3 text-red-700">Hapus</button>
    </div>
</template>
<script>
document.getElementById('add-item').addEventListener('click', () => {
    document.getElementById('items').append(document.getElementById('item-template').content.cloneNode(true));
});
document.getElementById('items').addEventListener('click', (event) => {
    if (event.target.classList.contains('remove-item') && document.querySelectorAll('.item-row').length > 1) {
        event.target.closest('.item-row').remove();
    }
});
</script>
<form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
    <label class="text-sm">Dari<input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <label class="text-sm">Sampai<input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white">Terapkan</button>
</form>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-200"><tr><th class="p-4">Faktur</th><th class="p-4">Tanggal</th><th class="p-4">Pelanggan</th><th class="p-4">Pembayaran</th><th class="p-4">Total</th><th class="p-4">Cetak</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($sales as $sale): ?>
            <tr><td class="p-4 font-medium"><?= h($sale['invoice_number']) ?></td><td class="p-4"><?= h($sale['sale_date']) ?></td><td class="p-4"><?= h($sale['customer_name'] ?? 'Umum') ?></td><td class="p-4"><?= h(strtoupper($sale['payment_method'])) ?></td><td class="p-4">Rp <?= number_format((float) $sale['total_amount'], 0, ',', '.') ?></td><td class="p-4"><a class="font-semibold text-teal-700" href="<?= BASE_URL ?>/pages/sales/invoice.php?id=<?= (int) $sale['id'] ?>">Lihat faktur</a></td></tr>
        <?php endforeach; ?>
        <?php if (!$sales): ?><tr><td class="p-5 text-center text-slate-500" colspan="6">Belum ada penjualan pada rentang ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

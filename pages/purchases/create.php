<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();
$products = $pdo->query('SELECT id, code, name, purchase_price FROM products ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $supplierId = filter_var($_POST['supplier_id'] ?? '', FILTER_VALIDATE_INT);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $notes = trim($_POST['notes'] ?? '');
        if (!$supplierId || !is_array($productIds) || !is_array($quantities)) {
            $error = 'Pemasok dan barang wajib dipilih.';
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
            if (!$requested && $error === '') {
                $error = 'Tambahkan sekurangnya satu barang.';
            }
            if ($notes !== '' && strlen($notes) > 2000) {
                $error = 'Catatan maksimal 2.000 karakter.';
            }

            if ($error === '') {
                try {
                    $pdo->beginTransaction();
                    $supplierCheck = $pdo->prepare('SELECT id FROM suppliers WHERE id = ?');
                    $supplierCheck->execute([$supplierId]);
                    if (!$supplierCheck->fetch()) {
                        throw new InvalidArgumentException('Pemasok tidak ditemukan.');
                    }
                    $invoice = 'PO-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
                    $insert = $pdo->prepare(
                        "INSERT INTO purchases (invoice_number,supplier_id,user_id,purchase_date,total_amount,status,notes)
                         VALUES (?,?,?,NOW(),0,'pending',?)"
                    );
                    $insert->execute([$invoice, $supplierId, (int) $_SESSION['user']['id'], $notes]);
                    $purchaseId = (int) $pdo->lastInsertId();
                    $total = 0.0;
                    foreach ($requested as $productId => $quantity) {
                        $query = $pdo->prepare('SELECT name, purchase_price FROM products WHERE id = ? FOR UPDATE');
                        $query->execute([$productId]);
                        $product = $query->fetch();
                        if (!$product) {
                            throw new InvalidArgumentException('Salah satu barang tidak ditemukan.');
                        }
                        $price = (float) $product['purchase_price'];
                        $subtotal = $price * $quantity;
                        $detail = $pdo->prepare(
                            'INSERT INTO purchase_details (purchase_id,product_id,product_name,quantity,purchase_price,subtotal)
                             VALUES (?,?,?,?,?,?)'
                        );
                        $detail->execute([$purchaseId, $productId, $product['name'], $quantity, $price, $subtotal]);
                        $total += $subtotal;
                    }
                    $update = $pdo->prepare('UPDATE purchases SET total_amount = ? WHERE id = ?');
                    $update->execute([$total, $purchaseId]);
                    $pdo->commit();
                    redirectTo(
                        '/pages/purchases/index.php',
                        'Pesanan ' . $invoice . ' berhasil disimpan sebagai pembelian tertunda.'
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
                    $error = 'Pembelian gagal disimpan.';
                }
            }
        }
    }
}
$pageTitle = 'Pembelian Baru';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-6">
    <a href="<?= BASE_URL ?>/pages/purchases/index.php" class="text-teal-700 hover:underline">&larr; Kembali ke pembelian</a>
    <h1 class="mt-3 text-2xl font-bold">Catat Pembelian</h1>
    <p class="mt-1 text-slate-500">Pesanan tersimpan sebagai tertunda; stok bertambah setelah menekan Terima.</p>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<?php if (!$suppliers): ?><div class="mb-4 rounded-lg bg-amber-50 p-3 text-amber-800">Tambahkan pemasok terlebih dahulu di menu Pemasok.</div><?php endif; ?>
<form method="post" class="space-y-5 rounded-xl bg-white p-5 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <label class="block text-sm font-medium">Pemasok
        <select name="supplier_id" required class="mt-1 w-full rounded-lg border p-3">
            <option value="">Pilih pemasok</option>
            <?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>"><?= h($supplier['name']) ?></option><?php endforeach; ?>
        </select>
    </label>
    <div>
        <div class="mb-2 flex items-center justify-between"><h2 class="font-semibold">Barang yang dipesan</h2><button id="add-item" type="button" class="text-sm font-semibold text-teal-700">+ Tambah baris</button></div>
        <div id="items" class="space-y-2">
            <div class="item-row grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
                <select name="product_ids[]" class="rounded-lg border p-3">
                    <option value="">Pilih barang</option>
                    <?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= h($product['code'] . ' - ' . $product['name']) ?></option><?php endforeach; ?>
                </select>
                <input type="number" name="quantities[]" min="1" step="1" placeholder="Jumlah" class="rounded-lg border p-3">
                <button type="button" class="remove-item rounded-lg border px-3 text-red-700">Hapus</button>
            </div>
        </div>
    </div>
    <label class="block text-sm font-medium">Catatan
        <textarea name="notes" maxlength="2000" class="mt-1 w-full rounded-lg border p-3"></textarea>
    </label>
    <button class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white">Simpan Pesanan Tertunda</button>
</form>
<template id="item-template">
    <div class="item-row grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
        <select name="product_ids[]" class="rounded-lg border p-3">
            <option value="">Pilih barang</option>
            <?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= h($product['code'] . ' - ' . $product['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="number" name="quantities[]" min="1" step="1" placeholder="Jumlah" class="rounded-lg border p-3">
        <button type="button" class="remove-item rounded-lg border px-3 text-red-700">Hapus</button>
    </div>
</template>
<script>
document.getElementById('add-item').addEventListener('click', () => {
    document.getElementById('items').append(
        document.getElementById('item-template').content.cloneNode(true)
    );
});
document.getElementById('items').addEventListener('click', (event) => {
    if (event.target.classList.contains('remove-item')) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) event.target.closest('.item-row').remove();
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

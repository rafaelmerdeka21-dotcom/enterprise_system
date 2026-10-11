<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
wajibLogin();
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID faktur tidak valid.');
}
$stmt = $pdo->prepare(
    'SELECT s.*, u.name AS cashier, c.name AS customer_name, c.phone AS customer_phone
     FROM sales s LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = ?'
);
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) {
    http_response_code(404);
    exit('Faktur tidak ditemukan.');
}
$stmt = $pdo->prepare(
    'SELECT product_name, quantity, price, subtotal FROM sale_details WHERE sale_id = ? ORDER BY id'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$pageTitle = 'Faktur ' . $sale['invoice_number'];
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
@media print {
    header, aside, nav, footer, .no-print { display: none !important; }
    main { padding: 0 !important; }
    body { background: #fff !important; }
    .invoice { box-shadow: none !important; border: 0 !important; }
    .qris-payment { break-inside: avoid; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>
<div class="no-print mb-4 flex justify-between">
    <a href="<?= BASE_URL ?>/pages/sales/index.php" class="text-teal-700 hover:underline">&larr; Kembali</a>
    <button onclick="window.print()" class="rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white">Cetak / Simpan PDF</button>
</div>
<article class="invoice mx-auto max-w-3xl rounded-xl bg-white p-8 shadow-sm">
    <div class="flex flex-wrap justify-between gap-4 border-b pb-5">
        <div><p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Enterprise System</p><h1 class="mt-2 text-3xl font-bold">Faktur Penjualan</h1></div>
        <div class="text-right"><p class="font-bold"><?= h($sale['invoice_number']) ?></p><p class="text-slate-500"><?= h($sale['sale_date']) ?></p></div>
    </div>
    <div class="grid gap-3 py-5 sm:grid-cols-2">
        <p><span class="text-slate-500">Pelanggan</span><br><strong><?= h($sale['customer_name'] ?? 'Pelanggan Umum') ?></strong></p>
        <p><span class="text-slate-500">Kasir</span><br><strong><?= h($sale['cashier'] ?? '-') ?></strong></p>
        <?php if (!empty($sale['customer_phone'])): ?><p><span class="text-slate-500">Telepon</span><br><?= h($sale['customer_phone']) ?></p><?php endif; ?>
        <p><span class="text-slate-500">Pembayaran</span><br><strong><?= h(strtoupper($sale['payment_method'])) ?></strong></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-y bg-slate-50"><tr><th class="p-3">Barang</th><th class="p-3 text-right">Jumlah</th><th class="p-3 text-right">Harga</th><th class="p-3 text-right">Subtotal</th></tr></thead>
            <tbody class="divide-y">
                <?php foreach ($items as $item): ?>
                    <tr><td class="p-3"><?= h($item['product_name']) ?></td><td class="p-3 text-right"><?= (int) $item['quantity'] ?></td><td class="p-3 text-right">Rp <?= number_format((float) $item['price'], 0, ',', '.') ?></td><td class="p-3 text-right">Rp <?= number_format((float) $item['subtotal'], 0, ',', '.') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3" class="p-3 text-right">Total</th><th class="p-3 text-right text-lg">Rp <?= number_format((float) $sale['total_amount'], 0, ',', '.') ?></th></tr></tfoot>
        </table>
    </div>
    <?php if ($sale['payment_method'] === 'qris' && $sale['status'] === 'completed'): ?>
        <section class="qris-payment mt-8 rounded-2xl border border-teal-100 bg-teal-50 p-5 text-center">
            <h2 class="text-lg font-bold text-slate-900">Pembayaran QRIS</h2>
            <p class="mt-1 text-sm text-slate-600">
                Scan kode QRIS resmi berikut dengan aplikasi pembayaran Anda.
            </p>
            <img src="<?= BASE_URL ?>/assets/images/qris-merchant.png"
                 alt="Kode QRIS resmi untuk pembayaran ke merchant"
                 class="mx-auto mt-4 h-56 w-56 rounded-xl border border-white bg-white p-2 object-contain shadow-sm"
                 width="224" height="224">
            <p class="mt-3 text-sm font-semibold text-slate-800">
                Pastikan jumlah pembayaran sesuai total faktur:
                Rp <?= number_format((float) $sale['total_amount'], 0, ',', '.') ?>
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Faktur <?= h($sale['invoice_number']) ?>
            </p>
        </section>
    <?php endif; ?>
    <p class="mt-8 border-t pt-4 text-center text-sm text-slate-500">Terima kasih atas kepercayaan Anda.</p>
</article>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

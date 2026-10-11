<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

$error = '';
try {
    [$from, $to] = dateRangeFromRequest();
} catch (InvalidArgumentException $e) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
    $error = $e->getMessage();
}
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS transaction_count, COALESCE(SUM(total_amount),0) AS total
     FROM sales WHERE status='completed' AND sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)"
);
$stmt->execute([$from, $to]);
$salesSummary = $stmt->fetch();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS transaction_count, COALESCE(SUM(total_amount),0) AS total
     FROM purchases WHERE status='received' AND purchase_date >= ? AND purchase_date < DATE_ADD(?, INTERVAL 1 DAY)"
);
$stmt->execute([$from, $to]);
$purchaseSummary = $stmt->fetch();
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM stock_movements WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)'
);
$stmt->execute([$from, $to]);
$movementCount = (int) $stmt->fetchColumn();
$lowStockCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= min_stock')->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT d.product_name, SUM(d.quantity) AS quantity, SUM(d.subtotal) AS revenue
     FROM sale_details d JOIN sales s ON s.id=d.sale_id
     WHERE s.status='completed' AND s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY d.product_id,d.product_name ORDER BY quantity DESC LIMIT 10"
);
$stmt->execute([$from, $to]);
$topProducts = $stmt->fetchAll();
$pageTitle = 'Laporan';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
@media print { header, aside, nav, footer, .no-print { display: none !important; } main { padding: 0 !important; } body { background: #fff !important; } }
</style>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Laporan Perusahaan</h1><p class="mt-1 text-slate-500">Ringkasan transaksi dan inventaris berdasarkan rentang tanggal.</p></div>
    <button onclick="window.print()" class="no-print rounded-lg border bg-white px-4 py-2 font-semibold">Cetak / Simpan PDF</button>
</div>
<?php if ($error !== ''): ?><div class="mb-4 rounded-lg bg-red-50 p-3 text-red-700"><?= h($error) ?></div><?php endif; ?>
<form method="get" class="no-print mb-5 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
    <label class="text-sm">Dari<input type="date" name="from" value="<?= h($from) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <label class="text-sm">Sampai<input type="date" name="to" value="<?= h($to) ?>" class="mt-1 block rounded-lg border p-2"></label>
    <button class="rounded-lg bg-teal-700 px-4 py-2 text-white">Terapkan</button>
</form>
<p class="mb-4 text-sm text-slate-500">Periode laporan: <?= h($from) ?> sampai <?= h($to) ?>. Data transaksi yang sudah dibatalkan tidak dihitung.</p>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-slate-500">Penjualan selesai</p><p class="mt-2 text-2xl font-bold">Rp <?= number_format((float) $salesSummary['total'], 0, ',', '.') ?></p><p class="text-sm text-slate-500"><?= (int) $salesSummary['transaction_count'] ?> transaksi</p></div>
    <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-slate-500">Pembelian diterima</p><p class="mt-2 text-2xl font-bold">Rp <?= number_format((float) $purchaseSummary['total'], 0, ',', '.') ?></p><p class="text-sm text-slate-500"><?= (int) $purchaseSummary['transaction_count'] ?> transaksi</p></div>
    <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-slate-500">Mutasi stok</p><p class="mt-2 text-2xl font-bold"><?= $movementCount ?></p><p class="text-sm text-slate-500">pada periode ini</p></div>
    <div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-slate-500">Barang stok menipis</p><p class="mt-2 text-2xl font-bold"><?= $lowStockCount ?></p><p class="text-sm text-slate-500">kondisi saat ini</p></div>
</div>
<div class="no-print mb-5 flex flex-wrap gap-2">
    <?php foreach (['sales' => 'Penjualan', 'purchases' => 'Pembelian', 'stock' => 'Mutasi Stok', 'inventory' => 'Inventaris'] as $type => $label): ?>
        <a class="rounded-lg border bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
           href="<?= BASE_URL ?>/pages/reports/export.php?type=<?= h($type) ?>&amp;from=<?= h($from) ?>&amp;to=<?= h($to) ?>">
            Ekspor <?= h($label) ?> CSV
        </a>
    <?php endforeach; ?>
</div>
<section class="rounded-xl bg-white p-5 shadow-sm">
    <h2 class="mb-4 text-lg font-bold">Barang terlaris pada periode</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-200"><tr><th class="p-3">Barang</th><th class="p-3 text-right">Jumlah terjual</th><th class="p-3 text-right">Nilai penjualan</th></tr></thead>
            <tbody class="divide-y">
                <?php foreach ($topProducts as $product): ?><tr><td class="p-3"><?= h($product['product_name']) ?></td><td class="p-3 text-right"><?= (int) $product['quantity'] ?></td><td class="p-3 text-right">Rp <?= number_format((float) $product['revenue'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
                <?php if (!$topProducts): ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Belum ada penjualan pada periode ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

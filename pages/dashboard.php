
<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';

wajibAdmin();

$user = $_SESSION['user'];

// Hitung semua produk.
$totalProducts = (int) $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

// Hitung produk yang stoknya habis.
$outOfStock = (int) $pdo->query(
    "SELECT COUNT(*) FROM products WHERE stock = 0"
)->fetchColumn();

// Hitung produk dengan stok di bawah batas minimum.
$lowStock = (int) $pdo->query(
    "SELECT COUNT(*) FROM products
     WHERE stock > 0 AND stock <= min_stock"
)->fetchColumn();

// Hitung omzet dari transaksi yang selesai.
$totalSales = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0)
     FROM sales WHERE status = 'completed'"
)->fetchColumn();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

?>

<div class="mb-8">
    <h1 class="text-3xl font-bold">
        Dashboard Administrator
    </h1>
    <p class="text-slate-500 mt-2">
        Selamat datang,
        <?= h($user['name']) ?>.
        Berikut ringkasan sistem perusahaan.
    </p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <p class="text-slate-500">Total Produk</p>
        <p class="text-3xl font-bold mt-3">
            <?= $totalProducts ?>
        </p>
        <a href="<?= BASE_URL ?>/pages/products/index.php"
           class="text-sm text-teal-700 hover:underline mt-4 inline-block">
            Kelola barang &rarr;
        </a>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <p class="text-slate-500">Stok Menipis</p>
        <p class="text-3xl font-bold text-amber-600 mt-3">
            <?= $lowStock ?>
        </p>
        <p class="text-sm text-slate-500 mt-4">
            Barang yang mencapai batas minimum.
        </p>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <p class="text-slate-500">Stok Habis</p>
        <p class="text-3xl font-bold text-red-600 mt-3">
            <?= $outOfStock ?>
        </p>
        <p class="text-sm text-slate-500 mt-4">
            Barang yang tidak memiliki stok.
        </p>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm">
        <p class="text-slate-500">Total Omzet</p>
        <p class="text-2xl font-bold text-teal-700 mt-3">
            Rp <?= number_format($totalSales, 0, ',', '.') ?>
        </p>
        <p class="text-sm text-slate-500 mt-4">
            Total transaksi berstatus selesai.
        </p>
    </div>

</div>

<div class="bg-white rounded-xl p-6 shadow-sm mt-6">
    <h2 class="text-xl font-bold mb-2">
        Modul Sistem
    </h2>
    <p class="text-slate-500 mb-5">
        Pilih modul yang ingin kamu kelola.
    </p>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <a href="<?= BASE_URL ?>/pages/products/index.php"
           class="rounded-lg bg-teal-700 px-5 py-4 font-semibold text-white hover:bg-teal-800">
            Manajemen Barang
        </a>
        <a href="<?= BASE_URL ?>/pages/sales/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Penjualan & Faktur
        </a>
        <a href="<?= BASE_URL ?>/pages/purchases/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Pembelian & Pemasok
        </a>
        <a href="<?= BASE_URL ?>/pages/reports/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Laporan Perusahaan
        </a>
        <a href="<?= BASE_URL ?>/pages/categories/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Kategori Barang
        </a>
        <a href="<?= BASE_URL ?>/pages/stock/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Mutasi & Penyesuaian Stok
        </a>
        <a href="<?= BASE_URL ?>/pages/suppliers/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Data Pemasok
        </a>
        <a href="<?= BASE_URL ?>/pages/users/index.php"
           class="rounded-lg bg-white px-5 py-4 font-semibold text-teal-800 ring-1 ring-slate-200 hover:bg-teal-50">
            Pengguna & Hak Akses
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

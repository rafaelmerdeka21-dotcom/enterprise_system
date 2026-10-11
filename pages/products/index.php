
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';

wajibAdmin();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        p.id,
        p.code,
        p.name,
        p.image_path,
        p.purchase_price,
        p.selling_price,
        p.stock,
        p.min_stock,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
";

$params = [];

if ($search !== '') {
    $sql .= " WHERE p.code LIKE ? OR p.name LIKE ? OR c.name LIKE ?";
    $keyword = '%' . $search . '%';
    $params = [$keyword, $keyword, $keyword];
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'Manajemen Barang';
require_once __DIR__ . '/../../includes/header.php';

?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Manajemen Barang</h1>
        <p class="text-slate-500 mt-1">
            Kelola produk dan persediaan perusahaan.
        </p>
    </div>

    <a href="create.php"
       class="rounded-lg bg-teal-700 px-4 py-3 font-semibold text-white hover:bg-teal-800">
        + Tambah Barang
    </a>
</div>

<form method="GET" class="flex gap-2 mb-5">
    <input
        type="text"
        name="search"
        value="<?= h($search) ?>"
        placeholder="Cari kode, nama, atau kategori..."
        class="w-full rounded-lg border border-slate-300 bg-white p-3"
    >

    <button class="rounded-lg bg-slate-800 px-5 text-white">
        Cari
    </button>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-sm text-left">
        <thead class="bg-slate-200 text-slate-700">
            <tr>
                <th class="p-4">Foto</th>
                <th class="p-4">Kode</th>
                <th class="p-4">Nama Barang</th>
                <th class="p-4">Kategori</th>
                <th class="p-4">Harga Beli</th>
                <th class="p-4">Harga Jual</th>
                <th class="p-4">Stok</th>
                <th class="p-4">Status</th>
                <th class="p-4">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
        <?php if (!$products): ?>
            <tr>
                <td colspan="9" class="p-8 text-center text-slate-500">
                    Belum ada barang. Tambahkan barang pertama kamu.
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($products as $product): ?>
            <tr class="hover:bg-slate-50">
                <td class="p-4">
                    <?php if (!empty($product['image_path'])): ?>
                        <img src="<?= BASE_URL ?>/<?= h($product['image_path']) ?>"
                             alt="<?= h($product['name']) ?>"
                             class="h-12 w-12 rounded-lg object-cover">
                    <?php else: ?>
                        <span class="text-slate-400">-</span>
                    <?php endif; ?>
                </td>
                <td class="p-4 font-medium"><?= h($product['code']) ?></td>
                <td class="p-4"><?= h($product['name']) ?></td>
                <td class="p-4">
                    <?= h($product['category_name'] ?? 'Tanpa kategori') ?>
                </td>
                <td class="p-4">
                    Rp <?= number_format((float) $product['purchase_price'], 0, ',', '.') ?>
                </td>
                <td class="p-4">
                    Rp <?= number_format((float) $product['selling_price'], 0, ',', '.') ?>
                </td>
                <td class="p-4"><?= (int) $product['stock'] ?></td>
                <td class="p-4">
                    <?php if ((int) $product['stock'] === 0): ?>
                        <span class="rounded-full bg-red-100 px-2 py-1 text-red-700">
                            Habis
                        </span>
                    <?php elseif ((int) $product['stock'] <= (int) $product['min_stock']): ?>
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800">
                            Menipis
                        </span>
                    <?php else: ?>
                        <span class="rounded-full bg-green-100 px-2 py-1 text-green-700">
                            Tersedia
                        </span>
                    <?php endif; ?>
                </td>
                <td class="p-4 whitespace-nowrap">
                    <a href="edit.php?id=<?= (int) $product['id'] ?>"
                       class="font-semibold text-blue-700 hover:underline">
                        Edit
                    </a>
                    <span class="text-slate-300 mx-1">|</span>
                    <form action="delete.php" method="POST" class="inline"
                          onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                        <input type="hidden" name="csrf_token"
                               value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="id"
                               value="<?= (int) $product['id'] ?>">
                        <button type="submit"
                                class="font-semibold text-red-700 hover:underline">
                            Hapus
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="mt-4 text-sm text-slate-500">
    Total barang terdaftar: <?= count($products) ?> produk
    pada hasil pencarian saat ini.
</p>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/product_image.php';

wajibAdmin();

$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID barang tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Barang tidak ditemukan.');
}

$error = '';
$uploadedImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $categoryInput = $_POST['category_id'] ?? '';
        $purchaseInput = $_POST['purchase_price'] ?? '';
        $sellingInput = $_POST['selling_price'] ?? '';
        $stockInput = $_POST['stock'] ?? '';
        $minStockInput = $_POST['min_stock'] ?? '';

        $categoryId = $categoryInput === ''
            ? null
            : filter_var($categoryInput, FILTER_VALIDATE_INT);

        $purchasePrice = filter_var(
            $purchaseInput,
            FILTER_VALIDATE_FLOAT
        );
        $sellingPrice = filter_var(
            $sellingInput,
            FILTER_VALIDATE_FLOAT
        );
        $stock = filter_var($stockInput, FILTER_VALIDATE_INT);
        $minStock = filter_var($minStockInput, FILTER_VALIDATE_INT);

        if ($code === '' || $name === '') {
            $error = 'Kode dan nama barang wajib diisi.';
        } elseif (strlen($code) > 30 || strlen($name) > 150) {
            $error = 'Kode atau nama barang terlalu panjang.';
        } elseif (
            $purchasePrice === false || $sellingPrice === false ||
            $purchasePrice < 0 || $sellingPrice < 0
        ) {
            $error = 'Harga harus berupa angka nol atau lebih.';
        } elseif (
            $stock === false || $minStock === false ||
            $stock < 0 || $minStock < 0
        ) {
            $error = 'Stok dan batas stok harus bilangan bulat nol atau lebih.';
        } elseif (
            $categoryInput !== '' &&
            ($categoryId === false || $categoryId === null || $categoryId < 1)
        ) {
            $error = 'Kategori tidak valid.';
        } else {
            try {
                $uploadedImage = storeProductImage($_FILES['image'] ?? []);
                $imagePath = $uploadedImage ?? $product['image_path'];
                $pdo->beginTransaction();

                // Kunci baris agar stok yang diubah tidak saling menimpa.
                $lock = $pdo->prepare(
                    "SELECT stock FROM products WHERE id = ? FOR UPDATE"
                );
                $lock->execute([$id]);
                $current = $lock->fetch();

                if (!$current) {
                    throw new RuntimeException('Barang tidak ditemukan.');
                }

                if ($categoryId !== null) {
                    $check = $pdo->prepare(
                        "SELECT id FROM categories WHERE id = ?"
                    );
                    $check->execute([$categoryId]);

                    if (!$check->fetch()) {
                        throw new InvalidArgumentException(
                            'Kategori tidak ditemukan.'
                        );
                    }
                }

                $oldStock = (int) $current['stock'];

                $update = $pdo->prepare(
                    "UPDATE products
                     SET category_id = ?, code = ?, name = ?, image_path = ?,
                         purchase_price = ?, selling_price = ?,
                         stock = ?, min_stock = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $categoryId,
                    $code,
                    $name,
                    $imagePath,
                    $purchasePrice,
                    $sellingPrice,
                    $stock,
                    $minStock,
                    $id
                ]);

                if ($stock !== $oldStock) {
                    $movement = $pdo->prepare(
                        "INSERT INTO stock_movements
                         (product_id, user_id, movement_type,
                          quantity, reference_note)
                         VALUES (?, ?, 'adjustment', ?, ?)"
                    );

                    $movement->execute([
                        $id,
                        (int) $_SESSION['user']['id'],
                        abs($stock - $oldStock),
                        "Penyesuaian stok: {$oldStock} menjadi {$stock}"
                    ]);
                }

                $pdo->commit();

                if ($uploadedImage !== null) {
                    removeProductImage($product['image_path']);
                }

                setFlashNotification(
                    'Perubahan barang tersimpan',
                    'Data ' . $name . ' berhasil diperbarui.'
                );
                header('Location: index.php');
                exit;

            } catch (InvalidArgumentException | RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                removeProductImage($uploadedImage);
                $error = $e->getMessage();

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                removeProductImage($uploadedImage);

                error_log($e->getMessage());

                $error = $e->getCode() === '23000'
                    ? 'Kode barang sudah digunakan.'
                    : 'Perubahan barang gagal disimpan.';
            }
        }
    }
}

$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name"
)->fetchAll();

function editValue(string $key, array $product): string
{
    return h($_POST[$key] ?? $product[$key] ?? '');
}

$pageTitle = 'Edit Barang';
require_once __DIR__ . '/../../includes/header.php';

?>

<div class="max-w-2xl mx-auto">
    <a href="index.php" class="text-teal-700 hover:underline">
        &larr; Kembali ke daftar barang
    </a>

    <div class="bg-white rounded-2xl shadow-sm p-6 md:p-8 mt-4">
        <h1 class="text-2xl font-bold mb-6">Edit Barang</h1>

        <?php if ($error !== ''): ?>
            <div class="bg-red-50 text-red-700 rounded-lg p-3 mb-5">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token"
                   value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div>
                <label class="block mb-1 font-medium">Kode Barang</label>
                <input name="code" required maxlength="30"
                       value="<?= editValue('code', $product) ?>"
                       class="w-full border rounded-lg p-3">
            </div>

            <div>
                <label class="block mb-1 font-medium">Nama Barang</label>
                <input name="name" required maxlength="150"
                       value="<?= editValue('name', $product) ?>"
                       class="w-full border rounded-lg p-3">
            </div>

            <div>
                <label class="mb-1 block font-medium" for="image">Foto Barang</label>
                <?php if (!empty($product['image_path'])): ?>
                    <img src="<?= BASE_URL ?>/<?= h($product['image_path']) ?>"
                         alt="<?= h($product['name']) ?>"
                         class="mb-3 h-24 w-24 rounded-lg object-cover">
                <?php endif; ?>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                       class="w-full rounded-lg border p-3">
                <p class="mt-1 text-xs text-slate-500">JPG, PNG, atau WebP; maksimal 5 MB.</p>
            </div>

            <div>
                <label class="block mb-1 font-medium">Kategori</label>
                <?php $selectedCategory = $_POST['category_id']
                    ?? $product['category_id'] ?? ''; ?>

                <select name="category_id"
                        class="w-full border rounded-lg p-3">
                    <option value="">Tanpa kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (string)$selectedCategory ===
                                (string)$category['id'] ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1 font-medium">Harga Beli (Rp)</label>
                    <input type="number" name="purchase_price" min="0"
                           step="0.01" required
                           value="<?= editValue('purchase_price', $product) ?>"
                           class="w-full border rounded-lg p-3">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Harga Jual (Rp)</label>
                    <input type="number" name="selling_price" min="0"
                           step="0.01" required
                           value="<?= editValue('selling_price', $product) ?>"
                           class="w-full border rounded-lg p-3">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Stok</label>
                    <input type="number" name="stock" min="0" step="1"
                           required value="<?= editValue('stock', $product) ?>"
                           class="w-full border rounded-lg p-3">
                </div>

                <div>
                    <label class="block mb-1 font-medium">
                        Batas Stok Menipis
                    </label>
                    <input type="number" name="min_stock" min="0" step="1"
                           required value="<?= editValue('min_stock', $product) ?>"
                           class="w-full border rounded-lg p-3">
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-teal-700 text-white rounded-lg p-3 font-semibold hover:bg-teal-800">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

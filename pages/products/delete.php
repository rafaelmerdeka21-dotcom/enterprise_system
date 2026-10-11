
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';

wajibAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrfValid()) {
    http_response_code(403);
    exit('Token keamanan tidak valid. Silakan kembali dan coba lagi.');
}

$id = filter_var(
    $_POST['id'] ?? '',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID barang tidak valid.');
}

$history = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM sale_details WHERE product_id = ?) +
        (SELECT COUNT(*) FROM purchase_details WHERE product_id = ?) +
        (SELECT COUNT(*) FROM stock_movements WHERE product_id = ?)'
);
$history->execute([$id, $id, $id]);
if ((int) $history->fetchColumn() > 0) {
    http_response_code(409);
    exit('Barang memiliki riwayat transaksi atau stok. Penghapusan tidak diizinkan agar data historis tetap utuh.');
}

$stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    header('Location: index.php');
    exit;
}

setFlashNotification(
    'Barang berhasil dihapus',
    'Barang sudah dihapus dari inventaris.'
);
header('Location: index.php');
exit;

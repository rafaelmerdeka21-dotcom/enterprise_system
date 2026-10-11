<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/module_helpers.php';
wajibAdmin();

try {
    [$from, $to] = dateRangeFromRequest();
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit(h($e->getMessage()));
}
$type = $_GET['type'] ?? '';
if ($type === 'sales') {
    $stmt = $pdo->prepare(
        'SELECT s.invoice_number,s.sale_date,c.name,s.payment_method,s.status,s.total_amount,u.name
         FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id
         WHERE s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY s.sale_date DESC,s.id DESC'
    );
    $stmt->execute([$from, $to]);
    sendCsv('laporan-penjualan-' . $from . '-sd-' . $to . '.csv',
        ['Faktur','Tanggal','Pelanggan','Pembayaran','Status','Total (Rp)','Petugas'],
        $stmt->fetchAll(PDO::FETCH_NUM));
}
if ($type === 'purchases') {
    $stmt = $pdo->prepare(
        'SELECT p.invoice_number,p.purchase_date,s.name,p.status,p.total_amount,u.name
         FROM purchases p JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN users u ON u.id=p.user_id
         WHERE p.purchase_date >= ? AND p.purchase_date < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY p.purchase_date DESC,p.id DESC'
    );
    $stmt->execute([$from, $to]);
    sendCsv('laporan-pembelian-' . $from . '-sd-' . $to . '.csv',
        ['Nomor Pesanan','Tanggal','Pemasok','Status','Total (Rp)','Petugas'],
        $stmt->fetchAll(PDO::FETCH_NUM));
}
if ($type === 'stock') {
    $stmt = $pdo->prepare(
        'SELECT m.created_at,p.code,p.name,m.movement_type,m.quantity,m.reference_note,u.name
         FROM stock_movements m JOIN products p ON p.id=m.product_id LEFT JOIN users u ON u.id=m.user_id
         WHERE m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY m.created_at DESC,m.id DESC'
    );
    $stmt->execute([$from, $to]);
    sendCsv('laporan-mutasi-stok-' . $from . '-sd-' . $to . '.csv',
        ['Waktu','Kode Barang','Nama Barang','Tipe','Jumlah','Catatan','Petugas'],
        $stmt->fetchAll(PDO::FETCH_NUM));
}
if ($type === 'inventory') {
    $stmt = $pdo->query(
        'SELECT p.code,p.name,c.name,p.stock,p.min_stock,p.purchase_price,p.selling_price,p.created_at
         FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.name'
    );
    sendCsv('inventaris-' . date('Y-m-d') . '.csv',
        ['Kode','Barang','Kategori','Stok','Batas Minimum','Harga Beli (Rp)','Harga Jual (Rp)','Dibuat'],
        $stmt->fetchAll(PDO::FETCH_NUM));
}
http_response_code(400);
exit('Jenis ekspor tidak dikenal.');

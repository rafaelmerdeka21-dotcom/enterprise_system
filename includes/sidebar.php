<?php
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

$icons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'products' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/><path d="M12 13v8"/>',
    'categories' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3.4 13.4a2 2 0 0 1 0-2.8l7.2-7.2A2 2 0 0 1 12 2.8h6a3 3 0 0 1 3 3v6a2 2 0 0 1-.4 1.6Z"/><circle cx="16.5" cy="7.5" r="1"/>',
    'stock' => '<path d="M3 12h4l3-8 4 16 3-8h4"/><path d="M5 4v3M19 17v3"/>',
    'sales' => '<path d="M3 3h2l2.4 12.1a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/>',
    'purchases' => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
    'suppliers' => '<path d="M3 7h11v11H3zM14 11h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="2"/><circle cx="17.5" cy="19" r="2"/><path d="M6 4h5"/>',
    'reports' => '<path d="M4 19V5M4 19h17"/><path d="m7 15 4-5 3 2 5-7"/><path d="M15 5h4v4"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
];

$renderLink = static function (string $path, string $icon, string $label, bool $mobile = false) use ($icons, $currentPath): void {
    $url = BASE_URL . $path;
    $active = $currentPath === $url;
    if ($mobile) {
        $class = $active
            ? 'flex min-w-14 shrink-0 flex-col items-center gap-1 rounded-xl bg-teal-50 px-2 py-1.5 text-teal-800'
            : 'flex min-w-14 shrink-0 flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-slate-500 hover:bg-slate-50 hover:text-teal-800';
        echo '<a href="' . h($url) . '" class="' . $class . '"' . ($active ? ' aria-current="page"' : '') . '>';
        echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">' . $icons[$icon] . '</svg>';
        echo '<span class="text-[10px] font-medium">' . h($label) . '</span></a>';
        return;
    }

    $class = $active
        ? 'group flex items-center gap-3 rounded-xl bg-teal-50 px-3 py-2.5 font-semibold text-teal-800 ring-1 ring-inset ring-teal-100'
        : 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-slate-600 transition hover:bg-slate-50 hover:text-teal-800';
    echo '<a href="' . h($url) . '" class="' . $class . '"' . ($active ? ' aria-current="page"' : '') . '>';
    echo '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ' . ($active ? 'bg-white text-teal-700 shadow-sm' : 'bg-slate-100 text-slate-500 transition group-hover:bg-white group-hover:text-teal-700') . '">';
    echo '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]">' . $icons[$icon] . '</svg>';
    echo '</span><span>' . h($label) . '</span></a>';
};
?>
<aside class="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:block">
    <div class="border-b border-slate-100 px-5 py-5">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-cyan-700 text-white shadow-md shadow-teal-900/15">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/><path d="M3 12l9 4.5 9-4.5M3 16.5 12 21l9-4.5"/>
                </svg>
            </span>
            <div>
                <p class="font-bold text-slate-800">Enterprise</p>
                <p class="text-xs text-slate-400">Management System</p>
            </div>
        </div>
    </div>
    <nav class="space-y-1 px-3 py-4 text-sm">
        <?php if ($isAdmin): ?>
            <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Ringkasan</p>
            <?php $renderLink('/pages/dashboard.php', 'dashboard', 'Dashboard'); ?>
            <p class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Persediaan</p>
            <?php $renderLink('/pages/products/index.php', 'products', 'Manajemen Barang'); ?>
            <?php $renderLink('/pages/categories/index.php', 'categories', 'Kategori'); ?>
            <?php $renderLink('/pages/stock/index.php', 'stock', 'Mutasi & Penyesuaian Stok'); ?>
            <p class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Operasional</p>
            <?php $renderLink('/pages/sales/index.php', 'sales', 'Penjualan & Faktur'); ?>
            <?php $renderLink('/pages/purchases/index.php', 'purchases', 'Pembelian'); ?>
            <?php $renderLink('/pages/suppliers/index.php', 'suppliers', 'Pemasok'); ?>
            <p class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Administrasi</p>
            <?php $renderLink('/pages/reports/index.php', 'reports', 'Laporan Perusahaan'); ?>
            <?php $renderLink('/pages/users/index.php', 'users', 'Pengguna & Hak Akses'); ?>
        <?php else: ?>
            <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Operasional</p>
            <?php $renderLink('/pages/sales/index.php', 'sales', 'Penjualan & Faktur'); ?>
        <?php endif; ?>
    </nav>
    <div class="mx-4 mt-4 rounded-xl bg-gradient-to-br from-teal-50 to-cyan-50 p-4">
        <p class="text-xs font-semibold text-teal-900">Kelola bisnis lebih mudah</p>
        <p class="mt-1 text-[11px] leading-relaxed text-teal-800/70">Pantau stok, transaksi, dan laporan perusahaan dari satu tempat.</p>
    </div>
</aside>

<nav aria-label="Navigasi utama" class="fixed inset-x-0 bottom-0 z-20 flex justify-start gap-1 overflow-x-auto border-t border-slate-200 bg-white/95 px-2 py-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgba(15,23,42,0.06)] backdrop-blur lg:hidden">
    <?php if ($isAdmin): ?>
        <?php $renderLink('/pages/dashboard.php', 'dashboard', 'Dashboard', true); ?>
        <?php $renderLink('/pages/products/index.php', 'products', 'Barang', true); ?>
        <?php $renderLink('/pages/categories/index.php', 'categories', 'Kategori', true); ?>
        <?php $renderLink('/pages/stock/index.php', 'stock', 'Stok', true); ?>
        <?php $renderLink('/pages/sales/index.php', 'sales', 'Penjualan', true); ?>
        <?php $renderLink('/pages/purchases/index.php', 'purchases', 'Pembelian', true); ?>
        <?php $renderLink('/pages/suppliers/index.php', 'suppliers', 'Pemasok', true); ?>
        <?php $renderLink('/pages/reports/index.php', 'reports', 'Laporan', true); ?>
        <?php $renderLink('/pages/users/index.php', 'users', 'Pengguna', true); ?>
    <?php else: ?>
        <?php $renderLink('/pages/sales/index.php', 'sales', 'Penjualan', true); ?>
    <?php endif; ?>
</nav>

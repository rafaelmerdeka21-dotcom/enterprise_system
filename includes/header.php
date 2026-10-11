<?php
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Enterprise System';
$currentUser = $_SESSION['user'] ?? [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - Enterprise System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">
<?php require __DIR__ . '/flash_notification.php'; ?>
<header class="sticky top-0 z-20 bg-white shadow-sm">
    <div class="flex min-h-16 items-center justify-between gap-4 px-5">
        <a href="<?= BASE_URL ?><?= ($_SESSION['user']['role'] ?? '') === 'admin' ? '/pages/dashboard.php' : '/pages/sales/index.php' ?>"
           class="text-lg font-bold text-teal-900">
            Enterprise System
        </a>
        <div class="flex items-center gap-4 text-sm">
            <span class="hidden text-slate-600 sm:inline">
                <?= h($currentUser['name'] ?? 'Pengguna') ?>
            </span>
            <form action="<?= BASE_URL ?>/logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <button type="submit"
                        class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-teal-800 to-cyan-700 px-3.5 py-2.5 font-semibold text-white shadow-md shadow-teal-900/15 transition hover:-translate-y-0.5 hover:from-teal-700 hover:to-cyan-600 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform group-hover:translate-x-0.5">
                    <path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
                </svg>
                <span>Keluar</span>
                </button>
            </form>
        </div>
    </div>
</header>

<div class="flex min-h-[calc(100vh-4rem)]">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="min-w-0 flex-1 p-4 pb-24 md:p-8 lg:pb-8">

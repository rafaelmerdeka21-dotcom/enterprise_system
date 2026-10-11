<?php
$flashNotification = takeFlashNotification();

if ($flashNotification !== null):
    $notificationStyles = [
        'success' => [
            'icon' => '<path d="m5 12 4 4L19 6"/>',
            'iconClass' => 'bg-emerald-100 text-emerald-700',
            'progressClass' => 'bg-emerald-500',
        ],
        'info' => [
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',
            'iconClass' => 'bg-sky-100 text-sky-700',
            'progressClass' => 'bg-sky-500',
        ],
        'warning' => [
            'icon' => '<path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/>',
            'iconClass' => 'bg-amber-100 text-amber-700',
            'progressClass' => 'bg-amber-500',
        ],
        'error' => [
            'icon' => '<path d="m6 6 12 12M18 6 6 18"/>',
            'iconClass' => 'bg-rose-100 text-rose-700',
            'progressClass' => 'bg-rose-500',
        ],
    ];
    $notificationStyle = $notificationStyles[$flashNotification['type']];
?>
<style>
@keyframes notification-enter {
    from { opacity: 0; transform: translate3d(1rem, -0.5rem, 0) scale(.97); }
    to { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
}
@keyframes notification-progress {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}
.notification-enter { animation: notification-enter .3s cubic-bezier(.2,.8,.2,1) both; }
.notification-progress { transform-origin: left; animation: notification-progress 6s linear forwards; }
@media (prefers-reduced-motion: reduce) {
    .notification-enter, .notification-progress { animation: none; }
}
</style>
<div class="pointer-events-none fixed inset-x-4 top-20 z-50 flex justify-center sm:inset-x-auto sm:right-6 sm:justify-end">
    <section id="flash-notification" role="status" aria-live="polite"
             class="notification-enter pointer-events-auto relative w-full max-w-md overflow-hidden rounded-2xl border border-white/70 bg-white/95 p-4 shadow-2xl shadow-slate-900/15 backdrop-blur">
        <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= h($notificationStyle['iconClass']) ?>">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <?= $notificationStyle['icon'] ?>
                </svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="font-semibold text-slate-900"><?= h($flashNotification['title']) ?></p>
                <p class="mt-1 text-sm leading-relaxed text-slate-600"><?= h($flashNotification['message']) ?></p>
            </div>
            <button type="button" aria-label="Tutup notifikasi"
                    onclick="document.getElementById('flash-notification').remove()"
                    class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                    <path d="m18 6-12 12M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="absolute inset-x-0 bottom-0 h-1 bg-slate-100">
            <div class="notification-progress h-full <?= h($notificationStyle['progressClass']) ?>"></div>
        </div>
    </section>
</div>
<script>
window.setTimeout(() => {
    const notification = document.getElementById('flash-notification');
    if (notification) {
        notification.style.transition = 'opacity .25s ease, transform .25s ease';
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-.5rem)';
        window.setTimeout(() => notification.remove(), 260);
    }
}, 6000);
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Notifications';
$db = getDB();

// Mark all read
if ($_GET['mark_all'] ?? false) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    header('Location: ' . APP_URL . '/pages/notifications.php');
    exit;
}

$notes = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notes->execute([$user['id']]); $notes = $notes->fetchAll() ?: [];

$unread = array_filter($notes, fn($n) => !$n['is_read']);

include __DIR__ . '/../includes/header.php';

$typeIcons = [
    'message'   => ['fas fa-envelope',       'bg-blue-100  text-blue-600'],
    'complaint' => ['fas fa-exclamation-circle','bg-red-100 text-red-600'],
    'welcome'   => ['fas fa-hand-wave',       'bg-green-100 text-green-600'],
    'rental'    => ['fas fa-key',             'bg-purple-100 text-purple-600'],
    'payment'   => ['fas fa-wallet',          'bg-yellow-100 text-yellow-600'],
];
?>

<div class="page-hero py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl text-white">Notifications</h1>
            <p class="text-blue-200 mt-1"><?= count((array)$unread) ?> unread</p>
        </div>
        <?php if ($unread): ?>
        <a href="?mark_all=1" class="btn-outline border-white/30 text-white hover:bg-white/10 text-sm py-2">
            <i class="fas fa-check-double"></i> Mark all read
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <?php if (empty($notes)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-16 text-center text-slate-400">
        <i class="fas fa-bell-slash text-5xl mb-4 block opacity-20"></i>
        <p class="font-medium text-slate-500">No notifications yet</p>
        <p class="text-sm mt-1">You'll see alerts here for messages, complaints, and more</p>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
        <?php foreach ($notes as $n):
            [$icon,$color] = $typeIcons[$n['type']] ?? ['fas fa-bell','bg-slate-100 text-slate-500'];
        ?>
        <div class="flex items-start gap-4 p-4 hover:bg-slate-50 transition-colors <?= !$n['is_read'] ? 'bg-blue-50/30' : '' ?>">
            <div class="w-10 h-10 rounded-full <?= $color ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="<?= $icon ?> text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-800 <?= !$n['is_read'] ? '' : 'font-medium' ?>">
                        <?= sanitize($n['title']) ?>
                    </p>
                    <?php if (!$n['is_read']): ?>
                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-1"></span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-600 mt-0.5"><?= sanitize($n['body']) ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
                <?php if ($n['link']): ?>
                <a href="<?= sanitize($n['link']) ?>" class="text-xs text-primary-600 font-medium hover:underline mt-1 inline-block">View →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['admin']);
$pageTitle = 'All Complaints';
$db = getDB();

$statusFilter = $_GET['status'] ?? '';
$where  = $statusFilter ? "WHERE c.status='$statusFilter'" : '';

$complaints = $db->query("
    SELECT c.*, p.title AS property_title, u.name AS tenant_name,
           l.name AS landlord_name, l.phone AS landlord_phone
    FROM complaints c
    JOIN properties p ON p.id=c.property_id
    JOIN users u ON u.id=c.tenant_id
    JOIN users l ON l.id=c.landlord_id
    $where
    ORDER BY c.created_at DESC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="font-display text-3xl text-white">All Complaints</h1>
        <p class="text-blue-200 mt-1"><?= count($complaints) ?> records</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex gap-2 mb-6 flex-wrap">
        <?php foreach (['' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $val => $label): ?>
        <a href="?status=<?= $val ?>"
           class="px-4 py-2 rounded-xl text-sm font-medium transition-colors
                  <?= $statusFilter === $val ? 'bg-primary-700 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Complaint</th><th>Tenant</th><th>Landlord</th><th>Property</th><th>Priority</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                <?php foreach ($complaints as $c): ?>
                <tr>
                    <td>
                        <p class="text-sm font-medium text-slate-800"><?= sanitize($c['title']) ?></p>
                        <p class="text-xs text-slate-500 capitalize"><?= $c['type'] ?></p>
                    </td>
                    <td class="text-sm text-slate-600"><?= sanitize($c['tenant_name']) ?></td>
                    <td>
                        <p class="text-sm text-slate-700"><?= sanitize($c['landlord_name']) ?></p>
                        <p class="text-xs text-slate-400"><?= sanitize($c['landlord_phone']) ?></p>
                    </td>
                    <td class="text-sm text-slate-600"><?= sanitize($c['property_title']) ?></td>
                    <td>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                            <?= $c['priority']==='urgent'?'bg-red-100 text-red-700':($c['priority']==='high'?'bg-orange-100 text-orange-700':'bg-slate-100 text-slate-600') ?>">
                            <?= ucfirst($c['priority']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= in_array($c['status'],['resolved','closed'])?'status-available':($c['status']==='in_progress'?'status-occupied':'status-maintenance') ?> text-xs">
                            <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
                        </span>
                    </td>
                    <td class="text-xs text-slate-400"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($complaints)): ?>
                <tr><td colspan="7" class="text-center text-slate-400 py-10">No complaints found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

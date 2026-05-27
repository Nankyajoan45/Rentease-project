<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord', 'agent', 'admin']);
$user = currentUser();
$pageTitle = 'Complaints & Maintenance';

$db = getDB();
$propertyFilter = (int)($_GET['property'] ?? 0);

$where = "WHERE c.landlord_id = ?";
$params = [$user['id']];
if ($propertyFilter) { $where .= " AND c.property_id = ?"; $params[] = $propertyFilter; }

$stmt = $db->prepare("
    SELECT c.*, p.title AS property_title, u.name AS tenant_name, u.phone AS tenant_phone, u.email AS tenant_email
    FROM complaints c JOIN properties p ON p.id = c.property_id JOIN users u ON u.id = c.tenant_id
    $where ORDER BY c.created_at DESC
");
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Handle resolution update
if ($_POST['update_complaint'] ?? false) {
    $cId = (int)$_POST['complaint_id'];
    $status = $_POST['status'];
    $notes = trim($_POST['resolution_notes'] ?? '');
    $allowed = ['open', 'in_progress', 'resolved', 'closed'];
    if (in_array($status, $allowed)) {
        $resolvedAt = in_array($status, ['resolved','closed']) ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE complaints SET status = ?, resolution_notes = ?, resolved_at = ?, updated_at = NOW() WHERE id = ? AND landlord_id = ?")
           ->execute([$status, $notes, $resolvedAt, $cId, $user['id']]);
        header('Location: ' . APP_URL . '/pages/landlord/complaints.php?updated=1');
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';

$counts = array_reduce($complaints, function($c, $comp) {
    $c[$comp['status']] = ($c[$comp['status']] ?? 0) + 1;
    return $c;
}, []);
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2 transition-colors">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <h1 class="font-display text-3xl text-white">Complaints & Maintenance</h1>
        <p class="text-blue-200 mt-1"><?= count($complaints) ?> total reports</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['updated'] ?? false): ?>
    <div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> Complaint updated successfully.</div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php foreach (['open'=>['#ef4444','Open'], 'in_progress'=>['#f59e0b','In Progress'], 'resolved'=>['#10b981','Resolved'], 'closed'=>['#6366f1','Closed']] as $status => $info): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
            <div class="text-2xl font-bold" style="color:<?= $info[0] ?>"><?= $counts[$status] ?? 0 ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= $info[1] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($complaints)): ?>
    <div class="text-center py-16 text-slate-400">
        <i class="fas fa-smile text-5xl mb-4 opacity-30"></i>
        <p>No complaints yet. Great job keeping tenants happy!</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($complaints as $c): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-slate-800"><?= sanitize($c['title']) ?></h3>
                            <span class="status-badge <?= $c['status'] === 'resolved' || $c['status'] === 'closed' ? 'status-available' : ($c['status'] === 'in_progress' ? 'status-occupied' : 'status-maintenance') ?> text-xs">
                                <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                            </span>
                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $c['priority'] === 'urgent' ? 'bg-red-100 text-red-700' : ($c['priority'] === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600') ?>">
                                <?= ucfirst($c['priority']) ?> Priority
                            </span>
                            <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full"><?= ucfirst($c['type']) ?></span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1"><i class="fas fa-home mr-1 text-accent-500"></i><?= sanitize($c['property_title']) ?></p>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap"><?= timeAgo($c['created_at']) ?></span>
                </div>

                <p class="text-sm text-slate-600 mb-3"><?= sanitize($c['description']) ?></p>

                <div class="flex items-center gap-4 text-xs text-slate-500 flex-wrap">
                    <span><i class="fas fa-user mr-1"></i><?= sanitize($c['tenant_name']) ?></span>
                    <a href="tel:<?= sanitize($c['tenant_phone']) ?>" class="hover:text-primary-600"><i class="fas fa-phone mr-1"></i><?= sanitize($c['tenant_phone']) ?></a>
                    <a href="mailto:<?= sanitize($c['tenant_email']) ?>" class="hover:text-primary-600"><i class="fas fa-envelope mr-1"></i><?= sanitize($c['tenant_email']) ?></a>
                </div>

                <?php if ($c['resolution_notes']): ?>
                <div class="mt-3 p-3 bg-green-50 rounded-xl border border-green-100 text-sm text-green-700">
                    <i class="fas fa-check-circle mr-1"></i> <?= sanitize($c['resolution_notes']) ?>
                </div>
                <?php endif; ?>

                <!-- Update Form -->
                <?php if (!in_array($c['status'], ['resolved','closed'])): ?>
                <details class="mt-3">
                    <summary class="text-sm text-primary-600 font-medium cursor-pointer hover:text-primary-800 list-none flex items-center gap-1">
                        <i class="fas fa-edit text-xs"></i> Update Status
                    </summary>
                    <form method="POST" class="mt-3 p-3 bg-slate-50 rounded-xl space-y-3">
                        <input type="hidden" name="update_complaint" value="1">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Status</label>
                                <select name="status" class="form-select text-sm py-2">
                                    <option value="open" <?= $c['status']==='open'?'selected':'' ?>>Open</option>
                                    <option value="in_progress" <?= $c['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                                    <option value="resolved" <?= $c['status']==='resolved'?'selected':'' ?>>Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">Resolution Notes</label>
                                <input type="text" name="resolution_notes" class="form-input text-sm py-2" placeholder="Brief resolution note..." value="<?= sanitize($c['resolution_notes'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary text-sm py-2">Update</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

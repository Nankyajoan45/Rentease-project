<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord', 'agent', 'admin']);
$user = currentUser();
$pageTitle = 'My Properties';

$db = getDB();
$success = $_GET['success'] ?? '';

$stmt = $db->prepare("
    SELECT p.*, 
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image,
           (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) AS image_count,
           (SELECT COUNT(*) FROM complaints WHERE property_id = p.id AND status IN ('open','in_progress')) AS open_issues,
           (SELECT COUNT(*) FROM rentals WHERE property_id = p.id AND status = 'active') AS active_rentals
    FROM properties p WHERE p.owner_id = ? ORDER BY p.created_at DESC
");
$stmt->execute([$user['id']]);
$properties = $stmt->fetchAll();

// Delete action
if ($_GET['delete'] ?? 0) {
    $delId = (int)$_GET['delete'];
    $check = $db->prepare("SELECT id FROM properties WHERE id = ? AND owner_id = ?");
    $check->execute([$delId, $user['id']]);
    if ($check->fetch()) {
        $db->prepare("DELETE FROM properties WHERE id = ?")->execute([$delId]);
        header('Location: ' . APP_URL . '/pages/landlord/properties.php?success=deleted');
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div>
            <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2 transition-colors">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <h1 class="font-display text-3xl text-white">My Properties</h1>
            <p class="text-blue-200 mt-1"><?= count($properties) ?> listings total</p>
        </div>
        <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="btn-accent">
            <i class="fas fa-plus"></i> Add Property
        </a>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($success === 'added'): ?>
    <div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> Property listed successfully!</div>
    <?php elseif ($success === 'deleted'): ?>
    <div class="alert alert-error mb-5"><i class="fas fa-trash"></i> Property removed.</div>
    <?php endif; ?>

    <?php if (empty($properties)): ?>
    <div class="text-center py-20">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-home text-3xl text-slate-300"></i>
        </div>
        <h3 class="font-semibold text-slate-700 text-lg mb-2">No properties yet</h3>
        <p class="text-slate-400 text-sm mb-5">Start by listing your first property</p>
        <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="btn-primary">Add Your First Property</a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Type</th>
                        <th>Price/Month</th>
                        <th>Status</th>
                        <th>Issues</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $p): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php if ($p['image']): ?>
                                <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">
                                <?php else: ?>
                                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-400 flex-shrink-0"><i class="fas fa-home text-sm"></i></div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-medium text-slate-800 text-sm"><?= sanitize($p['title']) ?></p>
                                    <p class="text-xs text-slate-500 flex items-center gap-1"><i class="fas fa-map-marker-alt text-accent-500"></i><?= sanitize($p['location']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td><span class="text-sm capitalize text-slate-600"><?= $p['property_type'] ?></span></td>
                        <td><span class="text-sm font-semibold text-primary-700"><?= formatPrice($p['price']) ?></span></td>
                        <td>
                            <form method="POST" action="<?= APP_URL ?>/api/properties.php?action=update_status&id=<?= $p['id'] ?>" onsubmit="this.querySelector('select').disabled=false">
                                <select name="status" onchange="this.form.submit()" class="form-select py-1.5 text-xs w-28">
                                    <?php foreach (['available','occupied','maintenance','inactive','booked'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php if ($p['open_issues'] > 0): ?>
                            <a href="<?= APP_URL ?>/pages/landlord/complaints.php?property=<?= $p['id'] ?>" class="inline-flex items-center gap-1 text-red-500 text-xs font-medium hover:underline">
                                <i class="fas fa-exclamation-circle"></i> <?= $p['open_issues'] ?> open
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center gap-1.5">
                                <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors" title="View">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="<?= APP_URL ?>/pages/landlord/edit-property.php?id=<?= $p['id'] ?>" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                <a href="<?= APP_URL ?>/pages/landlord/upload-images.php?id=<?= $p['id'] ?>" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors" title="Photos">
                                    <i class="fas fa-images text-sm"></i>
                                </a>
                                <button onclick="confirmAction('Are you sure you want to delete this property?', () => window.location='<?= APP_URL ?>/pages/landlord/properties.php?delete=<?= $p['id'] ?>')" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

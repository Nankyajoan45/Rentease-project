<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['admin']);
$pageTitle = 'All Properties';
$db = getDB();

// Delete
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM properties WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: ' . APP_URL . '/pages/admin/properties.php?deleted=1'); exit;
}

$status = $_GET['status'] ?? '';
$type   = $_GET['type']   ?? '';
$search = trim($_GET['search'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($status) { $where .= ' AND p.status=?'; $params[] = $status; }
if ($type)   { $where .= ' AND p.property_type=?'; $params[] = $type; }
if ($search) { $where .= ' AND (p.title LIKE ? OR p.location LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("
    SELECT p.*, u.name AS owner_name,
           (SELECT image_path FROM property_images WHERE property_id=p.id AND is_primary=1 LIMIT 1) AS image
    FROM properties p JOIN users u ON u.id=p.owner_id
    $where ORDER BY p.created_at DESC
");
$stmt->execute($params);
$properties = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="font-display text-3xl text-white">All Properties</h1>
        <p class="text-blue-200 mt-1"><?= count($properties) ?> listings</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['deleted'] ?? false): ?>
    <div class="alert alert-error mb-5"><i class="fas fa-trash"></i> Property deleted.</div>
    <?php endif; ?>

    <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label text-xs">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" class="form-input py-2.5 text-sm" placeholder="Title or location…">
        </div>
        <div>
            <label class="form-label text-xs">Status</label>
            <select name="status" class="form-select py-2.5 text-sm" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['available','occupied','maintenance','inactive'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label text-xs">Type</label>
            <select name="type" class="form-select py-2.5 text-sm" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['room','apartment','house','hostel','studio'] as $t): ?>
                <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary py-2.5 text-sm"><i class="fas fa-search"></i> Filter</button>
        <?php if ($status||$type||$search): ?>
        <a href="?" class="btn-outline py-2.5 text-sm"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Property</th><th>Owner</th><th>Type</th><th>Price</th><th>Status</th><th>Added</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($properties as $p): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <?php if ($p['image']): ?>
                            <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-400"><i class="fas fa-home text-sm"></i></div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm font-medium text-slate-800"><?= sanitize($p['title']) ?></p>
                                <p class="text-xs text-slate-500"><?= sanitize($p['location']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm text-slate-600"><?= sanitize($p['owner_name']) ?></td>
                    <td class="text-sm capitalize text-slate-600"><?= $p['property_type'] ?></td>
                    <td class="text-sm font-semibold text-primary-700"><?= formatPrice($p['price']) ?></td>
                    <td><span class="status-badge status-<?= $p['status'] ?> text-xs"><?= ucfirst($p['status']) ?></span></td>
                    <td class="text-xs text-slate-400"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-eye text-sm"></i></a>
                            <button onclick="confirmAction('Delete this property permanently?', ()=>window.location='<?= APP_URL ?>/pages/admin/properties.php?delete=<?= $p['id'] ?>')"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-trash text-sm"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord','agent','admin']);
$user = currentUser();
$pageTitle = 'Tenants';
$db = getDB();
$isAdmin = $user['role'] === 'admin';
$ownerJoin = $isAdmin ? '' : "AND p.owner_id = {$user['id']}";

$success = $error = '';

// Add rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'add_rental') {
    $propertyId = (int)$_POST['property_id'];
    $tenantId   = (int)$_POST['tenant_id'];
    $rent       = (float)$_POST['monthly_rent'];
    $deposit    = (float)($_POST['deposit']??0);
    $startDate  = $_POST['start_date'];
    $endDate    = $_POST['end_date'] ?: null;

    // Check ownership
    $propCheck = $db->query("SELECT owner_id FROM properties WHERE id=$propertyId")->fetch();
    if ($propCheck && ($isAdmin || $propCheck['owner_id'] == $user['id'])) {
        $db->prepare("INSERT INTO rentals (property_id, tenant_id, monthly_rent, deposit, start_date, end_date, status) VALUES (?,?,?,?,?,?,'active')")
           ->execute([$propertyId,$tenantId,$rent,$deposit,$startDate,$endDate]);
        $db->prepare("UPDATE properties SET status='occupied' WHERE id=?")->execute([$propertyId]);
        createNotification($tenantId, 'rental', 'Rental Confirmed', 'Your tenancy has been confirmed.', APP_URL.'/pages/tenant/dashboard.php');
        $success = 'Rental added successfully.';
    } else { $error = 'Unauthorized.'; }
}

// End rental
if (isset($_GET['end_rental'])) {
    $rid = (int)$_GET['end_rental'];
    $rental = $db->query("SELECT r.*,p.owner_id FROM rentals r JOIN properties p ON p.id=r.property_id WHERE r.id=$rid")->fetch();
    if ($rental && ($isAdmin || $rental['owner_id'] == $user['id'])) {
        $db->prepare("UPDATE rentals SET status='terminated',end_date=CURDATE() WHERE id=?")->execute([$rid]);
        $db->prepare("UPDATE properties SET status='available' WHERE id=?")->execute([$rental['property_id']]);
        header('Location: ' . APP_URL . '/pages/landlord/tenants.php?msg=ended'); exit;
    }
}

// Active rentals
$rentals = $db->query("
    SELECT r.*, p.title AS property_title, p.location, p.id AS pid,
           u.name AS tenant_name, u.email AS tenant_email, u.phone AS tenant_phone, u.id AS tid
    FROM rentals r
    JOIN properties p ON p.id=r.property_id
    JOIN users u ON u.id=r.tenant_id
    WHERE r.status='active' $ownerJoin
    ORDER BY r.start_date DESC
")->fetchAll();

// My properties (for form)
$myProps = $isAdmin
    ? $db->query("SELECT id,title FROM properties WHERE status='available' ORDER BY title")->fetchAll()
    : $db->query("SELECT id,title FROM properties WHERE owner_id={$user['id']} AND status='available' ORDER BY title")->fetchAll();

// Tenants list (for form)
$tenantsList = $db->query("SELECT id,name,email FROM users WHERE role='tenant' ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div>
            <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h1 class="font-display text-3xl text-white">Manage Tenants</h1>
            <p class="text-blue-200 mt-1"><?= count($rentals) ?> active rentals</p>
        </div>
        <button onclick="document.getElementById('addRentalModal').classList.remove('hidden')" class="btn-accent">
            <i class="fas fa-plus"></i> Add Rental
        </button>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['msg']??false): ?><div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> Rental ended successfully.</div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error   mb-5"><i class="fas fa-times-circle"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <?php if (empty($rentals)): ?>
    <div class="text-center py-16 bg-white rounded-2xl border border-slate-200 shadow-sm text-slate-400">
        <i class="fas fa-users text-5xl mb-4 block opacity-20"></i>
        <p class="font-medium text-slate-500">No active tenants</p>
        <button onclick="document.getElementById('addRentalModal').classList.remove('hidden')" class="btn-primary mt-4">
            <i class="fas fa-plus"></i> Add Rental
        </button>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Tenant</th><th>Property</th><th>Monthly Rent</th><th>Move-in</th><th>End Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rentals as $r): ?>
                <tr>
                    <td>
                        <p class="text-sm font-semibold text-slate-800"><?= sanitize($r['tenant_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= sanitize($r['tenant_email']) ?></p>
                        <p class="text-xs text-slate-400"><?= sanitize($r['tenant_phone']??'') ?></p>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/pages/property.php?id=<?= $r['pid'] ?>" class="text-sm text-primary-700 hover:underline font-medium"><?= sanitize($r['property_title']) ?></a>
                        <p class="text-xs text-slate-500"><?= sanitize($r['location']) ?></p>
                    </td>
                    <td class="text-sm font-semibold text-primary-700"><?= formatPrice($r['monthly_rent']) ?></td>
                    <td class="text-sm text-slate-600"><?= date('d M Y', strtotime($r['start_date'])) ?></td>
                    <td class="text-sm text-slate-600"><?= $r['end_date'] ? date('d M Y', strtotime($r['end_date'])) : '<span class="text-green-600 font-medium">Ongoing</span>' ?></td>
                    <td>
                        <div class="flex gap-2">
                            <a href="<?= APP_URL ?>/pages/messages.php?new=<?= $r['tid'] ?>" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-envelope text-sm"></i></a>
                            <button onclick="confirmAction('End this rental agreement?', ()=>window.location='<?= APP_URL ?>/pages/landlord/tenants.php?end_rental=<?= $r['id'] ?>')"
                                    class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"><i class="fas fa-times-circle text-sm"></i></button>
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

<!-- Add Rental Modal -->
<div id="addRentalModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl my-4">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-slate-800">Add New Rental</h3>
            <button onclick="document.getElementById('addRentalModal').classList.add('hidden')" class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 hover:bg-slate-200">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_rental">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div>
                <label class="form-label">Property *</label>
                <select name="property_id" class="form-select" required>
                    <option value="">Select available property…</option>
                    <?php foreach ($myProps as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= sanitize($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Tenant *</label>
                <select name="tenant_id" class="form-select" required>
                    <option value="">Select tenant…</option>
                    <?php foreach ($tenantsList as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?> — <?= sanitize($t['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Monthly Rent *</label>
                    <input type="number" name="monthly_rent" class="form-input" placeholder="UGX" required min="0">
                </div>
                <div>
                    <label class="form-label">Deposit</label>
                    <input type="number" name="deposit" class="form-input" placeholder="UGX" min="0">
                </div>
                <div>
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-input">
                </div>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="document.getElementById('addRentalModal').classList.add('hidden')" class="flex-1 btn-outline py-2.5 text-sm">Cancel</button>
                <button type="submit" class="flex-1 btn-primary justify-center py-2.5 text-sm"><i class="fas fa-plus"></i> Add Rental</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

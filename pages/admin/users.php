<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['admin']);
$user = currentUser();
$pageTitle = 'Manage Users';
$db = getDB();

// Toggle active
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== $user['id']) {
        $cur = $db->query("SELECT is_active FROM users WHERE id=$uid")->fetchColumn();
        $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$cur ? 0 : 1, $uid]);
    }
    header('Location: ' . APP_URL . '/pages/admin/users.php?msg=updated');
    exit;
}

$roleFilter = $_GET['role'] ?? '';
$search     = trim($_GET['search'] ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($roleFilter) { $where .= ' AND role=?'; $params[] = $roleFilter; }
if ($search)     { $where .= ' AND (name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="font-display text-3xl text-white">Manage Users</h1>
        <p class="text-blue-200 mt-1"><?= count($users) ?> users total</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['msg'] ?? false): ?>
    <div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> User updated.</div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label text-xs">Search</label>
            <input type="text" name="search" value="<?= sanitize($search) ?>" class="form-input py-2.5 text-sm" placeholder="Name or email…">
        </div>
        <div>
            <label class="form-label text-xs">Role</label>
            <select name="role" class="form-select py-2.5 text-sm" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <?php foreach (['tenant','landlord','agent','admin'] as $r): ?>
                <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary py-2.5 text-sm"><i class="fas fa-search"></i> Filter</button>
        <?php if ($roleFilter||$search): ?>
        <a href="?" class="btn-outline py-2.5 text-sm"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-800"><?= sanitize($u['name']) ?></p>
                                <p class="text-xs text-slate-500"><?= sanitize($u['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="text-xs px-2 py-1 rounded-full font-medium capitalize
                            <?= $u['role']==='admin'?'bg-purple-100 text-purple-700':($u['role']==='landlord'||$u['role']==='agent'?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700') ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td class="text-sm text-slate-600"><?= sanitize($u['phone']??'-') ?></td>
                    <td>
                        <span class="status-badge <?= $u['is_active'] ? 'status-available' : 'status-inactive' ?> text-xs">
                            <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                        </span>
                    </td>
                    <td class="text-xs text-slate-400"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="flex items-center gap-2">
                            <?php if ($u['id'] !== $user['id']): ?>
                            <a href="?toggle=<?= $u['id'] ?><?= $roleFilter ? "&role=$roleFilter" : '' ?>"
                               onclick="return confirm('<?= $u['is_active'] ? 'Suspend' : 'Activate' ?> this user?')"
                               class="<?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?> text-xs py-1.5 px-3">
                                <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                                <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">(You)</span>
                            <?php endif; ?>
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

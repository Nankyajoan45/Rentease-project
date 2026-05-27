<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['admin']);
$user = currentUser();
$pageTitle = 'Admin Dashboard';
$db = getDB();

// Stats
$stats = [
    'users'      => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'tenants'    => $db->query("SELECT COUNT(*) FROM users WHERE role='tenant'")->fetchColumn(),
    'landlords'  => $db->query("SELECT COUNT(*) FROM users WHERE role IN('landlord','agent')")->fetchColumn(),
    'properties' => $db->query("SELECT COUNT(*) FROM properties")->fetchColumn(),
    'available'  => $db->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn(),
    'occupied'   => $db->query("SELECT COUNT(*) FROM properties WHERE status='occupied'")->fetchColumn(),
    'rentals'    => $db->query("SELECT COUNT(*) FROM rentals WHERE status='active'")->fetchColumn(),
    'complaints' => $db->query("SELECT COUNT(*) FROM complaints WHERE status IN('open','in_progress')")->fetchColumn(),
];

// Recent users
$recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Recent properties
$recentProps = $db->query("
    SELECT p.*, u.name AS owner_name,
           (SELECT image_path FROM property_images WHERE property_id=p.id AND is_primary=1 LIMIT 1) AS image
    FROM properties p JOIN users u ON u.id=p.owner_id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

// Open complaints
$openComplaints = $db->query("
    SELECT c.*, p.title AS property_title, u.name AS tenant_name, l.name AS landlord_name
    FROM complaints c
    JOIN properties p ON p.id=c.property_id
    JOIN users u ON u.id=c.tenant_id
    JOIN users l ON l.id=c.landlord_id
    WHERE c.status IN('open','in_progress')
    ORDER BY c.created_at DESC LIMIT 6
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl text-white">Admin Dashboard</h1>
            <p class="text-blue-200 mt-1">System overview — <?= date('d M Y') ?></p>
        </div>
        <div class="flex gap-2">
            <a href="<?= APP_URL ?>/pages/admin/reports.php" class="btn-accent text-sm py-2.5">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php
        $cards = [
            ['Total Users',      $stats['users'],      'fas fa-users',           'bg-blue-100  text-blue-700'],
            ['Tenants',          $stats['tenants'],    'fas fa-user',            'bg-indigo-100 text-indigo-700'],
            ['Landlords/Agents', $stats['landlords'],  'fas fa-building',        'bg-purple-100 text-purple-700'],
            ['Total Properties', $stats['properties'], 'fas fa-home',            'bg-cyan-100  text-cyan-700'],
            ['Available',        $stats['available'],  'fas fa-check-circle',    'bg-green-100 text-green-700'],
            ['Occupied',         $stats['occupied'],   'fas fa-door-open',       'bg-yellow-100 text-yellow-700'],
            ['Active Rentals',   $stats['rentals'],    'fas fa-key',             'bg-orange-100 text-orange-700'],
            ['Open Complaints',  $stats['complaints'], 'fas fa-exclamation-circle','bg-red-100 text-red-700'],
        ];
        foreach ($cards as [$label,$val,$icon,$color]): ?>
        <div class="stat-card">
            <div class="stat-icon <?= $color ?> mb-3"><i class="<?= $icon ?>"></i></div>
            <div class="text-2xl font-bold text-slate-800"><?= number_format($val) ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Admin Links -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
        <?php $adminLinks = [
            [APP_URL.'/pages/admin/users.php',      'fas fa-users-cog',   'Manage Users',      'from-blue-500 to-blue-700'],
            [APP_URL.'/pages/admin/properties.php', 'fas fa-home',        'All Properties',    'from-purple-500 to-purple-700'],
            [APP_URL.'/pages/admin/complaints.php', 'fas fa-flag',        'Complaints',        'from-red-500 to-red-700'],
            [APP_URL.'/pages/admin/reports.php',    'fas fa-chart-bar',   'Reports',           'from-orange-500 to-orange-700'],
        ];
        foreach ($adminLinks as [$href,$icon,$label,$grad]): ?>
        <a href="<?= $href ?>" class="bg-gradient-to-br <?= $grad ?> text-white rounded-2xl p-5 hover:opacity-90 transition-opacity shadow-md text-center">
            <i class="<?= $icon ?> text-2xl mb-2 block"></i>
            <span class="font-semibold text-sm"><?= $label ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">

        <!-- Recent Users -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800">Recent Users</h2>
                <a href="<?= APP_URL ?>/pages/admin/users.php" class="text-primary-600 text-sm hover:underline">View all</a>
            </div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentUsers as $u): ?>
                <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                        <?= strtoupper(substr($u['name'],0,1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate"><?= sanitize($u['name']) ?></p>
                        <p class="text-xs text-slate-500 truncate"><?= sanitize($u['email']) ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium capitalize
                            <?= $u['role']==='admin'?'bg-purple-100 text-purple-700':($u['role']==='landlord'||$u['role']==='agent'?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700') ?>">
                            <?= $u['role'] ?>
                        </span>
                        <span class="text-xs text-slate-400"><?= timeAgo($u['created_at']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Open Complaints -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800">Open Complaints</h2>
                <a href="<?= APP_URL ?>/pages/admin/complaints.php" class="text-primary-600 text-sm hover:underline">View all</a>
            </div>
            <?php if (empty($openComplaints)): ?>
            <div class="p-8 text-center text-slate-400 text-sm">
                <i class="fas fa-smile text-3xl mb-2 block opacity-30"></i>
                No open complaints
            </div>
            <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($openComplaints as $c): ?>
                <div class="px-5 py-3 hover:bg-slate-50 transition-colors">
                    <div class="flex items-start justify-between gap-2 mb-0.5">
                        <p class="text-sm font-medium text-slate-800"><?= sanitize($c['title']) ?></p>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $c['priority']==='urgent'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700' ?>">
                            <?= ucfirst($c['priority']) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500"><?= sanitize($c['tenant_name']) ?> → <?= sanitize($c['landlord_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= sanitize($c['property_title']) ?> · <?= timeAgo($c['created_at']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Properties -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between p-5 border-b border-slate-100">
                <h2 class="font-semibold text-slate-800">Recent Property Listings</h2>
                <a href="<?= APP_URL ?>/pages/admin/properties.php" class="text-primary-600 text-sm hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Property</th><th>Owner</th><th>Type</th><th>Price</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentProps as $p): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php if ($p['image']): ?>
                                <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                                <?php else: ?>
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-400"><i class="fas fa-home text-sm"></i></div>
                                <?php endif; ?>
                                <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="text-sm font-medium text-primary-700 hover:underline"><?= sanitize($p['title']) ?></a>
                            </div>
                        </td>
                        <td class="text-sm text-slate-600"><?= sanitize($p['owner_name']) ?></td>
                        <td class="text-sm capitalize text-slate-600"><?= $p['property_type'] ?></td>
                        <td class="text-sm font-semibold text-primary-700"><?= formatPrice($p['price']) ?></td>
                        <td><span class="status-badge status-<?= $p['status'] ?> text-xs"><?= ucfirst($p['status']) ?></span></td>
                        <td class="text-xs text-slate-400"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

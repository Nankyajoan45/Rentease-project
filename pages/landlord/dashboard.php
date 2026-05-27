<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord', 'agent', 'admin']);
$user = currentUser();
$pageTitle = 'Landlord Dashboard';
$db = getDB();

// Stats
$totalProps = $db->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ?"); $totalProps->execute([$user['id']]); $totalProps = $totalProps->fetchColumn();
$available = $db->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ? AND status = 'available'"); $available->execute([$user['id']]); $available = $available->fetchColumn();
$occupied = $db->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ? AND status = 'occupied'"); $occupied->execute([$user['id']]); $occupied = $occupied->fetchColumn();
$openComplaints = $db->prepare("SELECT COUNT(*) FROM complaints WHERE landlord_id = ? AND status IN ('open','in_progress')"); $openComplaints->execute([$user['id']]); $openComplaints = $openComplaints->fetchColumn();
$totalTenants = $db->prepare("SELECT COUNT(DISTINCT tenant_id) FROM rentals r JOIN properties p ON p.id = r.property_id WHERE p.owner_id = ? AND r.status = 'active'"); $totalTenants->execute([$user['id']]); $totalTenants = $totalTenants->fetchColumn();
$monthlyIncome = $db->prepare("SELECT SUM(monthly_rent) FROM rentals r JOIN properties p ON p.id = r.property_id WHERE p.owner_id = ? AND r.status = 'active'"); $monthlyIncome->execute([$user['id']]); $monthlyIncome = $monthlyIncome->fetchColumn() ?: 0;

// Recent properties
$recentProps = $db->prepare("
    SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM properties p WHERE p.owner_id = ? ORDER BY p.created_at DESC LIMIT 5
");
$recentProps->execute([$user['id']]); $recentProps = $recentProps->fetchAll();

// Recent complaints
$recentComplaints = $db->prepare("
    SELECT c.*, p.title AS property_title, u.name AS tenant_name
    FROM complaints c JOIN properties p ON p.id = c.property_id JOIN users u ON u.id = c.tenant_id
    WHERE c.landlord_id = ? ORDER BY c.created_at DESC LIMIT 5
");
$recentComplaints->execute([$user['id']]); $recentComplaints = $recentComplaints->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-blue-200 text-sm mb-1">Welcome back,</p>
                <h1 class="font-display text-3xl text-white"><?= sanitize($user['name']) ?></h1>
                <p class="text-blue-200 mt-1 text-sm capitalize"><?= $user['role'] ?> Account</p>
            </div>
            <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="btn-accent">
                <i class="fas fa-plus"></i> Add Property
            </a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <?php
        $statCards = [
            ['icon' => 'fas fa-building', 'color' => 'bg-blue-100 text-blue-600', 'value' => $totalProps, 'label' => 'Total Listings'],
            ['icon' => 'fas fa-check-circle', 'color' => 'bg-green-100 text-green-600', 'value' => $available, 'label' => 'Available'],
            ['icon' => 'fas fa-users', 'color' => 'bg-purple-100 text-purple-600', 'value' => $occupied, 'label' => 'Occupied'],
            ['icon' => 'fas fa-user-friends', 'color' => 'bg-indigo-100 text-indigo-600', 'value' => $totalTenants, 'label' => 'Tenants'],
            ['icon' => 'fas fa-exclamation-circle', 'color' => 'bg-red-100 text-red-600', 'value' => $openComplaints, 'label' => 'Open Issues'],
            ['icon' => 'fas fa-wallet', 'color' => 'bg-orange-100 text-orange-600', 'value' => 'UGX ' . number_format($monthlyIncome), 'label' => 'Monthly Income', 'small' => true],
        ];
        foreach ($statCards as $s): ?>
        <div class="stat-card">
            <div class="stat-icon <?= $s['color'] ?> mb-3">
                <i class="<?= $s['icon'] ?>"></i>
            </div>
            <div class="<?= isset($s['small']) ? 'text-base' : 'text-2xl' ?> font-bold text-slate-800"><?= $s['value'] ?></div>
            <div class="text-xs text-slate-500 mt-0.5"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Recent Properties -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">My Listings</h2>
                    <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="text-primary-600 text-sm font-medium hover:underline">View all</a>
                </div>
                <?php if (empty($recentProps)): ?>
                <div class="text-center py-10 text-slate-400">
                    <i class="fas fa-home text-4xl mb-3 opacity-30"></i>
                    <p class="text-sm">No properties listed yet.</p>
                    <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="btn-primary mt-4 text-sm">Add Your First Property</a>
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($recentProps as $p): ?>
                    <div class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors">
                        <?php if ($p['image']): ?>
                        <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" class="w-14 h-14 rounded-xl object-cover flex-shrink-0">
                        <?php else: ?>
                        <div class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center text-blue-400 flex-shrink-0"><i class="fas fa-home"></i></div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-slate-800 text-sm truncate"><?= sanitize($p['title']) ?></p>
                            <p class="text-xs text-slate-500"><?= sanitize($p['location']) ?> · <?= formatPrice($p['price']) ?>/mo</p>
                        </div>
                        <span class="status-badge status-<?= $p['status'] ?> text-xs"><?= ucfirst($p['status']) ?></span>
                        <div class="flex gap-2 ml-2">
                            <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="p-1.5 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-eye text-sm"></i></a>
                            <a href="<?= APP_URL ?>/pages/landlord/edit-property.php?id=<?= $p['id'] ?>" class="p-1.5 text-slate-400 hover:text-primary-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-edit text-sm"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="font-semibold text-slate-800 mb-4">Quick Actions</h2>
                <div class="space-y-2">
                    <?php
                    $actions = [
                        ['href' => APP_URL.'/pages/landlord/add-property.php', 'icon' => 'fas fa-plus-circle', 'color' => 'text-blue-600 bg-blue-50', 'label' => 'Add New Property'],
                        ['href' => APP_URL.'/pages/landlord/tenants.php', 'icon' => 'fas fa-users', 'color' => 'text-purple-600 bg-purple-50', 'label' => 'Manage Tenants'],
                        ['href' => APP_URL.'/pages/landlord/complaints.php', 'icon' => 'fas fa-exclamation-circle', 'color' => 'text-red-500 bg-red-50', 'label' => 'View Complaints' . ($openComplaints > 0 ? " ($openComplaints)" : '')],
                        ['href' => APP_URL.'/pages/messages.php', 'icon' => 'fas fa-envelope', 'color' => 'text-green-600 bg-green-50', 'label' => 'Messages'],
                        ['href' => APP_URL.'/pages/landlord/reports.php', 'icon' => 'fas fa-chart-bar', 'color' => 'text-orange-500 bg-orange-50', 'label' => 'View Reports'],
                    ];
                    foreach ($actions as $a): ?>
                    <a href="<?= $a['href'] ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors group">
                        <div class="w-9 h-9 <?= $a['color'] ?> rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="<?= $a['icon'] ?> text-sm"></i>
                        </div>
                        <span class="text-sm font-medium text-slate-700 group-hover:text-primary-700"><?= $a['label'] ?></span>
                        <i class="fas fa-chevron-right text-xs text-slate-300 ml-auto"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Complaints -->
            <?php if (!empty($recentComplaints)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-slate-800">Recent Issues</h2>
                    <a href="<?= APP_URL ?>/pages/landlord/complaints.php" class="text-xs text-primary-600 font-medium hover:underline">View all</a>
                </div>
                <div class="space-y-3">
                    <?php foreach (array_slice($recentComplaints, 0, 3) as $c): ?>
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-800 leading-tight"><?= sanitize($c['title']) ?></p>
                            <span class="status-badge status-<?= $c['priority'] === 'urgent' || $c['priority'] === 'high' ? 'maintenance' : 'available' ?> text-xs shrink-0">
                                <?= ucfirst($c['priority']) ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1"><?= sanitize($c['tenant_name']) ?> · <?= sanitize($c['property_title']) ?></p>
                        <p class="text-xs text-slate-400 mt-0.5"><?= timeAgo($c['created_at']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

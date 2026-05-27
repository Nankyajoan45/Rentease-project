<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord','agent','admin']);
$user = currentUser();
$pageTitle = 'Reports';
$db = getDB();
$isAdmin = $user['role'] === 'admin';

$ownerClause = $isAdmin ? '' : "AND p.owner_id = {$user['id']}";

// Summary stats
$available  = $db->query("SELECT COUNT(*) FROM properties p WHERE p.status='available' $ownerClause")->fetchColumn();
$occupied   = $db->query("SELECT COUNT(*) FROM properties p WHERE p.status='occupied' $ownerClause")->fetchColumn();
$maint      = $db->query("SELECT COUNT(*) FROM properties p WHERE p.status='maintenance' $ownerClause")->fetchColumn();
$totalProps = (int)$available + (int)$occupied + (int)$maint;

// Tenant list
$tenants = $db->query("
    SELECT r.*, p.title AS property_title, p.location,
           u.name AS tenant_name, u.email AS tenant_email, u.phone AS tenant_phone
    FROM rentals r
    JOIN properties p ON p.id=r.property_id
    JOIN users u ON u.id=r.tenant_id
    WHERE r.status='active' $ownerClause
    ORDER BY r.start_date DESC
")->fetchAll();

// Complaint summary
$compStats = $db->query("
    SELECT status, COUNT(*) AS cnt FROM complaints c
    JOIN properties p ON p.id=c.property_id
    WHERE 1=1 $ownerClause
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Monthly income (landlord)
$income = [];
if (!$isAdmin) {
    $income = $db->query("
        SELECT DATE_FORMAT(pay.payment_date,'%Y-%m') AS month, SUM(pay.amount) AS total
        FROM payments pay
        JOIN rentals r ON r.id=pay.rental_id
        JOIN properties p ON p.id=r.property_id
        WHERE p.owner_id={$user['id']}
        GROUP BY month ORDER BY month DESC LIMIT 12
    ")->fetchAll();
}

// Available property list
$availProps = $db->query("
    SELECT p.*, u.name AS owner_name FROM properties p
    JOIN users u ON u.id=p.owner_id
    WHERE p.status='available' $ownerClause
    ORDER BY p.location, p.price
")->fetchAll();

// Occupied properties
$occupiedProps = $db->query("
    SELECT p.*, u.name AS owner_name,
           (SELECT u2.name FROM rentals r2 JOIN users u2 ON u2.id=r2.tenant_id WHERE r2.property_id=p.id AND r2.status='active' LIMIT 1) AS current_tenant
    FROM properties p JOIN users u ON u.id=p.owner_id
    WHERE p.status='occupied' $ownerClause
    ORDER BY p.location
")->fetchAll();

$reportType = $_GET['report'] ?? 'summary';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <a href="<?= APP_URL ?>/pages/<?= $isAdmin?'admin':'landlord' ?>/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h1 class="font-display text-3xl text-white">Reports & Analytics</h1>
            <p class="text-blue-200 mt-1">Generated on <?= date('d M Y, H:i') ?></p>
        </div>
        <button onclick="window.print()" class="btn-outline border-white/30 text-white hover:bg-white/10 text-sm py-2.5">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Report Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-1">
        <?php foreach ([
            ['summary','fas fa-chart-pie','Summary'],
            ['available','fas fa-check-circle','Available'],
            ['occupied','fas fa-door-open','Occupied'],
            ['tenants','fas fa-users','Tenants'],
            ['complaints','fas fa-flag','Complaints'],
        ] as [$key,$icon,$label]): ?>
        <a href="?report=<?= $key ?>"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap transition-colors
                  <?= $reportType===$key ? 'bg-primary-700 text-white shadow' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' ?>">
            <i class="<?= $icon ?> text-xs"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Summary -->
    <?php if ($reportType === 'summary'): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ([
            ['Total Properties', $totalProps,  'fas fa-home',         'bg-blue-100  text-blue-700'],
            ['Available',        $available,   'fas fa-check-circle', 'bg-green-100 text-green-700'],
            ['Occupied',         $occupied,    'fas fa-door-open',    'bg-yellow-100 text-yellow-700'],
            ['Maintenance',      $maint,       'fas fa-tools',        'bg-red-100   text-red-700'],
        ] as [$label,$val,$icon,$color]): ?>
        <div class="stat-card">
            <div class="stat-icon <?= $color ?> mb-3"><i class="<?= $icon ?>"></i></div>
            <div class="text-3xl font-bold text-slate-800"><?= number_format($val) ?></div>
            <div class="text-sm text-slate-500 mt-1"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Complaint Summary -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Complaint Status Breakdown</h3>
            <?php $statusColors = ['open'=>'bg-red-500','in_progress'=>'bg-yellow-500','resolved'=>'bg-green-500','closed'=>'bg-slate-400'];
            $total = array_sum($compStats) ?: 1;
            foreach ($statusColors as $s => $color): $cnt = $compStats[$s] ?? 0; ?>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-28 text-xs font-medium text-slate-600 capitalize"><?= str_replace('_',' ',$s) ?></div>
                <div class="flex-1 bg-slate-100 rounded-full h-2">
                    <div class="<?= $color ?> h-2 rounded-full" style="width:<?= round($cnt/$total*100) ?>%"></div>
                </div>
                <div class="w-8 text-xs text-right font-bold text-slate-700"><?= $cnt ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Occupancy Pie (text-based) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-semibold text-slate-800 mb-4">Occupancy Overview</h3>
            <?php $occupancyRate = $totalProps > 0 ? round($occupied/$totalProps*100) : 0; ?>
            <div class="flex items-center justify-center mb-4">
                <div class="relative w-32 h-32">
                    <svg viewBox="0 0 36 36" class="w-32 h-32 -rotate-90">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#0f4c81" stroke-width="3"
                                stroke-dasharray="<?= $occupancyRate ?> <?= 100-$occupancyRate ?>"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold text-primary-800"><?= $occupancyRate ?>%</span>
                        <span class="text-xs text-slate-500">Occupied</span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-sm">
                <div><div class="font-bold text-green-600"><?= $available ?></div><div class="text-xs text-slate-500">Available</div></div>
                <div><div class="font-bold text-yellow-600"><?= $occupied ?></div><div class="text-xs text-slate-500">Occupied</div></div>
                <div><div class="font-bold text-red-500"><?= $maint ?></div><div class="text-xs text-slate-500">Maint.</div></div>
            </div>
        </div>

        <?php if (!$isAdmin && !empty($income)): ?>
        <!-- Income Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:col-span-2">
            <h3 class="font-semibold text-slate-800 mb-4">Monthly Income (Last 12 Months)</h3>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Month</th><th>Income (UGX)</th></tr></thead>
                    <tbody>
                    <?php foreach ($income as $i): ?>
                    <tr>
                        <td><?= date('F Y', strtotime($i['month'].'-01')) ?></td>
                        <td class="font-semibold text-primary-700"><?= number_format($i['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Available Properties -->
    <?php elseif ($reportType === 'available'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Available Properties (<?= count($availProps) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Location</th><th>Type</th><th>Price/Month</th><?= $isAdmin?'<th>Owner</th>':'' ?><th>Rooms</th></tr></thead>
                <tbody>
                <?php foreach ($availProps as $p): ?>
                <tr>
                    <td><a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="text-primary-700 hover:underline text-sm font-medium"><?= sanitize($p['title']) ?></a></td>
                    <td class="text-sm text-slate-600"><?= sanitize($p['location']) ?></td>
                    <td class="text-sm capitalize text-slate-600"><?= $p['property_type'] ?></td>
                    <td class="text-sm font-semibold text-green-700"><?= formatPrice($p['price']) ?></td>
                    <?php if ($isAdmin): ?><td class="text-sm text-slate-600"><?= sanitize($p['owner_name']) ?></td><?php endif; ?>
                    <td class="text-sm text-slate-600"><?= $p['num_rooms'] ?> bed / <?= $p['num_bathrooms'] ?> bath</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($availProps)): ?>
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No available properties</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Occupied Properties -->
    <?php elseif ($reportType === 'occupied'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">Occupied Properties (<?= count($occupiedProps) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Property</th><th>Location</th><th>Type</th><th>Rent</th><th>Current Tenant</th><?= $isAdmin?'<th>Owner</th>':'' ?></tr></thead>
                <tbody>
                <?php foreach ($occupiedProps as $p): ?>
                <tr>
                    <td><a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="text-primary-700 hover:underline text-sm font-medium"><?= sanitize($p['title']) ?></a></td>
                    <td class="text-sm text-slate-600"><?= sanitize($p['location']) ?></td>
                    <td class="text-sm capitalize text-slate-600"><?= $p['property_type'] ?></td>
                    <td class="text-sm font-semibold text-yellow-700"><?= formatPrice($p['price']) ?></td>
                    <td class="text-sm text-slate-600"><?= sanitize($p['current_tenant'] ?? '—') ?></td>
                    <?php if ($isAdmin): ?><td class="text-sm text-slate-600"><?= sanitize($p['owner_name']) ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($occupiedProps)): ?>
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No occupied properties</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tenants -->
    <?php elseif ($reportType === 'tenants'): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">Active Tenants (<?= count($tenants) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Tenant</th><th>Property</th><th>Location</th><th>Monthly Rent</th><th>Since</th><th>End Date</th></tr></thead>
                <tbody>
                <?php foreach ($tenants as $t): ?>
                <tr>
                    <td>
                        <p class="text-sm font-medium text-slate-800"><?= sanitize($t['tenant_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= sanitize($t['tenant_phone']) ?></p>
                        <p class="text-xs text-slate-400"><?= sanitize($t['tenant_email']) ?></p>
                    </td>
                    <td class="text-sm text-slate-700"><?= sanitize($t['property_title']) ?></td>
                    <td class="text-sm text-slate-600"><?= sanitize($t['location']) ?></td>
                    <td class="text-sm font-semibold text-primary-700"><?= formatPrice($t['monthly_rent']) ?></td>
                    <td class="text-sm text-slate-600"><?= date('d M Y', strtotime($t['start_date'])) ?></td>
                    <td class="text-sm text-slate-600"><?= $t['end_date'] ? date('d M Y', strtotime($t['end_date'])) : 'Ongoing' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tenants)): ?>
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No active tenants</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Complaints -->
    <?php elseif ($reportType === 'complaints'): ?>
    <?php
    $allComplaints = $db->query("
        SELECT c.*, p.title AS property_title, u.name AS tenant_name, l.name AS landlord_name
        FROM complaints c
        JOIN properties p ON p.id=c.property_id
        JOIN users u ON u.id=c.tenant_id
        JOIN users l ON l.id=c.landlord_id
        WHERE 1=1 $ownerClause
        ORDER BY c.created_at DESC
    ")->fetchAll();
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800">All Complaints (<?= count($allComplaints) ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Property</th><th>Tenant</th><th>Type</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($allComplaints as $c): ?>
                <tr>
                    <td class="text-sm font-medium text-slate-800"><?= sanitize($c['title']) ?></td>
                    <td class="text-sm text-slate-600"><?= sanitize($c['property_title']) ?></td>
                    <td class="text-sm text-slate-600"><?= sanitize($c['tenant_name']) ?></td>
                    <td class="text-sm capitalize text-slate-600"><?= sanitize($c['type']) ?></td>
                    <td>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $c['priority']==='urgent'?'bg-red-100 text-red-700':($c['priority']==='high'?'bg-orange-100 text-orange-700':'bg-slate-100 text-slate-600') ?>">
                            <?= ucfirst($c['priority']) ?>
                        </span>
                    </td>
                    <td><span class="status-badge <?= $c['status']==='resolved'||$c['status']==='closed'?'status-available':($c['status']==='in_progress'?'status-occupied':'status-maintenance') ?> text-xs"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
                    <td class="text-xs text-slate-400"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allComplaints)): ?>
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No complaints</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .navbar, footer, .mobile-bottom-nav, .page-hero { display: none !important; }
    body { background: white; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

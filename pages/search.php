<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Search Properties';

$db = getDB();

// Filters
$location = trim($_GET['location'] ?? '');
$type = $_GET['type'] ?? '';
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';
$view = $_GET['view'] ?? 'grid';

$where = "WHERE p.status IN ('available','booked')";
$params = [];
if ($location) { $where .= " AND (p.location LIKE ? OR p.address LIKE ? OR p.city LIKE ? OR p.title LIKE ?)"; $params = array_merge($params, ["%$location%","%$location%","%$location%","%$location%"]); }
if ($type) { $where .= " AND p.property_type = ?"; $params[] = $type; }
if ($minPrice > 0) { $where .= " AND p.price >= ?"; $params[] = $minPrice; }
if ($maxPrice > 0) { $where .= " AND p.price <= ?"; $params[] = $maxPrice; }

$order = match($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular' => 'p.views DESC',
    default => 'p.created_at DESC'
};

$stmt = $db->prepare("
    SELECT p.*, u.name AS owner_name,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM properties p JOIN users u ON u.id = p.owner_id
    $where ORDER BY $order LIMIT 50
");
$stmt->execute($params);
$properties = $stmt->fetchAll();
$count = count($properties);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <h1 class="font-display text-3xl text-white mb-2">Find Your Next Home</h1>
        <p class="text-blue-200">Browse <?= $count ?> available properties<?= $location ? " in \"" . sanitize($location) . "\"" : "" ?></p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Filters -->
    <form id="searchForm" action="" method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <div class="col-span-2 md:col-span-1">
                <label class="form-label text-xs">Location</label>
                <div class="relative">
                    <i class="fas fa-map-marker-alt absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="location" value="<?= sanitize($location) ?>" class="form-input pl-9 py-2.5 text-sm" placeholder="City, area...">
                </div>
            </div>
            <div>
                <label class="form-label text-xs">Type</label>
                <select name="type" class="form-select py-2.5 text-sm">
                    <option value="">All Types</option>
                    <?php foreach (['room','apartment','house','hostel','studio'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label text-xs">Min Price (UGX)</label>
                <input type="number" name="min_price" value="<?= $minPrice ?: '' ?>" class="form-input py-2.5 text-sm" placeholder="0">
            </div>
            <div>
                <label class="form-label text-xs">Max Price (UGX)</label>
                <input type="number" name="max_price" value="<?= $maxPrice ?: '' ?>" class="form-input py-2.5 text-sm" placeholder="Any">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary py-2.5 flex-1 justify-center text-sm">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($location || $type || $minPrice || $maxPrice): ?>
                <a href="<?= APP_URL ?>/pages/search.php" class="btn-outline py-2.5 px-3 text-sm"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sort & View toggle -->
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-500"><?= $count ?> results</span>
                <select name="sort" class="form-select py-1.5 text-xs w-auto" onchange="this.form.submit()">
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest First</option>
                    <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                    <option value="popular" <?= $sort==='popular'?'selected':'' ?>>Most Popular</option>
                </select>
            </div>
            <div class="flex gap-1">
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>" class="view-btn <?= $view==='grid'?'active':'' ?>"><i class="fas fa-th"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'list'])) ?>" class="view-btn <?= $view==='list'?'active':'' ?>"><i class="fas fa-list"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'map'])) ?>" class="view-btn <?= $view==='map'?'active':'' ?>"><i class="fas fa-map"></i></a>
            </div>
        </div>
    </form>

    <!-- Map View - Leaflet -->
<?php if ($view === 'map'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div id="propertyMap" style="height: 500px;" class="rounded-2xl border border-slate-200 shadow-sm mb-6 z-0"></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('propertyMap').setView([0.3476, 32.5825], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    fetch('<?= APP_URL ?>/api/properties.php?action=map_data')
        .then(r => r.json())
        .then(data => {
            if (!data.markers) return;
            data.markers.forEach(p => {
                if (!p.latitude || !p.longitude) return;
                const marker = L.marker([parseFloat(p.latitude), parseFloat(p.longitude)]);
                marker.bindPopup(`
                    <div style="font-family:sans-serif;min-width:160px">
                        <strong style="color:#0f4c81">${p.title}</strong><br>
                        <span style="color:#64748b;font-size:12px">${p.location}</span><br>
                        <strong style="color:#f97316">UGX ${parseInt(p.price).toLocaleString()}/mo</strong><br>
                        <a href="<?= APP_URL ?>/pages/property.php?id=${p.id}" style="color:#0f4c81;font-size:12px">View Details →</a>
                    </div>
                `);
                marker.addTo(map);
            });
        });
});
</script>
<?php endif; ?>

    <!-- Results -->
    <?php if (empty($properties)): ?>
    <div class="text-center py-20">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fas fa-home text-3xl text-slate-300"></i>
        </div>
        <h3 class="font-semibold text-slate-700 text-lg mb-2">No properties found</h3>
        <p class="text-slate-400 text-sm mb-6">Try adjusting your search filters</p>
        <a href="<?= APP_URL ?>/pages/search.php" class="btn-primary">Clear Filters</a>
    </div>
    <?php elseif ($view === 'list'): ?>
    <div class="space-y-4">
        <?php foreach ($properties as $p): ?>
        <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="property-card flex overflow-hidden group">
            <?php if ($p['image']): ?>
                <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" alt="" class="w-36 sm:w-48 h-36 sm:h-48 object-cover flex-shrink-0 group-hover:scale-105 transition-transform duration-500">
            <?php else: ?>
                <div class="w-36 sm:w-48 h-36 flex-shrink-0 bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-300 text-3xl"><i class="fas fa-home"></i></div>
            <?php endif; ?>
            <div class="p-4 flex-1 flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h3 class="font-semibold text-slate-800 text-sm leading-tight flex-1"><?= sanitize($p['title']) ?></h3>
                        <span class="status-badge status-<?= $p['status'] ?> text-xs shrink-0"><?= ucfirst($p['status']) ?></span>
                    </div>
                    <p class="text-xs text-slate-500 mb-2"><i class="fas fa-map-marker-alt mr-1 text-accent-500"></i><?= sanitize($p['location']) ?> · <span class="capitalize"><?= $p['property_type'] ?></span></p>
                    <p class="text-xs text-slate-600 line-clamp-2 hidden sm:block"><?= sanitize(substr($p['description'] ?? '', 0, 120)) ?>...</p>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <div class="text-primary-800 font-bold text-sm"><?= formatPrice($p['price']) ?><span class="text-slate-400 font-normal">/mo</span></div>
                    <div class="flex gap-3 text-xs text-slate-400">
                        <span><i class="fas fa-bed mr-1"></i><?= $p['num_rooms'] ?></span>
                        <span><i class="fas fa-bath mr-1"></i><?= $p['num_bathrooms'] ?></span>
                        <span><i class="fas fa-eye mr-1"></i><?= $p['views'] ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($properties as $p): ?>
        <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="property-card group">
            <?php if ($p['image']): ?>
                <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" alt="" class="property-card-img group-hover:scale-105 transition-transform duration-500">
            <?php else: ?>
                <div class="property-card-img-placeholder"><i class="fas fa-home"></i></div>
            <?php endif; ?>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <h3 class="font-semibold text-slate-800 text-sm leading-tight flex-1"><?= sanitize($p['title']) ?></h3>
                    <span class="status-badge status-<?= $p['status'] ?> text-xs shrink-0"><?= ucfirst($p['status']) ?></span>
                </div>
                <p class="text-xs text-slate-500 mb-3"><i class="fas fa-map-marker-alt mr-1 text-accent-500"></i><?= sanitize($p['location']) ?> · <span class="capitalize"><?= $p['property_type'] ?></span></p>
                <div class="flex items-center justify-between">
                    <div class="text-primary-800 font-bold text-sm"><?= formatPrice($p['price']) ?><span class="text-slate-400 font-normal">/mo</span></div>
                    <div class="flex gap-3 text-xs text-slate-400">
                        <span><i class="fas fa-bed mr-1"></i><?= $p['num_rooms'] ?></span>
                        <span><i class="fas fa-bath mr-1"></i><?= $p['num_bathrooms'] ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.view-btn { padding: 7px 12px; border-radius: 8px; color: #64748b; border: 1px solid #e2e8f0; text-decoration: none; font-size: 13px; transition: all 0.15s; }
.view-btn:hover, .view-btn.active { background: #0f4c81; color: white; border-color: #0f4c81; }
.line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

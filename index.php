<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Find Your Perfect Home';
$db = getDB();

// Featured properties
$featured = $db->query("
    SELECT p.*, u.name AS owner_name,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM properties p JOIN users u ON u.id = p.owner_id
    WHERE p.status = 'available'
    ORDER BY p.created_at DESC LIMIT 6
")->fetchAll();

// Stats
$stats = [
    'properties' => $db->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn(),
    'landlords' => $db->query("SELECT COUNT(*) FROM users WHERE role IN ('landlord','Custodian')")->fetchColumn(),
    'tenants' => $db->query("SELECT COUNT(*) FROM users WHERE role='tenant'")->fetchColumn(),
    'cities' => $db->query("SELECT COUNT(DISTINCT city) FROM properties WHERE city != ''")->fetchColumn(),
];
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="bg-blue-900 py-20 px-0">
    <div class="max-w-7xl max-auto px-4">
       
            <!-- Search Bar -->
            <form action="<?= APP_URL ?>/pages/search.php" method="GET" class="bg-white rounded-2xl p-2 flex flex-col sm:flex-row gap-2 shadow-2xl max-w-4xl mx-auto">
                <div class="flex-1 flex items-center gap-3 px-4 py-2">
                    <i class="fas fa-map-marker-alt text-orange-500 text-lg"></i>
                    <input type="text" name="location" placeholder="Search by location (e.g. Onzivu, Muni Site...)"
                           class="flex-1 outline-none text-slate-800 text-sm font-medium placeholder:text-slate-400 bg-transparent">
                </div>
                <select name="type" class="px-4 py-3 text-sm text-slate-600 bg-slate-50 rounded-xl outline-none border-0 cursor-pointer">
                    <option value="">All Types</option>
                    <option value="apartment">Apartment</option>
                    <option value="room">Room</option>
                    <option value="house">House</option>
                </select>
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white rounded-xl px-8 py-3 text-sm whitespace-nowrap">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            <!-- Quick buttons --> 
            <div class="flex flex-wrap justify-center gap-4 mt-6">
                <a href="<?= APP_URL ?>/pages/search.php?type=apartment" class="quick-tag"> Apartments</a>
                <a href="<?= APP_URL ?>/pages/search.php?type=room" class="quick-tag"> Room</a>
                <a href="<?= APP_URL ?>/pages/search.php?location=Arua" class="quick-tag"> Arua</a>
                <a href="<?= APP_URL ?>/pages/search.php?type=house" class="quick-tag"> House</a>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <!--<div class="mt-10 bg-white/10 rounded-1xl py-8 px-6 w-full">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-white">
                <div><div class="text-2xl font-bold"><?= number_format($stats['properties']) ?>+</div><div class="text-blue-200 text-xs mt-0.5">Available Properties</div></div>
                <div><div class="text-2xl font-bold"><?= number_format($stats['landlords']) ?>+</div><div class="text-blue-200 text-xs mt-0.5">Verified Landlords</div></div>
                <div><div class="text-2xl font-bold"><?= number_format($stats['tenants']) ?>+</div><div class="text-blue-200 text-xs mt-0.5">Happy Tenants</div></div>
                <div><div class="text-2xl font-bold"><?= number_format($stats['cities']) ?>+</div><div class="text-blue-200 text-xs mt-0.5">Cities Covered</div></div>
            </div> -->
    </div>
</section>

<!-- Featured Properties -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="font-display text-3xl text-slate-800">Featured Properties</h2>
            <p class="text-slate-500 mt-1">Latest listings from verified landlords</p>
        </div>
        <a href="<?= APP_URL ?>/pages/search.php" class="btn-outline hidden sm:flex">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($featured)): ?>
    <div class="text-center py-16 text-slate-400">
        <i class="fas fa-home text-5xl mb-4 opacity-30"></i>
        <p>No properties listed yet. Be the first to list!</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($featured as $p): ?>
        <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="property-card group">
            <?php if ($p['image']): ?>
                <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['title']) ?>" class="property-card-img group-hover:scale-105 transition-transform duration-500">
            <?php else: ?>
                <div class="property-card-img-placeholder">
                    <i class="fas fa-home"></i>
                </div>
            <?php endif; ?>
            <div class="p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <h3 class="font-semibold text-slate-800 text-sm leading-tight line-clamp-2 flex-1"><?= sanitize($p['title']) ?></h3>
                    <span class="status-badge status-<?= $p['status'] ?> shrink-0 text-xs">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-500 text-xs mb-3">
                    <i class="fas fa-map-marker-alt text-accent-500"></i>
                    <span><?= sanitize($p['location']) ?></span>
                    <span class="mx-1">·</span>
                    <span class="capitalize"><?= $p['property_type'] ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="text-primary-800 font-bold text-sm"><?= formatPrice($p['price']) ?><span class="text-slate-400 font-normal">/mo</span></div>
                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <span><i class="fas fa-bed mr-1"></i><?= $p['num_rooms'] ?></span>
                        <span><i class="fas fa-bath mr-1"></i><?= $p['num_bathrooms'] ?></span>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-8 sm:hidden">
        <a href="<?= APP_URL ?>/pages/search.php" class="btn-primary">View All Properties <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php endif; ?>
</section>

<!-- How It Works -->
<section class="bg-gradient-to-br from-slate-50 to-blue-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-12">
            <h2 class="font-display text-3xl text-slate-800 mb-3">How RentEase Works</h2>
            <p class="text-slate-500 max-w-xl mx-auto">Three simple steps to find your next home or list your property</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php
            $steps = [
                ['icon' => 'fas fa-search', 'color' => 'bg-blue-100 text-blue-600', 'num' => '01', 'title' => 'Search Properties', 'desc' => 'Browse thousands of verified listings. Filter by location, price, type, and amenities.'],
                ['icon' => 'fas fa-comments', 'color' => 'bg-orange-100 text-orange-600', 'num' => '02', 'title' => 'Connect & Message', 'desc' => 'Message landlords directly, schedule viewings, and ask questions about any property.'],
                ['icon' => 'fas fa-key', 'color' => 'bg-green-100 text-green-600', 'num' => '03', 'title' => 'Move In', 'desc' => 'Sign your rental agreement, manage payments, and report maintenance — all in one place.'],
            ];
            foreach ($steps as $s): ?>
            <div class="text-center">
                <div class="inline-flex w-16 h-16 <?= $s['color'] ?> rounded-2xl items-center justify-center mb-5 text-2xl">
                    <i class="<?= $s['icon'] ?>"></i>
                </div>
                <div class="text-xs font-bold text-slate-300 mb-2 tracking-widest"><?= $s['num'] ?></div>
                <h3 class="font-semibold text-lg text-slate-800 mb-2"><?= $s['title'] ?></h3>
                <p class="text-slate-500 text-sm leading-relaxed"><?= $s['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
.quick-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 99px;
    color: white;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.2s;
}
.quick-tag:hover { background: rgba(255,255,255,0.2); }
.line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
</style>

<?php include 'includes/footer.php'; ?>

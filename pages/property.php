<?php
require_once __DIR__ . '/../includes/config.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/pages/search.php'); exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, u.name AS owner_name, u.phone AS owner_phone, u.email AS owner_email, u.id AS owner_id
    FROM properties p JOIN users u ON u.id = p.owner_id WHERE p.id = ?
");
$stmt->execute([$id]);
$property = $stmt->fetch();
if (!$property) { header('Location: ' . APP_URL . '/pages/search.php'); exit; }

// Images
$images = $db->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC");
$images->execute([$id]);
$images = $images->fetchAll();

// Increment views
$db->prepare("UPDATE properties SET views = views + 1 WHERE id = ?")->execute([$id]);

// Similar properties
$similar = $db->prepare("
    SELECT p.id, p.title, p.price, p.location, p.property_type,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image
    FROM properties p WHERE p.status = 'available' AND p.id != ? AND p.property_type = ? LIMIT 3
");
$similar->execute([$id, $property['property_type']]);
$similar = $similar->fetchAll();

$pageTitle = $property['title'];
$user = currentUser();
$amenities = $property['amenities'] ? explode(',', $property['amenities']) : [];

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- Breadcrumb -->
    <nav class="text-sm text-slate-500 mb-5 flex items-center gap-2">
        <a href="<?= APP_URL ?>" class="hover:text-primary-700">Home</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <a href="<?= APP_URL ?>/pages/search.php" class="hover:text-primary-700">Properties</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-slate-700 truncate max-w-xs"><?= sanitize($property['title']) ?></span>
    </nav>

    <div class="lg:grid lg:grid-cols-3 lg:gap-8">
        <!-- Left: Details -->
        <div class="lg:col-span-2">
            <!-- Image Gallery -->
            <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-sm mb-6">
                <?php if (!empty($images)): ?>
                <div class="relative">
                    <img id="mainImg" src="<?= APP_URL ?>/<?= sanitize($images[0]['image_path']) ?>" alt="<?= sanitize($property['title']) ?>"
                         class="w-full h-72 md:h-96 object-cover">
                    <?php if (count($images) > 1): ?>
                    <div class="absolute bottom-4 left-4 flex gap-2 overflow-x-auto max-w-sm">
                        <?php foreach ($images as $i => $img): ?>
                        <button onclick="document.getElementById('mainImg').src='<?= APP_URL ?>/<?= sanitize($img['image_path']) ?>'"
                                class="w-16 h-12 rounded-lg overflow-hidden border-2 <?= $i===0?'border-white':'border-white/50 opacity-70 hover:opacity-100' ?> transition-all flex-shrink-0">
                            <img src="<?= APP_URL ?>/<?= sanitize($img['image_path']) ?>" class="w-full h-full object-cover">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="w-full h-72 bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center text-blue-300 text-6xl">
                    <i class="fas fa-home"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Property Info Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h1 class="font-display text-2xl text-slate-900 mb-1"><?= sanitize($property['title']) ?></h1>
                        <div class="flex items-center gap-2 text-slate-500 text-sm">
                            <i class="fas fa-map-marker-alt text-accent-500"></i>
                            <span><?= sanitize($property['address'] ?: $property['location']) ?></span>
                        </div>
                    </div>
                    <span class="status-badge status-<?= $property['status'] ?> text-sm shrink-0"><?= ucfirst($property['status']) ?></span>
                </div>

                <div class="flex items-center gap-6 py-4 border-y border-slate-100 mb-4">
                    <div>
                        <div class="text-primary-800 font-bold text-2xl"><?= formatPrice($property['price']) ?></div>
                        <div class="text-slate-400 text-xs">per month</div>
                    </div>
                    <div class="text-slate-300">|</div>
                    <div class="flex gap-5 text-sm text-slate-600">
                        <div class="text-center"><i class="fas fa-bed text-primary-500 text-lg block mb-0.5"></i><?= $property['num_rooms'] ?> Bed</div>
                        <div class="text-center"><i class="fas fa-bath text-primary-500 text-lg block mb-0.5"></i><?= $property['num_bathrooms'] ?> Bath</div>
                        <div class="text-center"><i class="fas fa-home text-primary-500 text-lg block mb-0.5"></i><span class="capitalize"><?= $property['property_type'] ?></span></div>
                        <div class="text-center"><i class="fas fa-eye text-primary-500 text-lg block mb-0.5"></i><?= $property['views'] ?> Views</div>
                    </div>
                </div>

                <?php if ($property['description']): ?>
                <div class="mb-4">
                    <h3 class="font-semibold text-slate-800 mb-2">Description</h3>
                    <p class="text-slate-600 text-sm leading-relaxed"><?= nl2br(sanitize($property['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($amenities)): ?>
                <div>
                    <h3 class="font-semibold text-slate-800 mb-3">Amenities</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $amenityIcons = ['WiFi'=>'fas fa-wifi','Parking'=>'fas fa-car','Security'=>'fas fa-shield-alt','Water'=>'fas fa-tint','Generator'=>'fas fa-bolt','Garden'=>'fas fa-leaf','Pool'=>'fas fa-swimming-pool','CCTV'=>'fas fa-video'];
                        foreach ($amenities as $a):
                            $a = trim($a);
                            $icon = $amenityIcons[$a] ?? 'fas fa-check-circle';
                        ?>
                        <span class="inline-flex items-center gap-1.5 bg-blue-50 text-primary-700 text-xs font-medium px-3 py-1.5 rounded-full border border-blue-100">
                            <i class="<?= $icon ?> text-xs"></i> <?= sanitize($a) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Location Map placeholder - Leaflet -->
            <?php if ($property['latitude'] && $property['longitude']): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
                    <h3 class="font-semibold text-slate-800 mb-3">Location</h3>
                    <div id="detailMap" style="height:250px" class="rounded-xl z-0"></div>
                </div>

            <!-- Leaflet CSS -->
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
            <!-- Leaflet JS -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

            <script>
                const lat = <?= (float)$property['latitude'] ?>;
                const lng = <?= (float)$property['longitude'] ?>;
                const title = '<?= addslashes(sanitize($property['title'])) ?>';

                const map = L.map('detailMap').setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                L.marker([lat, lng])
                    .addTo(map)
                    .bindPopup('<strong>' + title + '</strong>')
                    .openPopup();
            </script>
        <?php endif; ?>

        <!-- Right: Contact Sidebar -->
        <div class="mt-6 lg:mt-0 space-y-4">
            <!-- Landlord Card -->
            <div class="sidebar">
                <h3 class="font-semibold text-slate-800 mb-4">Listed by</h3>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold text-lg">
                        <?= strtoupper(substr($property['owner_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800"><?= sanitize($property['owner_name']) ?></p>
                        <p class="text-xs text-slate-500">Verified Landlord</p>
                    </div>
                </div>

                <div class="space-y-2 mb-4">
                    <a href="tel:<?= sanitize($property['owner_phone']) ?>" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors">
                        <i class="fas fa-phone text-primary-600 w-4 text-center"></i>
                        <span class="text-sm text-slate-700"><?= sanitize($property['owner_phone'] ?: 'Not provided') ?></span>
                    </a>
                    <a href="mailto:<?= sanitize($property['owner_email']) ?>" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors">
                        <i class="fas fa-envelope text-primary-600 w-4 text-center"></i>
                        <span class="text-sm text-slate-700 truncate"><?= sanitize($property['owner_email']) ?></span>
                    </a>
                </div>

                <?php if ($user && $user['id'] !== $property['owner_id']): ?>
                <button onclick="document.getElementById('msgPanel').classList.toggle('hidden')" class="w-full btn-primary justify-center py-3">
                    <i class="fas fa-envelope"></i> Send Message
                </button>

                <!-- Message Form -->
                <div id="msgPanel" class="hidden mt-4 pt-4 border-t border-slate-100">
                    <textarea id="msgBody" class="form-textarea text-sm mb-3" rows="3" placeholder="Hi, I'm interested in this property..."></textarea>
                    <button onclick="sendMessage()" class="w-full btn-primary justify-center py-2.5 text-sm">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
                <?php elseif (!$user): ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="w-full btn-primary justify-center py-3 block text-center">
                    <i class="fas fa-sign-in-alt"></i> Login to Contact
                </a>
                <?php endif; ?>
            </div>

            <!-- Owner Actions -->
            <?php if ($user && $user['id'] === $property['owner_id']): ?>
            <div class="sidebar">
                <h3 class="font-semibold text-slate-800 mb-3">Manage Property</h3>
                <div class="space-y-2">
                    <a href="<?= APP_URL ?>/pages/landlord/edit-property.php?id=<?= $id ?>" class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors text-sm font-medium text-slate-700">
                        <i class="fas fa-edit text-primary-600 w-4"></i> Edit Listing
                    </a>
                    <a href="<?= APP_URL ?>/pages/landlord/upload-images.php?id=<?= $id ?>" class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors text-sm font-medium text-slate-700">
                        <i class="fas fa-images text-primary-600 w-4"></i> Manage Photos
                    </a>
                    <form method="POST" action="<?= APP_URL ?>/api/properties.php?action=update_status&id=<?= $id ?>" onsubmit="return confirm('Change status?')">
                        <select name="status" onchange="this.form.submit()" class="form-select text-sm py-2.5">
                            <?php foreach (['available','occupied','maintenance','inactive','booked'] as $s): ?>
                            <option value="<?= $s ?>" <?= $property['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Similar Properties -->
            <?php if (!empty($similar)): ?>
            <div class="sidebar">
                <h3 class="font-semibold text-slate-800 mb-3">Similar Properties</h3>
                <div class="space-y-3">
                    <?php foreach ($similar as $s): ?>
                    <a href="<?= APP_URL ?>/pages/property.php?id=<?= $s['id'] ?>" class="flex gap-3 hover:bg-slate-50 p-2 rounded-xl transition-colors group">
                        <?php if ($s['image']): ?>
                        <img src="<?= APP_URL ?>/<?= sanitize($s['image']) ?>" class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
                        <?php else: ?>
                        <div class="w-16 h-16 rounded-lg bg-blue-100 flex items-center justify-center text-blue-300 flex-shrink-0"><i class="fas fa-home"></i></div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm font-medium text-slate-800 group-hover:text-primary-700 transition-colors line-clamp-1"><?= sanitize($s['title']) ?></p>
                            <p class="text-xs text-slate-500"><?= sanitize($s['location']) ?></p>
                            <p class="text-xs font-semibold text-primary-700 mt-0.5"><?= formatPrice($s['price']) ?>/mo</p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
async function sendMessage() {
    const body = document.getElementById('msgBody').value.trim();
    if (!body) return showToast('Please write a message', 'warning');

    const res = await fetch('<?= APP_URL ?>/api/messages.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            receiver_id: <?= (int)$property['owner_id'] ?>,
            property_id: <?= $id ?>,
            subject: 'Inquiry about: <?= addslashes($property['title']) ?>',
            body: body
        })
    });
    const data = await res.json();
    if (data.success) {
        showToast('Message sent successfully!');
        document.getElementById('msgBody').value = '';
        document.getElementById('msgPanel').classList.add('hidden');
    } else {
        showToast(data.error || 'Failed to send message', 'error');
    }
}
</script>

<style>
.line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

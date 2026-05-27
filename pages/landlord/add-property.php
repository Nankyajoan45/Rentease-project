<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord', 'agent', 'admin']);
$user = currentUser();
$pageTitle = 'Add Property';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $required = ['title', 'property_type', 'price', 'location'];
    $missing = array_filter($required, fn($f) => empty($_POST[$f]));

    if ($missing) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missing);
    } else {
        $amenities = array_filter($_POST['amenities'] ?? []);
        $stmt = $db->prepare("
            INSERT INTO properties (owner_id, title, description, property_type, price, location, address, city, latitude, longitude, num_rooms, num_bathrooms, amenities, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'available')
        ");
        $stmt->execute([
            $user['id'],
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            $_POST['property_type'],
            (float)$_POST['price'],
            trim($_POST['location']),
            trim($_POST['address'] ?? ''),
            trim($_POST['city'] ?? ''),
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            (int)($_POST['num_rooms'] ?? 1),
            (int)($_POST['num_bathrooms'] ?? 1),
            implode(',', $amenities),
        ]);
        $newId = $db->lastInsertId();

        // Handle images
        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = UPLOAD_PATH . 'properties/' . $newId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $isPrimary = 1;
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $type = $_FILES['images']['type'][$i];
                if (!in_array($type, ALLOWED_IMAGE_TYPES)) continue;
                $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('img_', true) . '.' . strtolower($ext);
                if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                    $db->prepare("INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?,?,?)")
                       ->execute([$newId, 'uploads/properties/' . $newId . '/' . $filename, $isPrimary]);
                    $isPrimary = 0;
                }
            }
        }
        header('Location: ' . APP_URL . '/pages/landlord/properties.php?success=added');
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-3 transition-colors">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="font-display text-3xl text-white">List a New Property</h1>
        <p class="text-blue-200 mt-1">Fill in the details below to list your property</p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($error): ?><div class="alert alert-error mb-5"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- Basic Info -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                <div class="w-7 h-7 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center text-sm font-bold">1</div>
                Basic Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Property Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="e.g. Modern 2-Bedroom Apartment in Muni site" required value="<?= sanitize($_POST['title'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Property Type *</label>
                    <select name="property_type" class="form-select" required>
                        <option value="">Select type...</option>
                        <?php foreach (['room','apartment','house'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($_POST['property_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Monthly Rent (UGX) *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-medium">UGX</span>
                        <input type="number" name="price" class="form-input pl-12" placeholder="500000" required min="0" value="<?= sanitize($_POST['price'] ?? '') ?>">
                    </div>
                </div>
                <div>
                    <label class="form-label">Number of Rooms</label>
                    <input type="number" name="num_rooms" class="form-input" min="1" max="20" value="<?= sanitize($_POST['num_rooms'] ?? '1') ?>">
                </div>
                <div>
                    <label class="form-label">Number of Bathrooms</label>
                    <input type="number" name="num_bathrooms" class="form-input" min="0" max="10" value="<?= sanitize($_POST['num_bathrooms'] ?? '1') ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="4" placeholder="Describe the property — features, nearby amenities, ideal tenant..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Location -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                <div class="w-7 h-7 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center text-sm font-bold">2</div>
                Location Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Area / Neighbourhood *</label>
                    <input type="text" name="location" class="form-input" placeholder="e.g. Muni Site, Onzivu, Ocholini" required value="<?= sanitize($_POST['location'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-input" placeholder="e.g. Arua" value="<?= sanitize($_POST['city'] ?? '') ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Full Address</label>
                    <input type="text" name="address" class="form-input" placeholder="e.g. Along Muni Girls road" value="<?= sanitize($_POST['address'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Latitude <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="latitude" id="latInput" class="form-input" placeholder="e.g. 0.33600" value="<?= sanitize($_POST['latitude'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Longitude <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input type="text" name="longitude" id="lngInput" class="form-input" placeholder="e.g. 32.59300" value="<?= sanitize($_POST['longitude'] ?? '') ?>">
                </div>
                <div class="md:col-span-2">
                    <button type="button" onclick="getLocation()" class="btn-outline text-sm">
                        <i class="fas fa-crosshairs"></i> Use My Current Location
                    </button>
                </div>
            </div>
        </div>

        <!-- Amenities -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                <div class="w-7 h-7 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center text-sm font-bold">3</div>
                Amenities
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <?php
                $ams = ['WiFi','Parking','Security','Water','Generator','Garden','CCTV','Furnished','Air Conditioning','Balcony','Elevator','Gym'];
                foreach ($ams as $am):
                    $checked = in_array($am, $_POST['amenities'] ?? []);
                ?>
                <label class="amenity-check cursor-pointer">
                    <input type="checkbox" name="amenities[]" value="<?= $am ?>" class="sr-only" <?= $checked?'checked':'' ?> onchange="toggleAmenity(this)">
                    <div class="amenity-btn <?= $checked?'active':'' ?> flex items-center gap-2 p-3 border rounded-xl transition-all text-sm font-medium">
                        <i class="fas fa-check text-xs opacity-0 check-icon"></i>
                        <?= $am ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Photos -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5 flex items-center gap-2">
                <div class="w-7 h-7 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center text-sm font-bold">4</div>
                Photos <span class="text-sm font-normal text-slate-400">(up to 5)</span>
            </h2>
            <div id="uploadZone" class="upload-zone" onclick="document.getElementById('imageInput').click()">
                <i class="fas fa-cloud-upload-alt text-3xl text-slate-300 mb-3"></i>
                <p class="text-slate-600 font-medium">Click to upload or drag & drop</p>
                <p class="text-slate-400 text-xs mt-1">JPG, PNG, WebP — max 5MB each</p>
            </div>
            <input type="file" id="imageInput" name="images[]" multiple accept="image/*" class="hidden">
            <div id="imagePreview" class="grid grid-cols-4 sm:grid-cols-5 gap-2 mt-3"></div>
        </div>

        <!-- Submit -->
        <div class="flex gap-3 justify-end">
            <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="btn-outline">Cancel</a>
            <button type="submit" class="btn-primary py-3 px-8 text-base">
                <i class="fas fa-home"></i> List Property
            </button>
        </div>
    </form>
</div>

<style>
.amenity-btn { border-color: #e2e8f0; color: #64748b; background: #f8fafc; }
.amenity-btn.active { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
.amenity-btn.active .check-icon { opacity: 1 !important; color: #3b82f6; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setupImagePreview('imageInput', 'imagePreview');
    setupDragDrop('uploadZone', 'imageInput');
});
function toggleAmenity(input) {
    const btn = input.parentElement.querySelector('.amenity-btn');
    if (input.checked) btn.classList.add('active');
    else btn.classList.remove('active');
}
function getLocation() {
    if (!navigator.geolocation) return showToast('Geolocation not supported', 'error');
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('latInput').value = pos.coords.latitude.toFixed(6);
        document.getElementById('lngInput').value = pos.coords.longitude.toFixed(6);
        showToast('Location captured!');
    }, () => showToast('Location access denied', 'warning'));
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

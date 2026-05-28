<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord','agent','admin']);
$user = currentUser();
$pageTitle = 'Edit Property';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/pages/landlord/properties.php'); exit; }

$db = getDB();
$property = $db->query("SELECT * FROM properties WHERE id=$id")->fetch();
if (!$property || ($property['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
    header('Location: ' . APP_URL . '/pages/landlord/properties.php?error=unauthorized'); exit;
}

$images = $db->query("SELECT * FROM property_images WHERE property_id=$id ORDER BY is_primary DESC")->fetchAll();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amenities = array_filter($_POST['amenities'] ?? []);
    $stmt = $db->prepare("
        UPDATE properties SET
            title=?, description=?, property_type=?, price=?, location=?, address=?, city=?,
            latitude=?, longitude=?, num_rooms=?, num_bathrooms=?, amenities=?, status=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        trim($_POST['title']),
        trim($_POST['description']??''),
        $_POST['property_type'],
        (float)$_POST['price'],
        trim($_POST['location']),
        trim($_POST['address']??''),
        trim($_POST['city']??''),
        $_POST['latitude']??null,
        $_POST['longitude']??null,
        (int)($_POST['num_rooms']??1),
        (int)($_POST['num_bathrooms']??1),
        implode(',',$amenities),
        $_POST['status']??'available',
        $id,
    ]);

    // New images
    if (!empty($_FILES['new_images']['name'][0])) {
        $uploadDir = UPLOAD_PATH . 'properties/' . $id . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        foreach ($_FILES['new_images']['tmp_name'] as $i => $tmp) {
            if (!$tmp) continue;
            $type = $_FILES['new_images']['type'][$i];
            if (!in_array($type, ALLOWED_IMAGE_TYPES)) continue;
            $ext = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
            $fn  = uniqid('img_',true).'.'.strtolower($ext);
            if (move_uploaded_file($tmp, $uploadDir.$fn)) {
                $hasPrimary = $db->query("SELECT COUNT(*) FROM property_images WHERE property_id=$id AND is_primary=1")->fetchColumn();
                $db->prepare("INSERT INTO property_images (property_id,image_path,is_primary) VALUES (?,?,?)")
                   ->execute([$id, 'uploads/properties/'.$id.'/'.$fn, $hasPrimary?0:1]);
            }
        }
    }

    // Delete images
    foreach ($_POST['delete_images'] ?? [] as $imgId) {
        $img = $db->query("SELECT image_path FROM property_images WHERE id=$imgId AND property_id=$id")->fetch();
        if ($img) {
            $full = __DIR__.'/../../'.$img['image_path'];
            if (file_exists($full)) unlink($full);
            $db->prepare("DELETE FROM property_images WHERE id=?")->execute([$imgId]);
        }
    }

    $success = 'Property updated successfully.';
    $property = $db->query("SELECT * FROM properties WHERE id=$id")->fetch();
    $images   = $db->query("SELECT * FROM property_images WHERE property_id=$id ORDER BY is_primary DESC")->fetchAll();
}

$currentAmenities = $property['amenities'] ? explode(',', $property['amenities']) : [];
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> My Properties</a>
        <h1 class="font-display text-3xl text-white">Edit Property</h1>
        <p class="text-blue-200 mt-1"><?= sanitize($property['title']) ?></p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($success): ?><div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error   mb-5"><i class="fas fa-times-circle"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- Basic Info -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-input" value="<?= sanitize($property['title']) ?>" required>
                </div>
                <div>
                    <label class="form-label">Property Type *</label>
                    <select name="property_type" class="form-select" required>
                        <?php foreach (['rooms','apartment','house'] as $t): ?>
                        <option value="<?= $t ?>" <?= $property['property_type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['available','occupied','maintenance','inactive', 'booked'] as $s): ?>
                        <option value="<?= $s ?>" <?= $property['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Monthly Rent (UGX) *</label>
                    <input type="number" name="price" class="form-input" value="<?= $property['price'] ?>" required>
                </div>
                <div>
                    <label class="form-label">Rooms / Bathrooms</label>
                    <div class="flex gap-2">
                        <input type="number" name="num_rooms" class="form-input" value="<?= $property['num_rooms'] ?>" min="1" placeholder="Rooms">
                        <input type="number" name="num_bathrooms" class="form-input" value="<?= $property['num_bathrooms'] ?>" min="0" placeholder="Baths">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="4"><?= sanitize($property['description']??'') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Location -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <h2 class="font-semibold text-slate-800 mb-5">Location</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Area *</label>
            <input type="text" name="location" class="form-input" value="<?= sanitize($property['location']) ?>" required>
        </div>
        <div>
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-input" value="<?= sanitize($property['city']??'') ?>">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Full Address</label>
            <input type="text" name="address" class="form-input" value="<?= sanitize($property['address']??'') ?>">
        </div>

        <!-- Map Picker -->
        <div class="md:col-span-2">
            <label class="form-label">Pin Location on Map</label>
            <p class="text-xs text-slate-400 mb-2">Click on the map to set the exact property location</p>
            <div id="locationPicker" style="height: 300px;" class="rounded-xl border border-slate-200 z-0"></div>
        </div>

        <div>
            <label class="form-label">Latitude</label>
            <input type="text" name="latitude" id="latInput" class="form-input" 
                   value="<?= $property['latitude']??'' ?>" placeholder="Click map to set" readonly>
        </div>
        <div>
            <label class="form-label">Longitude</label>
            <input type="text" name="longitude" id="lngInput" class="form-input" 
                   value="<?= $property['longitude']??'' ?>" placeholder="Click map to set" readonly>
        </div>
    </div>
</div>

        <!-- Amenities -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5">Amenities</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php foreach (['WiFi','Parking','Security','Water','Generator','Garden','CCTV','Furnished','Air Conditioning','Balcony','Elevator','Gym'] as $am):
                    $checked = in_array($am, $currentAmenities); ?>
                <label class="amenity-check cursor-pointer">
                    <input type="checkbox" name="amenities[]" value="<?= $am ?>" class="sr-only" <?= $checked?'checked':'' ?> onchange="toggleAmenity(this)">
                    <div class="amenity-btn <?= $checked?'active':'' ?> flex items-center gap-2 p-3 border rounded-xl transition-all text-sm font-medium">
                        <i class="fas fa-check text-xs opacity-0 check-icon"></i><?= $am ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Photos -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h2 class="font-semibold text-slate-800 mb-5">Photos</h2>

            <?php if (!empty($images)): ?>
<div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-4">
    <?php foreach ($images as $img): ?>
    <div class="relative group" id="img-<?= $img['id'] ?>">
        <img src="<?= APP_URL ?>/<?= sanitize($img['image_path']) ?>" 
             class="w-full h-20 object-cover rounded-xl border border-slate-200">
        <?php if ($img['is_primary']): ?>
        <span class="absolute top-1 left-1 bg-primary-700 text-white text-xs px-1.5 py-0.5 rounded font-medium">Main</span>
        <?php endif; ?>
        <!-- Always visible delete button -->
        <button type="button"
                onclick="deleteImage(<?= $img['id'] ?>, <?= $id ?>)"
                class="absolute top-1 right-1 bg-red-500 hover:bg-red-600 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center shadow transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endforeach; ?>
</div>
<p class="text-xs text-slate-400 mb-3">Click the <span class="text-red-500 font-bold">✕</span> on a photo to remove it instantly.</p>
<?php endif; ?>

            <div id="uploadZone" class="upload-zone" onclick="document.getElementById('newImageInput').click()">
                <i class="fas fa-cloud-upload-alt text-2xl text-slate-300 mb-2"></i>
                <p class="text-slate-600 font-medium text-sm">Add more photos</p>
            </div>
            <input type="file" id="newImageInput" name="new_images[]" multiple accept="image/*" class="hidden">
            <div id="newImagePreview" class="grid grid-cols-5 gap-2 mt-3"></div>
        </div>

        <div class="flex gap-3 justify-end">
            <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="btn-outline">Cancel</a>
            <button type="submit" class="btn-primary py-3 px-8"><i class="fas fa-save"></i> Save Changes</button>
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
    setupImagePreview('newImageInput','newImagePreview');
    setupDragDrop('uploadZone','newImageInput');
});
function toggleAmenity(input) {
    const btn = input.parentElement.querySelector('.amenity-btn');
    input.checked ? btn.classList.add('active') : btn.classList.remove('active');
}
</script>

<!-- Leaflet Map Picker -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Default to Kampala if no coordinates saved
    const savedLat = <?= !empty($property['latitude'])  ? (float)$property['latitude']  : 2.99625300 ?>;
    const savedLng = <?= !empty($property['longitude']) ? (float)$property['longitude'] : 30.92199200 ?>;

    const map = L.map('locationPicker').setView([savedLat, savedLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Show existing marker if coordinates are saved
    let marker = null;
    <?php if (!empty($property['latitude']) && !empty($property['longitude'])): ?>
    marker = L.marker([savedLat, savedLng], { draggable: true }).addTo(map);
    marker.on('dragend', function (e) {
        document.getElementById('latInput').value = e.target.getLatLng().lat.toFixed(6);
        document.getElementById('lngInput').value = e.target.getLatLng().lng.toFixed(6);
    });
    <?php endif; ?>

    // Click map to place/move marker
    map.on('click', function (e) {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);

        document.getElementById('latInput').value = lat;
        document.getElementById('lngInput').value = lng;

        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                document.getElementById('latInput').value = e.target.getLatLng().lat.toFixed(6);
                document.getElementById('lngInput').value = e.target.getLatLng().lng.toFixed(6);
            });
        }
    });
});

async function deleteImage(imgId, propertyId) {
    if (!confirm('Delete this photo?')) return;
    
    const fd = new FormData();
    fd.append('image_id', imgId);
    
    const res = await fetch('<?= APP_URL ?>/api/upload.php?action=delete', {
        method: 'POST',
        body: fd
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('img-' + imgId).remove();
        showToast('Photo deleted');
    } else {
        showToast(data.error || 'Failed to delete', 'error');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['landlord','agent','admin']);
$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/pages/landlord/properties.php'); exit; }

$db = getDB();
$property = $db->query("SELECT * FROM properties WHERE id=$id")->fetch();
if (!$property || ($property['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
    header('Location: ' . APP_URL . '/pages/landlord/properties.php'); exit;
}

$images = $db->query("SELECT * FROM property_images WHERE property_id=$id ORDER BY is_primary DESC")->fetchAll();
$pageTitle = 'Manage Photos';

// Set primary
if (isset($_GET['set_primary'])) {
    $imgId = (int)$_GET['set_primary'];
    $db->prepare("UPDATE property_images SET is_primary=0 WHERE property_id=?")->execute([$id]);
    $db->prepare("UPDATE property_images SET is_primary=1 WHERE id=? AND property_id=?")->execute([$imgId,$id]);
    header("Location: ?id=$id&msg=primary_set"); exit;
}
// Delete
if (isset($_GET['del_img'])) {
    $imgId = (int)$_GET['del_img'];
    $img = $db->query("SELECT * FROM property_images WHERE id=$imgId AND property_id=$id")->fetch();
    if ($img) {
        $full = __DIR__ . '/../../' . $img['image_path'];
        if (file_exists($full)) unlink($full);
        $db->prepare("DELETE FROM property_images WHERE id=?")->execute([$imgId]);
    }
    header("Location: ?id=$id&msg=deleted"); exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2"><i class="fas fa-arrow-left"></i> My Properties</a>
        <h1 class="font-display text-3xl text-white">Manage Photos</h1>
        <p class="text-blue-200 mt-1"><?= sanitize($property['title']) ?></p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['msg']??false): ?>
    <div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i>
        <?= $_GET['msg']==='primary_set' ? 'Cover photo updated.' : 'Photo deleted.' ?>
    </div>
    <?php endif; ?>

    <!-- Existing Photos -->
<?php if (!empty($images)): ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
    <h2 class="font-semibold text-slate-800 mb-4">Current Photos (<?= count($images) ?>)</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php foreach ($images as $img): ?>
        <div class="relative" id="img-<?= $img['id'] ?>">
            <img src="<?= APP_URL ?>/<?= sanitize($img['image_path']) ?>"
                 class="w-full h-32 object-cover rounded-xl border-2 <?= $img['is_primary']?'border-primary-500':'border-slate-200' ?>">
            <?php if ($img['is_primary']): ?>
            <span class="absolute top-2 left-2 bg-primary-700 text-white text-xs px-2 py-0.5 rounded-full font-medium">Cover</span>
            <?php endif; ?>

            <!-- Always visible action buttons -->
            <div class="absolute bottom-2 left-0 right-0 flex items-center justify-center gap-2">
                <?php if (!$img['is_primary']): ?>
                <a href="?id=<?= $id ?>&set_primary=<?= $img['id'] ?>"
                   class="bg-white text-primary-700 text-xs px-2 py-1 rounded-lg font-medium shadow hover:bg-primary-50 transition-colors">
                   Cover
                </a>
                <?php endif; ?>
                <button type="button"
                        onclick="deleteImage(<?= $img['id'] ?>)"
                        class="bg-red-500 text-white text-xs px-2 py-1 rounded-lg font-medium shadow hover:bg-red-600 transition-colors">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

    <!-- Upload New -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Upload More Photos</h2>
        <div id="uploadZone" class="upload-zone" onclick="document.getElementById('imgInput').click()">
            <i class="fas fa-cloud-upload-alt text-3xl text-slate-300 mb-3"></i>
            <p class="text-slate-600 font-medium">Click or drag photos here</p>
            <p class="text-slate-400 text-xs mt-1">JPG, PNG, WebP — max 5MB each (up to 5 photos)</p>
        </div>
        <input type="file" id="imgInput" accept="image/*" multiple class="hidden">
        <div id="preview" class="grid grid-cols-4 gap-3 mt-4"></div>
        <div id="uploadStatus" class="hidden mt-4"></div>
        <div class="flex gap-3 mt-4">
            <button id="uploadBtn" onclick="uploadPhotos()" class="btn-primary" style="display:none">
                <i class="fas fa-upload"></i> Upload Photos
            </button>
            <a href="<?= APP_URL ?>/pages/property.php?id=<?= $id ?>" class="btn-outline">
                <i class="fas fa-eye"></i> View Listing
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    setupImagePreview('imgInput','preview');
    setupDragDrop('uploadZone','imgInput');
    document.getElementById('imgInput').addEventListener('change', function(){
        document.getElementById('uploadBtn').style.display = this.files.length ? '' : 'none';
    });
});

async function deleteImage(imgId) {
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

async function uploadPhotos() {
    const input = document.getElementById('imgInput');
    if (!input.files.length) return showToast('Select photos first','warning');

    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';

    const fd = new FormData();
    Array.from(input.files).forEach(f => fd.append('images[]', f));
    fd.append('property_id','<?= $id ?>');

    const res = await fetch('<?= APP_URL ?>/api/upload.php?action=upload', { method:'POST', body: fd });
    const data = await res.json();

    btn.disabled = false; btn.innerHTML = '<i class="fas fa-upload"></i> Upload Photos';

    if (data.success) {
        showToast(`${data.uploaded.length} photo(s) uploaded!`);
        setTimeout(() => location.reload(), 1200);
    } else {
        showToast(data.error || 'Upload failed','error');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

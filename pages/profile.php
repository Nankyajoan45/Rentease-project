<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$user = currentUser();
$pageTitle = 'My Profile';
$db = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio   = trim($_POST['bio']   ?? '');
        if (!$name) { $error = 'Name is required.'; }
        else {
            // Avatar upload
            $avatarPath = $user['avatar'];
            if (!empty($_FILES['avatar']['tmp_name'])) {
                $type = $_FILES['avatar']['type'];
                if (in_array($type, ALLOWED_IMAGE_TYPES) && $_FILES['avatar']['size'] <= MAX_UPLOAD_SIZE) {
                    $dir = UPLOAD_PATH . 'avatars/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $fn  = 'avatar_' . $user['id'] . '.' . strtolower($ext);
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir.$fn)) {
                        $avatarPath = 'uploads/avatars/'.$fn;
                    }
                }
            }
            $db->prepare("UPDATE users SET name=?, phone=?, bio=?, avatar=? WHERE id=?")
               ->execute([$name, $phone, $bio, $avatarPath, $user['id']]);
            $success = 'Profile updated successfully.';
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row = $db->query("SELECT password_hash FROM users WHERE id={$user['id']}")->fetch();
        if (!password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $user['id']]);
            $success = 'Password changed successfully.';
        }
    }
    // Refresh user
    $user = currentUser();
}

// Stats
//$propCount  = in_array($user['role'],['landlord','agent'])
    //? $db->prepare("SELECT COUNT(*) FROM properties WHERE owner_id=?")->execute([$user['id']])->fetchColumn(): 0;
//$rentalCount = $db->prepare("SELECT COUNT(*) FROM rentals WHERE owner_id=?")->execute([$user['id']])->fetchColumn();
//$msgCount   = $db->prepare("SELECT COUNT(*) FROM messages WHERE owner_id=?")->execute([$user['id']])->fetchColumn();

// Stats - DEBUG VERSION
$propCount   = 0;
$rentalCount = 0;
$msgCount    = 0;

try {
    if (in_array($user['role'], ['landlord', 'agent'])) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM properties WHERE owner_id = ?");
        $stmt->execute([$user['id']]);
        $propCount = (int) $stmt->fetchColumn();
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM rentals WHERE tenant_id = ?");
    $stmt->execute([$user['id']]);
    $rentalCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
    $stmt->execute([$user['id']]);
    $msgCount = (int) $stmt->fetchColumn();

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());  // ← Will show exact problem
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        <h1 class="font-display text-3xl text-white">My Profile</h1>
        <p class="text-blue-200 mt-1">Manage your account information</p>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($success): ?><div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> <?= sanitize($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error   mb-5"><i class="fas fa-times-circle"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-3 gap-6">

        <!-- Profile Card -->
        <div class="md:col-span-1 space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center">
                <div class="relative inline-block mb-4">
                    <?php if ($user['avatar']): ?>
                    <img src="<?= APP_URL ?>/<?= sanitize($user['avatar']) ?>" class="w-24 h-24 rounded-full object-cover mx-auto border-4 border-white shadow-md">
                    <?php else: ?>
                    <div class="w-24 h-24 rounded-full bg-blue-900 flex items-center justify-center text-white text-3xl font-bold mx-auto border-4 border-white shadow-md">
                        <?= strtoupper(substr($user['name'],0,1)) ?>
                    </div>
                    <?php endif; ?>
                    <label for="quickAvatar" class="absolute bottom-0 right-0 w-8 h-8 bg-primary-600 text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-primary-700 shadow">
                        <i class="fas fa-camera text-xs"></i>
                    </label>
                </div>
                <h2 class="font-bold text-slate-800 text-lg"><?= sanitize($user['name']) ?></h2>
                <p class="text-sm text-slate-500 capitalize mt-0.5"><?= $user['role'] ?></p>
                <p class="text-xs text-slate-400 mt-1"><?= sanitize($user['email']) ?></p>

                <div class="grid grid-cols-3 gap-2 mt-5 pt-5 border-t border-slate-100">
                    <?php foreach ([
                        ['Properties', $propCount,   'fas fa-home'],
                        ['Rentals',    $rentalCount, 'fas fa-key'],
                        ['Messages',   $msgCount,    'fas fa-envelope'],
                    ] as [$label,$val,$icon]): ?>
                    <div>
                        <div class="text-lg font-bold text-primary-800"><?= $val ?></div>
                        <div class="text-xs text-slate-500 leading-tight"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick nav -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <nav class="space-y-1">
                    <?php
                    $dashUrl = match($user['role']) {
                        'landlord','agent' => APP_URL.'/pages/landlord/dashboard.php',
                        'admin'            => APP_URL.'/pages/admin/dashboard.php',
                        default            => APP_URL.'/pages/tenant/dashboard.php',
                    };
                    $links = [
                        [$dashUrl,                                 'fas fa-tachometer-alt','Dashboard'],
                        [APP_URL.'/pages/messages.php',            'fas fa-envelope',      'Messages'],
                        [APP_URL.'/pages/notifications.php',       'fas fa-bell',          'Notifications'],
                        [APP_URL.'/api/auth.php?action=logout',    'fas fa-sign-out-alt',  'Logout',  'text-red-500 hover:bg-red-50'],
                    ];
                    if (in_array($user['role'],['landlord','agent'])) {
                        array_splice($links,1,0,[[APP_URL.'/pages/landlord/properties.php','fas fa-building','My Listings']]);
                    }
                    //foreach ($links as [$href,$icon,$label,$extra = '']): ?>
                    <?php foreach ($links as $link):
                        [$href,$icon,$label] = $link;
                        $extra = $link[3] ?? '';
                    ?>    
                    <a href="<?= $href ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors <?= $extra ?>">
                        <i class="<?= $icon ?> w-4 text-center text-slate-400"></i> <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Edit Forms -->
        <div class="md:col-span-2 space-y-6">

            <!-- Profile Info -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="font-semibold text-slate-800 mb-5">Personal Information</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action"     value="profile">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="file" id="quickAvatar" name="avatar" accept="image/*" class="hidden"
                           onchange="previewAvatar(this)">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" value="<?= sanitize($user['name']) ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input bg-slate-50 cursor-not-allowed" value="<?= sanitize($user['email']) ?>" disabled>
                            <p class="text-xs text-slate-400 mt-1">Email cannot be changed</p>
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" value="<?= sanitize($user['phone']??'') ?>" placeholder="+256 7XX XXX XXX">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Bio <span class="text-slate-400 font-normal">(optional)</span></label>
                            <textarea name="bio" class="form-textarea" rows="3" placeholder="A brief description about yourself…"><?= sanitize($user['bio']??'') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary py-2.5">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="font-semibold text-slate-800 mb-5">Change Password</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action"     value="password">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div>
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required placeholder="••••••••">
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" required placeholder="Min 8 chars" minlength="8">
                        </div>
                        <div>
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required placeholder="Repeat password">
                        </div>
                    </div>
                    <button type="submit" class="btn-outline py-2.5">
                        <i class="fas fa-lock"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const imgs = document.querySelectorAll('.profile-avatar-img');
        document.querySelectorAll('[data-avatar]').forEach(el => el.src = e.target.result);
        // Replace initials div with img if present
        const initials = document.querySelector('.w-24.h-24.rounded-full.bg-blue-900');
        if (initials) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'w-24 h-24 rounded-full object-cover mx-auto border-4 border-white shadow-md';
            initials.replaceWith(img);
        }
        showToast('Photo selected — save to apply');
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

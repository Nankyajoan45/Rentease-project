<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['tenant']);
$user = currentUser();
$pageTitle = 'My Dashboard';

$db = getDB();

// Active rental
$rental = $db->prepare("
    SELECT r.*, p.title AS property_title, p.location, p.address, p.property_type, p.price,
           u.name AS landlord_name, u.phone AS landlord_phone, u.email AS landlord_email, u.id AS landlord_id,
           (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS property_image
    FROM rentals r
    JOIN properties p ON p.id = r.property_id
    JOIN users u ON u.id = p.owner_id
    WHERE r.tenant_id = ? AND r.status = 'active'
    ORDER BY r.start_date DESC LIMIT 1
");
$rental->execute([$user['id']]); $rental = $rental->fetch();

// My complaints
$complaints = $db->prepare("
    SELECT c.*, p.title AS property_title FROM complaints c JOIN properties p ON p.id = c.property_id
    WHERE c.tenant_id = ? ORDER BY c.created_at DESC LIMIT 5
"); $complaints->execute([$user['id']]); $complaints = $complaints->fetchAll();

// Messages
$messages = $db->prepare("
    SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT 5
"); $messages->execute([$user['id']]); $messages = $messages->fetchAll();

// Saved searches / bookmarked properties
$nearby = $db->query("SELECT p.*, (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image FROM properties p WHERE p.status = 'available' ORDER BY RAND() LIMIT 4")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <p class="text-blue-200 text-sm mb-1">Welcome,</p>
        <h1 class="font-display text-3xl text-white"><?= sanitize($user['name']) ?></h1>
        <p class="text-blue-200 mt-1 text-sm">Tenant Account</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Current Rental -->
            <?php if ($rental): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-primary-800 to-primary-600 p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-xs mb-1">Current Rental</p>
                            <h2 class="font-semibold text-lg"><?= sanitize($rental['property_title']) ?></h2>
                            <p class="text-blue-200 text-sm mt-0.5"><i class="fas fa-map-marker-alt mr-1"></i><?= sanitize($rental['location']) ?></p>
                        </div>
                        <span class="status-badge status-available text-xs">Active</span>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-3 gap-4 mb-5">
                        <div class="text-center">
                            <div class="text-lg font-bold text-primary-800"><?= formatPrice($rental['monthly_rent']) ?></div>
                            <div class="text-xs text-slate-500">Monthly Rent</div>
                        </div>
                        <div class="text-center border-x border-slate-100">
                            <div class="text-lg font-bold text-slate-800"><?= date('d M Y', strtotime($rental['start_date'])) ?></div>
                            <div class="text-xs text-slate-500">Move-in Date</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-slate-800"><?= $rental['end_date'] ? date('d M Y', strtotime($rental['end_date'])) : 'Ongoing' ?></div>
                            <div class="text-xs text-slate-500">End Date</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($rental['landlord_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-sm text-slate-800"><?= sanitize($rental['landlord_name']) ?></p>
                            <p class="text-xs text-slate-500">Landlord · <?= sanitize($rental['landlord_phone']) ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="tel:<?= sanitize($rental['landlord_phone']) ?>" class="p-2 bg-green-100 text-green-600 rounded-lg hover:bg-green-200 transition-colors">
                                <i class="fas fa-phone text-sm"></i>
                            </a>
                            <button onclick="showMsgModal(<?= $rental['landlord_id'] ?>)" class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 transition-colors">
                                <i class="fas fa-envelope text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-primary-600 text-xl"></i>
                </div>
                <h3 class="font-semibold text-slate-800 mb-2">Looking for a Home?</h3>
                <p class="text-slate-500 text-sm mb-4">Browse available properties in your preferred location</p>
                <a href="<?= APP_URL ?>/pages/search.php" class="btn-primary">Browse Properties</a>
            </div>
            <?php endif; ?>

            <!-- Recent Complaints -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between p-5 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">My Complaints & Requests</h2>
                    <a href="<?= APP_URL ?>/pages/tenant/complaints.php" class="text-primary-600 text-sm font-medium hover:underline">View all</a>
                </div>
                <?php if (empty($complaints)): ?>
                <div class="p-6 text-center text-slate-400 text-sm">
                    <i class="fas fa-check-circle text-green-400 text-2xl mb-2 block"></i>
                    No complaints submitted yet.
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($complaints as $c): ?>
                    <div class="p-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800"><?= sanitize($c['title']) ?></p>
                            <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($c['property_title']) ?> · <?= timeAgo($c['created_at']) ?></p>
                        </div>
                        <span class="status-badge <?= $c['status'] === 'resolved' ? 'status-available' : ($c['status'] === 'in_progress' ? 'status-occupied' : 'status-maintenance') ?> text-xs shrink-0">
                            <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
            <!-- Quick Actions -->
            <div class="sidebar">
                <h3 class="font-semibold text-slate-800 mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <?php
                    $actions = [
                        ['href' => APP_URL.'/pages/search.php', 'icon' => 'fas fa-search', 'label' => 'Find a Property', 'color' => 'text-blue-600 bg-blue-50'],
                        ['href' => APP_URL.'/pages/messages.php', 'icon' => 'fas fa-envelope', 'label' => 'Messages', 'color' => 'text-green-600 bg-green-50'],
                        ['href' => APP_URL.'/pages/tenant/complaints.php', 'icon' => 'fas fa-exclamation-circle', 'label' => 'Report Issue', 'color' => 'text-red-500 bg-red-50'],
                        ['href' => APP_URL.'/pages/profile.php', 'icon' => 'fas fa-user-cog', 'label' => 'My Profile', 'color' => 'text-purple-600 bg-purple-50'],
                    ];
                    foreach ($actions as $a): ?>
                    <a href="<?= $a['href'] ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
                        <div class="w-9 h-9 <?= $a['color'] ?> rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="<?= $a['icon'] ?> text-sm"></i>
                        </div>
                        <span class="text-sm font-medium text-slate-700"><?= $a['label'] ?></span>
                        <i class="fas fa-chevron-right text-xs text-slate-300 ml-auto"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="sidebar">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-slate-800">Messages</h3>
                    <a href="<?= APP_URL ?>/pages/messages.php" class="text-xs text-primary-600 hover:underline">View all</a>
                </div>
                <?php if (empty($messages)): ?>
                <p class="text-sm text-slate-400 text-center py-4">No messages yet</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach (array_slice($messages, 0, 3) as $m): ?>
                    <a href="<?= APP_URL ?>/pages/messages.php" class="flex items-start gap-3 p-2 rounded-xl hover:bg-slate-50 transition-colors">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                            <?= strtoupper(substr($m['sender_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-xs font-semibold text-slate-800"><?= sanitize($m['sender_name']) ?></p>
                                <?php if (!$m['is_read']): ?><span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span><?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-500 truncate"><?= sanitize(substr($m['body'], 0, 60)) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Available Properties -->
    <div class="mt-8">
        <div class="flex items-center justify-between mb-5">
            <h2 class="font-semibold text-slate-800 text-lg">Available Properties</h2>
            <a href="<?= APP_URL ?>/pages/search.php" class="text-primary-600 text-sm hover:underline">Browse all</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($nearby as $p): ?>
            <a href="<?= APP_URL ?>/pages/property.php?id=<?= $p['id'] ?>" class="property-card">
                <?php if ($p['image']): ?>
                    <img src="<?= APP_URL ?>/<?= sanitize($p['image']) ?>" class="property-card-img">
                <?php else: ?>
                    <div class="property-card-img-placeholder"><i class="fas fa-home"></i></div>
                <?php endif; ?>
                <div class="p-4">
                    <p class="font-medium text-slate-800 text-sm mb-1"><?= sanitize($p['title']) ?></p>
                    <p class="text-xs text-slate-500 mb-2"><i class="fas fa-map-marker-alt mr-1 text-accent-500"></i><?= sanitize($p['location']) ?></p>
                    <p class="text-primary-700 font-bold text-sm"><?= formatPrice($p['price']) ?>/mo</p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Message Modal -->
<div id="msgModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl">
        <h3 class="font-semibold text-slate-800 mb-4">Send Message</h3>
        <input type="hidden" id="msgReceiver" value="">
        <textarea id="msgText" class="form-textarea mb-3" rows="4" placeholder="Type your message..."></textarea>
        <div class="flex gap-3">
            <button onclick="document.getElementById('msgModal').classList.add('hidden')" class="flex-1 btn-outline py-2.5 text-sm">Cancel</button>
            <button onclick="sendMsg()" class="flex-1 btn-primary justify-center py-2.5 text-sm">Send</button>
        </div>
    </div>
</div>

<script>
function showMsgModal(receiverId) {
    document.getElementById('msgReceiver').value = receiverId;
    document.getElementById('msgModal').classList.remove('hidden');
}
async function sendMsg() {
    const receiverId = document.getElementById('msgReceiver').value;
    const body = document.getElementById('msgText').value.trim();
    if (!body) return showToast('Please enter a message', 'warning');
    const res = await fetch('<?= APP_URL ?>/api/messages.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receiver_id: parseInt(receiverId), body, subject: 'Message from tenant' })
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('msgModal').classList.add('hidden');
        document.getElementById('msgText').value = '';
        showToast('Message sent!');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

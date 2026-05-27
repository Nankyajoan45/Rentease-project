<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['tenant']);
$user = currentUser();
$pageTitle = 'Complaints & Maintenance';

$db = getDB();

// Get tenant's rented properties
$myProperties = $db->prepare("
    SELECT p.id, p.title, p.location FROM rentals r
    JOIN properties p ON p.id = r.property_id
    WHERE r.tenant_id = ? AND r.status = 'active'
");
$myProperties->execute([$user['id']]); $myProperties = $myProperties->fetchAll();

// Also allow non-active rentals
if (empty($myProperties)) {
    $myProperties = $db->prepare("
        SELECT DISTINCT p.id, p.title, p.location FROM rentals r
        JOIN properties p ON p.id = r.property_id WHERE r.tenant_id = ?
    ");
    $myProperties->execute([$user['id']]); $myProperties = $myProperties->fetchAll();
}

// Get all complaints
$complaints = $db->prepare("
    SELECT c.*, p.title AS property_title
    FROM complaints c JOIN properties p ON p.id = c.property_id
    WHERE c.tenant_id = ? ORDER BY c.created_at DESC
");
$complaints->execute([$user['id']]); $complaints = $complaints->fetchAll();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $propertyId = (int)($_POST['property_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'complaint';
    $priority = $_POST['priority'] ?? 'medium';

    if (!$propertyId || !$title || !$description) {
        $formError = 'Please fill in all required fields.';
    } else {
        $prop = $db->query("SELECT owner_id FROM properties WHERE id = $propertyId")->fetch();
        if ($prop) {
            $db->prepare("INSERT INTO complaints (tenant_id, property_id, landlord_id, type, title, description, priority) VALUES (?,?,?,?,?,?,?)")
               ->execute([$user['id'], $propertyId, $prop['owner_id'], $type, $title, $description, $priority]);
            createNotification($prop['owner_id'], 'complaint', 'New complaint: '.$title, $description);
            header('Location: ' . APP_URL . '/pages/tenant/complaints.php?submitted=1');
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <a href="<?= APP_URL ?>/pages/tenant/dashboard.php" class="inline-flex items-center gap-2 text-blue-200 hover:text-white text-sm mb-2">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <h1 class="font-display text-3xl text-white">Complaints & Maintenance</h1>
        <p class="text-blue-200 mt-1">Report issues with your rental property</p>
    </div>
</div>

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
    <?php if ($_GET['submitted'] ?? false): ?>
    <div class="alert alert-success mb-5"><i class="fas fa-check-circle"></i> Your complaint has been submitted. The landlord will be notified.</div>
    <?php endif; ?>

    <div class="grid md:grid-cols-5 gap-6">
        <!-- Form -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-semibold text-slate-800 mb-5">Submit a Report</h2>

                <?php if (empty($myProperties)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    You need an active rental to submit a complaint. <a href="<?= APP_URL ?>/pages/search.php" class="underline font-medium">Find a property</a>
                </div>
                <?php else: ?>

                <?php if (isset($formError)): ?>
                <div class="alert alert-error mb-4"><i class="fas fa-exclamation-circle"></i> <?= sanitize($formError) ?></div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div>
                        <label class="form-label">Property *</label>
                        <select name="property_id" class="form-select" required>
                            <option value="">Select property...</option>
                            <?php foreach ($myProperties as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= sanitize($p['title']) ?> — <?= sanitize($p['location']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Report Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="maintenance">🔧 Maintenance Request</option>
                            <option value="complaint">⚠️ Complaint</option>
                            <option value="damage">💥 Property Damage</option>
                            <option value="noise">🔊 Noise Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">🚨 Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-input" placeholder="Brief summary" required>
                    </div>
                    <div>
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-textarea" rows="5" placeholder="Describe the issue in detail..." required></textarea>
                    </div>
                    <button type="submit" class="w-full btn-primary justify-center py-3">
                        <i class="fas fa-paper-plane"></i> Submit Report
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- History -->
        <div class="md:col-span-3">
            <h2 class="font-semibold text-slate-800 mb-4">Submission History</h2>
            <?php if (empty($complaints)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-10 text-center text-slate-400">
                <i class="fas fa-inbox text-4xl mb-3 opacity-30 block"></i>
                No reports submitted yet.
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($complaints as $c):
                    $statusClass = match($c['status']) {
                        'resolved', 'closed' => 'status-available',
                        'in_progress' => 'status-occupied',
                        default => 'status-maintenance'
                    };
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <h3 class="font-semibold text-slate-800 text-sm"><?= sanitize($c['title']) ?></h3>
                            <p class="text-xs text-slate-500 mt-0.5"><?= sanitize($c['property_title']) ?></p>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="status-badge <?= $statusClass ?> text-xs"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
                            <span class="text-xs text-slate-400"><?= timeAgo($c['created_at']) ?></span>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 mb-2"><?= sanitize($c['description']) ?></p>
                    <div class="flex gap-2 flex-wrap">
                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full"><?= ucfirst($c['type']) ?></span>
                        <span class="text-xs <?= $c['priority']==='urgent'?'bg-red-100 text-red-700':($c['priority']==='high'?'bg-orange-100 text-orange-700':'bg-slate-100 text-slate-600') ?> px-2 py-1 rounded-full"><?= ucfirst($c['priority']) ?> Priority</span>
                    </div>
                    <?php if ($c['resolution_notes']): ?>
                    <div class="mt-3 p-3 bg-green-50 rounded-xl border border-green-100 text-xs text-green-700">
                        <i class="fas fa-check-circle mr-1"></i><strong>Landlord response:</strong> <?= sanitize($c['resolution_notes']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

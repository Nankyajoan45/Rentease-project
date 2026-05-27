<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
requireLogin();
$user = currentUser();

if (!in_array($user['role'], ['landlord', 'agent', 'admin'])) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$action = $_GET['action'] ?? 'upload';

if ($action === 'upload') {
    $propertyId = (int)($_POST['property_id'] ?? 0);
    if (!$propertyId) jsonResponse(['error' => 'Property ID required'], 400);

    // Verify ownership
    $db = getDB();
    $prop = $db->query("SELECT owner_id FROM properties WHERE id = $propertyId")->fetch();
    if (!$prop || ($prop['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    if (empty($_FILES['images'])) jsonResponse(['error' => 'No files uploaded'], 400);

    $uploaded = [];
    $uploadDir = UPLOAD_PATH . 'properties/' . $propertyId . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $files = $_FILES['images'];
    $count = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < min($count, 5); $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];

        if (!in_array($type, ALLOWED_IMAGE_TYPES)) continue;
        if ($size > MAX_UPLOAD_SIZE) continue;

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . strtolower($ext);
        $dest = $uploadDir . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            $isPrimary = empty($uploaded) ? 1 : 0;
            // Set first uploaded as primary if no primary exists
            $existing = $db->query("SELECT COUNT(*) FROM property_images WHERE property_id = $propertyId AND is_primary = 1")->fetchColumn();
            if ($existing > 0) $isPrimary = 0;

            $stmt = $db->prepare("INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?,?,?)");
            $relativePath = 'uploads/properties/' . $propertyId . '/' . $filename;
            $stmt->execute([$propertyId, $relativePath, $isPrimary]);
            $uploaded[] = ['id' => $db->lastInsertId(), 'path' => $relativePath];
        }
    }

    jsonResponse(['success' => true, 'uploaded' => $uploaded]);
} elseif ($action === 'delete') {
    $imageId = (int)($_POST['image_id'] ?? 0);
    if (!$imageId) jsonResponse(['error' => 'Image ID required'], 400);

    $db = getDB();
    $img = $db->query("SELECT pi.*, p.owner_id FROM property_images pi JOIN properties p ON p.id = pi.property_id WHERE pi.id = $imageId")->fetch();
    if (!$img || ($img['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $fullPath = __DIR__ . '/../' . $img['image_path'];
    if (file_exists($fullPath)) unlink($fullPath);
    $db->prepare("DELETE FROM property_images WHERE id = ?")->execute([$imageId]);

    jsonResponse(['success' => true]);
}

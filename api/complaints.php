<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
requireLogin();
$user = currentUser();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listComplaints();
        break;
    case 'create':
        createComplaint();
        break;
    case 'update':
        updateComplaint();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function listComplaints(): void {
    global $user;
    $db = getDB();

    if ($user['role'] === 'tenant') {
        $stmt = $db->prepare("
            SELECT c.*, p.title AS property_title, u.name AS landlord_name
            FROM complaints c
            JOIN properties p ON p.id = c.property_id
            JOIN users u ON u.id = c.landlord_id
            WHERE c.tenant_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $db->prepare("
            SELECT c.*, p.title AS property_title, u.name AS tenant_name, u.phone AS tenant_phone
            FROM complaints c
            JOIN properties p ON p.id = c.property_id
            JOIN users u ON u.id = c.tenant_id
            WHERE c.landlord_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    }

    jsonResponse(['complaints' => $stmt->fetchAll()]);
}

function createComplaint(): void {
    global $user;
    if ($user['role'] !== 'tenant') jsonResponse(['error' => 'Only tenants can submit complaints'], 403);

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $propertyId = (int)($data['property_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $type = $data['type'] ?? 'complaint';
    $priority = $data['priority'] ?? 'medium';

    if (!$propertyId || !$title || !$description) jsonResponse(['error' => 'Missing required fields'], 422);

    $db = getDB();
    $prop = $db->query("SELECT owner_id FROM properties WHERE id = $propertyId")->fetch();
    if (!$prop) jsonResponse(['error' => 'Property not found'], 404);

    $stmt = $db->prepare("INSERT INTO complaints (tenant_id, property_id, landlord_id, type, title, description, priority) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$user['id'], $propertyId, $prop['owner_id'], $type, $title, $description, $priority]);

    createNotification($prop['owner_id'], 'complaint', 'New complaint: ' . $title, $description, APP_URL . '/pages/landlord/complaints.php');
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

function updateComplaint(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $status = $data['status'] ?? '';
    $notes = trim($data['resolution_notes'] ?? '');

    $allowed = ['open', 'in_progress', 'resolved', 'closed'];
    if (!$id || !in_array($status, $allowed)) jsonResponse(['error' => 'Invalid'], 400);

    $db = getDB();
    $resolvedAt = in_array($status, ['resolved', 'closed']) ? date('Y-m-d H:i:s') : null;
    $stmt = $db->prepare("UPDATE complaints SET status = ?, resolution_notes = ?, resolved_at = ? WHERE id = ? AND landlord_id = ?");
    $stmt->execute([$status, $notes, $resolvedAt, $id, $user['id']]);

    jsonResponse(['success' => true]);
}

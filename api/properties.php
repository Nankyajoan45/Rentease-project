<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

requireLogin();
$user = currentUser();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listProperties();
        break;
    case 'get':
        getProperty();
        break;
    case 'create':
        createProperty();
        break;
    case 'update':
        updateProperty();
        break;
    case 'delete':
        deleteProperty();
        break;
    case 'update_status':
        updateStatus();
        break;
    case 'map_data':
        mapData();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function listProperties(): void {
    global $user;
    $db = getDB();

    $where = "WHERE 1=1";
    $params = [];

    // Owner filter for landlords
    if (in_array($user['role'], ['landlord', 'agent'])) {
        $where .= " AND p.owner_id = ?";
        $params[] = $user['id'];
    }

    $location = trim($_GET['location'] ?? '');
    $type = $_GET['type'] ?? '';
    $minPrice = (float)($_GET['min_price'] ?? 0);
    $maxPrice = (float)($_GET['max_price'] ?? 0);
    $status = $_GET['status'] ?? '';

    if ($location) { $where .= " AND (p.location LIKE ? OR p.address LIKE ? OR p.city LIKE ?)"; $params[] = "%$location%"; $params[] = "%$location%"; $params[] = "%$location%"; }
    if ($type) { $where .= " AND p.property_type = ?"; $params[] = $type; }
    if ($minPrice > 0) { $where .= " AND p.price >= ?"; $params[] = $minPrice; }
    if ($maxPrice > 0) { $where .= " AND p.price <= ?"; $params[] = $maxPrice; }
    if ($status) { $where .= " AND p.status = ?"; $params[] = $status; }

    if ($user['role'] === 'tenant') $where .= " AND p.status = 'available'";

    $stmt = $db->prepare("
        SELECT p.*, u.name AS owner_name, u.phone AS owner_phone,
               (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) AS image_count
        FROM properties p
        JOIN users u ON u.id = p.owner_id
        $where
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    jsonResponse(['properties' => $stmt->fetchAll()]);
}

function getProperty(): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, u.name AS owner_name, u.phone AS owner_phone, u.email AS owner_email
        FROM properties p JOIN users u ON u.id = p.owner_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $property = $stmt->fetch();
    if (!$property) jsonResponse(['error' => 'Not found'], 404);

    $images = $db->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC");
    $images->execute([$id]);
    $property['images'] = $images->fetchAll();

    // Increment views
    $db->prepare("UPDATE properties SET views = views + 1 WHERE id = ?")->execute([$id]);

    jsonResponse(['property' => $property]);
}

function createProperty(): void {
    global $user;
    if (!in_array($user['role'], ['landlord', 'agent', 'admin'])) jsonResponse(['error' => 'Unauthorized'], 403);

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $required = ['title', 'property_type', 'price', 'location'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "Field '$f' is required"], 422);
    }

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO properties (owner_id, title, description, property_type, price, location, address, city, latitude, longitude, num_rooms, num_bathrooms, amenities, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
    ");
    $stmt->execute([
        $user['id'],
        $data['title'],
        $data['description'] ?? '',
        $data['property_type'],
        $data['price'],
        $data['location'],
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        (int)($data['num_rooms'] ?? 1),
        (int)($data['num_bathrooms'] ?? 1),
        $data['amenities'] ?? '',
    ]);

    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

function updateProperty(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    $prop = $db->prepare("SELECT owner_id FROM properties WHERE id = ?")->execute([$id]);
    $prop = $db->query("SELECT owner_id FROM properties WHERE id = $id")->fetch();

    if (!$prop || ($prop['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $stmt = $db->prepare("
        UPDATE properties SET title=?, description=?, property_type=?, price=?, location=?, address=?, city=?, num_rooms=?, num_bathrooms=?, amenities=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        $data['title'] ?? '',
        $data['description'] ?? '',
        $data['property_type'] ?? '',
        $data['price'] ?? 0,
        $data['location'] ?? '',
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['num_rooms'] ?? 1,
        $data['num_bathrooms'] ?? 1,
        $data['amenities'] ?? '',
        $id
    ]);
    jsonResponse(['success' => true]);
}

function deleteProperty(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Invalid ID'], 400);

    $db = getDB();
    $prop = $db->query("SELECT owner_id FROM properties WHERE id = $id")->fetch();
    if (!$prop || ($prop['owner_id'] != $user['id'] && $user['role'] !== 'admin')) {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }

    $db->prepare("DELETE FROM properties WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}

function updateStatus(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    $status = $_POST['status'] ?? (json_decode(file_get_contents('php://input'), true)['status'] ?? '');
    $allowed = ['available', 'occupied', 'maintenance', 'inactive'];
    if (!$id || !in_array($status, $allowed)) jsonResponse(['error' => 'Invalid'], 400);

    $db = getDB();
    $db->prepare("UPDATE properties SET status = ? WHERE id = ?")->execute([$status, $id]);
    jsonResponse(['success' => true]);
}

function mapData(): void {
    $db = getDB();
    $stmt = $db->query("
        SELECT p.id, p.title, p.price, p.location, p.property_type, p.status, p.latitude, p.longitude,
               (SELECT image_path FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS image
        FROM properties p
        WHERE p.status = 'available' AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL
        LIMIT 200
    ");
    jsonResponse(['markers' => $stmt->fetchAll()]);
}

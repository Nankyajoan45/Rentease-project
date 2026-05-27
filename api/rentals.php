<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
requireLogin();
$user = currentUser();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listRentals();
        break;
    case 'create':
        createRental();
        break;
    case 'end':
        endRental();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function listRentals(): void {
    global $user;
    $db = getDB();
    if ($user['role'] === 'tenant') {
        $stmt = $db->prepare("
            SELECT r.*, p.title AS property_title, p.location, u.name AS landlord_name, u.phone AS landlord_phone
            FROM rentals r
            JOIN properties p ON p.id=r.property_id
            JOIN users u ON u.id=p.owner_id
            WHERE r.tenant_id=? ORDER BY r.start_date DESC
        ");
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $db->prepare("
            SELECT r.*, p.title AS property_title, p.location, u.name AS tenant_name, u.phone AS tenant_phone
            FROM rentals r
            JOIN properties p ON p.id=r.property_id
            JOIN users u ON u.id=r.tenant_id
            WHERE p.owner_id=? ORDER BY r.start_date DESC
        ");
        $stmt->execute([$user['id']]);
    }
    jsonResponse(['rentals' => $stmt->fetchAll()]);
}

function createRental(): void {
    global $user;
    if (!in_array($user['role'], ['landlord','agent','admin'])) jsonResponse(['error'=>'Unauthorized'],403);
    $data = json_decode(file_get_contents('php://input'),true) ?? $_POST;

    $propertyId = (int)($data['property_id'] ?? 0);
    $tenantId   = (int)($data['tenant_id']   ?? 0);
    $rent       = (float)($data['monthly_rent'] ?? 0);
    $startDate  = $data['start_date'] ?? date('Y-m-d');

    if (!$propertyId || !$tenantId || !$rent) jsonResponse(['error'=>'Missing fields'],422);

    $db = getDB();
    $prop = $db->query("SELECT owner_id FROM properties WHERE id=$propertyId")->fetch();
    if (!$prop || ($prop['owner_id'] != $user['id'] && $user['role']!=='admin')) jsonResponse(['error'=>'Unauthorized'],403);

    $stmt = $db->prepare("INSERT INTO rentals (property_id,tenant_id,monthly_rent,deposit,start_date,end_date,status) VALUES (?,?,?,?,?,?,'active')");
    $stmt->execute([$propertyId,$tenantId,$rent,$data['deposit']??0,$startDate,$data['end_date']??null]);
    $db->prepare("UPDATE properties SET status='occupied' WHERE id=?")->execute([$propertyId]);
    createNotification($tenantId,'rental','Tenancy Confirmed','Your rental agreement has been created.');
    jsonResponse(['success'=>true,'id'=>$db->lastInsertId()]);
}

function endRental(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error'=>'Invalid'],400);
    $db = getDB();
    $rental = $db->query("SELECT r.*,p.owner_id FROM rentals r JOIN properties p ON p.id=r.property_id WHERE r.id=$id")->fetch();
    if (!$rental || ($rental['owner_id']!=$user['id'] && $user['role']!=='admin')) jsonResponse(['error'=>'Unauthorized'],403);
    $db->prepare("UPDATE rentals SET status='terminated',end_date=CURDATE() WHERE id=?")->execute([$id]);
    $db->prepare("UPDATE properties SET status='available' WHERE id=?")->execute([$rental['property_id']]);
    jsonResponse(['success'=>true]);
}

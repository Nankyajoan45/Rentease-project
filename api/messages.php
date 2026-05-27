<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
requireLogin();
$user = currentUser();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'inbox':
        inbox();
        break;
    case 'send':
        send();
        break;
    case 'thread':
        thread();
        break;
    case 'mark_read':
        markRead();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function inbox(): void {
    global $user;
    $db = getDB();
    $stmt = $db->prepare("
        SELECT m.*, u.name AS sender_name, u.role AS sender_role,
               p.title AS property_title
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        LEFT JOIN properties p ON p.id = m.property_id
        WHERE m.receiver_id = ? AND m.parent_id IS NULL
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    jsonResponse(['messages' => $stmt->fetchAll()]);
}

function send(): void {
    global $user;
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $receiverId = (int)($data['receiver_id'] ?? 0);
    $body = trim($data['body'] ?? '');
    $subject = trim($data['subject'] ?? 'New Message');
    $propertyId = (int)($data['property_id'] ?? 0) ?: null;
    $parentId = (int)($data['parent_id'] ?? 0) ?: null;

    if (!$receiverId || !$body) jsonResponse(['error' => 'Missing fields'], 422);

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, property_id, subject, body, parent_id) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user['id'], $receiverId, $propertyId, $subject, $body, $parentId]);

    createNotification($receiverId, 'message', 'New message from ' . $user['name'], substr($body, 0, 100), APP_URL . '/pages/messages.php');
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

function thread(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Invalid'], 400);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m JOIN users u ON u.id = m.sender_id
        WHERE m.id = ? OR m.parent_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$id, $id]);
    $messages = $stmt->fetchAll();

    // Mark as read
    $db->prepare("UPDATE messages SET is_read = 1 WHERE (id = ? OR parent_id = ?) AND receiver_id = ?")->execute([$id, $id, $user['id']]);

    jsonResponse(['messages' => $messages]);
}

function markRead(): void {
    global $user;
    $id = (int)($_GET['id'] ?? 0);
    $db = getDB();
    $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?")->execute([$id, $user['id']]);
    jsonResponse(['success' => true]);
}

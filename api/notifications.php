<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
requireLogin();
$user = currentUser();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'unread_count':
        jsonResponse(['count' => unreadCount($user['id']), 'messages' => unreadMessages($user['id'])]);
        break;
    case 'mark_read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            getDB()->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $user['id']]);
        } else {
            getDB()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
        }
        jsonResponse(['success' => true]);
        break;
    case 'list':
        $notes = getDB()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
        $notes->execute([$user['id']]);
        jsonResponse(['notifications' => $notes->fetchAll()]);
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

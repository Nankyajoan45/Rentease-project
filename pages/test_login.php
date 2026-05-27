<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@rentease.com']);
$user = $stmt->fetch();

echo "User found: " . ($user ? 'YES' : 'NO') . "<br>";
echo "Hash: " . $user['password_hash'] . "<br>";
echo "Password verify: " . (password_verify('password', $user['password_hash']) ? 'YES ✅' : 'NO ❌') . "<br>";
echo "is_active: " . $user['is_active'] . "<br>";
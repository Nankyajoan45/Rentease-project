<?php
require_once __DIR__ . '/../includes/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'logout':
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?msg=logged_out');
        exit;
    case 'reset_request':
        handleResetRequest();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        header('Location: ' . APP_URL . '/index.php');
        exit;
}

function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? APP_URL . '/index.php';

    if (!$email || !$password) {
        header('Location: ' . APP_URL . '/pages/login.php?error=missing_fields');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: ' . APP_URL . '/pages/login.php?error=invalid_credentials');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];

    // Redirect based on role
    $roleRedirect = match($user['role']) {
        'landlord', 'agent' => APP_URL . '/pages/landlord/dashboard.php',
        'admin' => APP_URL . '/pages/admin/dashboard.php',
        default => APP_URL . '/pages/tenant/dashboard.php',
    };

    header('Location: ' . $roleRedirect);
    exit;
}

function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . APP_URL . '/pages/register.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'tenant';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $allowedRoles = ['tenant', 'landlord', 'agent'];
    if (!in_array($role, $allowedRoles)) $role = 'tenant';

    $errors = [];
    if (!$name) $errors[] = 'Name is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirm) $errors[] = 'Passwords do not match';

    if ($errors) {
        $q = http_build_query(['error' => implode(', ', $errors)]);
        header('Location: ' . APP_URL . '/pages/register.php?' . $q);
        exit;
    }

    $db = getDB();
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        header('Location: ' . APP_URL . '/pages/register.php?error=Email+already+registered');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $hash, $role]);

    $userId = $db->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;

    createNotification($userId, 'welcome', 'Welcome to RentEase!', "Hi $name, your account has been created successfully.", APP_URL . '/index.php');

    $redirect = match($role) {
        'landlord', 'agent' => APP_URL . '/pages/landlord/dashboard.php',
        default => APP_URL . '/pages/tenant/dashboard.php',
    };
    header('Location: ' . $redirect . '?welcome=1');
    exit;
}

function handleResetRequest(): void {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . APP_URL . '/pages/forgot-password.php?error=invalid_email');
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?")->execute([$token, $expiry, $email]);
        // In production: send email with reset link
    }
    header('Location: ' . APP_URL . '/pages/forgot-password.php?sent=1');
    exit;
}

function handleResetPassword(): void {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm || strlen($password) < 8) {
        header('Location: ' . APP_URL . '/pages/reset-password.php?token=' . $token . '&error=1');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ' . APP_URL . '/pages/forgot-password.php?error=expired_token');
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")->execute([$hash, $user['id']]);
    header('Location: ' . APP_URL . '/pages/login.php?msg=password_reset');
    exit;
}

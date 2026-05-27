<?php
/**
 * RentEase — First-Run Setup
 * Visit: http://yourserver/rentease/setup.php
 * DELETE THIS FILE after setup!
 */

$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $user = trim($_POST['db_user'] ?? 'root');
    $pass = $_POST['db_pass'] ?? '';
    $name = trim($_POST['db_name'] ?? 'rentease');
    $port = (int)($_POST['db_port'] ?? 3306);
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');

    try {
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Run schema
        $sql = file_get_contents(__DIR__ . '/database.sql');
        // Split and execute statement by statement
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) {
                try { $pdo->exec($stmt); } catch(PDOException $e) { /* ignore duplicate errors */ }
            }
        }

        // Update config
        $config = file_get_contents(__DIR__ . '/includes/config.php');
        $config = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/", "define('DB_HOST', '$host')", $config);
        $config = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', '$user')", $config);
        $config = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '$pass')", $config);
        $config = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/", "define('DB_NAME', '$name')", $config);
        $config = preg_replace("/define\('DB_PORT',\s*\d+\)/",      "define('DB_PORT', $port)", $config);
        $config = preg_replace("/define\('APP_URL',\s*'[^']*'\)/",  "define('APP_URL', '$appUrl')", $config);
        file_put_contents(__DIR__ . '/includes/config.php', $config);

        $success = "Setup complete! Database '$name' created and configured.";
        $step = 3;
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RentEase Setup</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg">
    <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-800 to-blue-600 p-8 text-white text-center">
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">🏠</span>
            </div>
            <h1 class="text-2xl font-bold">RentEase Setup</h1>
            <p class="text-blue-200 mt-1 text-sm">First-run configuration wizard</p>
        </div>
        <div class="p-8">
            <?php if ($step === 1): ?>
            <h2 class="text-lg font-bold text-slate-800 mb-2">Welcome!</h2>
            <p class="text-slate-600 text-sm mb-6">This wizard will configure RentEase for your server. You'll need MySQL credentials to proceed.</p>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
                <strong>⚠️ Security Notice:</strong> Delete <code>setup.php</code> after completing setup.
            </div>
            <a href="?step=2" class="block w-full bg-blue-700 text-white text-center py-3 rounded-xl font-semibold hover:bg-blue-800 transition-colors">
                Begin Setup →
            </a>

            <?php elseif ($step === 2): ?>
            <h2 class="text-lg font-bold text-slate-800 mb-5">Database & App Configuration</h2>
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-700">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="step" value="2">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">App URL</label>
                        <input type="text" name="app_url" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400" value="http://localhost/rentease" placeholder="http://yourserver.com/rentease">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">DB Host</label>
                        <input type="text" name="db_host" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400" value="localhost">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">DB Port</label>
                        <input type="number" name="db_port" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400" value="3306">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">DB Username</label>
                        <input type="text" name="db_user" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400" value="root">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">DB Password</label>
                        <input type="password" name="db_pass" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Database Name</label>
                        <input type="text" name="db_name" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-blue-400" value="rentease">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-3 rounded-xl font-semibold hover:bg-blue-800 transition-colors">
                    Install Database & Configure →
                </button>
            </form>

            <?php elseif ($step === 3): ?>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">✅</span>
                </div>
                <h2 class="text-lg font-bold text-green-700 mb-2">Setup Complete!</h2>
                <p class="text-slate-600 text-sm mb-6"><?= htmlspecialchars($success) ?></p>

                <div class="bg-slate-50 rounded-xl p-4 text-left text-sm mb-6 space-y-2">
                    <p class="font-semibold text-slate-700">Demo login credentials:</p>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="bg-white rounded-lg p-2 border"><div class="font-semibold text-purple-700">Admin</div><div class="text-slate-500">admin@rentease.com</div></div>
                        <div class="bg-white rounded-lg p-2 border"><div class="font-semibold text-blue-700">Landlord</div><div class="text-slate-500">landlord@rentease.com</div></div>
                        <div class="bg-white rounded-lg p-2 border"><div class="font-semibold text-green-700">Tenant</div><div class="text-slate-500">tenant@rentease.com</div></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">All demo passwords: <code class="bg-slate-100 px-1 rounded">password</code></p>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-5 text-xs text-red-700 text-left">
                    🔴 <strong>Important:</strong> Delete <code>setup.php</code> from your server now!
                </div>

                <a href="index.php" class="block w-full bg-blue-700 text-white text-center py-3 rounded-xl font-semibold hover:bg-blue-800 transition-colors">
                    Go to RentEase →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

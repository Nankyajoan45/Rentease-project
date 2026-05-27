<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) { header('Location: '.APP_URL.'/index.php'); exit; }
$pageTitle = 'Forgot Password';
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-primary-700 via-primary-500 to-accent-500"></div>
            <div class="p-8">
                <div class="text-center mb-8">
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-primary-600 text-xl"></i>
                    </div>
                    <h1 class="text-xl font-bold text-slate-800">Reset Password</h1>
                    <p class="text-slate-500 text-sm mt-1">Enter your email and we'll send a reset link</p>
                </div>

                <?php if ($_GET['sent'] ?? false): ?>
                <div class="alert alert-success mb-5">
                    <i class="fas fa-check-circle"></i>
                    If that email exists, a reset link has been sent. Check your inbox.
                </div>
                <?php elseif ($_GET['error'] ?? false): ?>
                <div class="alert alert-error mb-5">
                    <i class="fas fa-times-circle"></i>
                    Please enter a valid email address.
                </div>
                <?php endif; ?>

                <form action="<?= APP_URL ?>/api/auth.php?action=reset_request" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-5">
                        <label class="form-label">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="email" name="email" class="form-input pl-11" placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="w-full btn-primary justify-center py-3">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Remember your password? <a href="<?= APP_URL ?>/pages/login.php" class="text-primary-700 font-semibold hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

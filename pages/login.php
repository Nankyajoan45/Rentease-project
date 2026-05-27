<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/index.php'); exit; }
$pageTitle = 'Sign In';
$error = $_GET['error'] ?? '';
$msg = $_GET['msg'] ?? '';
$errorMessages = [
    'invalid_credentials' => 'Invalid email or password. Please try again.',
    'missing_fields' => 'Please enter your email and password.',
    'unauthorized' => 'You do not have permission to access that page.',
];
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <!-- Top accent -->
            <div class="h-1.5 bg-orange-500"></div>

            <div class="p-8">
                <!-- Logo -->
                <div class="text-center mb-8">
                    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-blue-900 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-home text-white"></i>
                        </div>
                        <span class="font-display text-2xl text-primary-800">Rent<span class="text-accent-500">Ease</span></span>
                    </a>
                    <h1 class="text-xl font-bold text-slate-800">Welcome back</h1>
                    <p class="text-slate-500 text-sm mt-1">Sign in to your account</p>
                </div>

                <?php if ($error && isset($errorMessages[$error])): ?>
                <div class="alert alert-error mb-6">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $errorMessages[$error] ?>
                </div>
                <?php endif; ?>

                <?php if ($msg === 'logged_out'): ?>
                <div class="alert alert-info mb-6"><i class="fas fa-info-circle"></i> You have been signed out.</div>
                <?php endif; ?>

                <?php if ($msg === 'password_reset'): ?>
                <div class="alert alert-success mb-6"><i class="fas fa-check-circle"></i> Password reset successfully. Please sign in.</div>
                <?php endif; ?>

                <form action="<?= APP_URL ?>/api/auth.php?action=login" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="redirect" value="<?= sanitize($_GET['redirect'] ?? '') ?>">

                    <div class="space-y-5">
                        <div>
                            <label class="form-label">Email Address</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="email" name="email" class="form-input pl-11" placeholder="you@example.com" required autofocus>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="form-label mb-0">Password</label>
                                <a href="<?= APP_URL ?>/pages/forgot-password.php" class="text-xs text-primary-600 hover:underline font-medium">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="password" name="password" id="passwordInput" class="form-input pl-11 pr-11" placeholder="••••••••" required>
                                <button type="button" onclick="togglePass()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <i id="passIcon" class="fas fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full btn-primary justify-center py-3 text-base">
                            Sign In <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Don't have an account?
                    <a href="<?= APP_URL ?>/pages/register.php" class="text-primary-700 font-semibold hover:underline">Create one free</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const inp = document.getElementById('passwordInput');
    const icon = document.getElementById('passIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash text-sm'; }
    else { inp.type = 'password'; icon.className = 'fas fa-eye text-sm'; }
}
function fillDemo(email) {
    document.querySelector('[name=email]').value = email;
    document.querySelector('[name=password]').value = 'password';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

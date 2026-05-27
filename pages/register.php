<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/index.php'); exit; }
$pageTitle = 'Create Account';
$preRole = $_GET['role'] ?? 'tenant';
$error = $_GET['error'] ?? '';
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <div class="h-1.5 bg-orange-500"></div>
            <div class="p-8">
                <div class="text-center mb-8">
                    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-blue-900 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-home text-white"></i>
                        </div>
                        <span class="font-display text-2xl text-primary-800">Rent<span class="text-accent-500">Ease</span></span>
                    </a>
                    <h1 class="text-xl font-bold text-slate-800">Create your account</h1>
                    <p class="text-slate-500 text-sm mt-1">Join thousands of users on RentEase</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error mb-5"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
                <?php endif; ?>

                <!-- Role Selector -->
                <div class="grid grid-cols-3 gap-2 mb-6 p-1 bg-slate-100 rounded-xl">
                    <?php foreach (['tenant' => ['Tenant'], 'landlord' => ['Landlord'], 'agent' => ['Custodian']] as $role => $info): ?>
                    <label class="role-option cursor-pointer">
                        <input type="radio" name="role_select" value="<?= $role ?>" <?= $preRole === $role ? 'checked' : '' ?> class="sr-only" onchange="setRole(event, '<?= $role ?>')">
                        <div class="role-btn <?= $preRole === $role ? 'active' : '' ?> text-center py-2.5 px-2 rounded-lg transition-all">
                            <div class="text-xl mb-0.5"><?= $info[0] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <form action="<?= APP_URL ?>/api/auth.php?action=register" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="role" id="roleInput" value="<?= sanitize($preRole) ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label">Full Name *</label>
                            <div class="relative">
                                <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" name="name" class="form-input pl-11" placeholder="Your full name" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Email Address *</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="email" name="email" class="form-input pl-11" placeholder="you@email.com" required>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <div class="relative">
                                <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="tel" name="phone" class="form-input pl-11" placeholder="+256 7XX XXX XXX">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Password *</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="password" name="password" id="pass1" class="form-input pl-11 pr-11" placeholder="Min 8 characters" required minlength="8">
                                <button type="button" onclick="tp('pass1','pi1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 p-1">
                                    <i id="pi1" class="fas fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Confirm Password *</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="password" name="confirm_password" id="pass2" class="form-input pl-11" placeholder="Repeat password" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-primary justify-center py-3 text-base mt-6">
                        Create Account <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-5">
                    Already have an account? <a href="<?= APP_URL ?>/pages/login.php" class="text-primary-700 font-semibold hover:underline">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.role-btn { color: #64748b; }
.role-btn.active { background: white; color: #0f4c81; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>
<script>
function setRole(event, role) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.closest('.role-option').querySelector('.role-btn').classList.add('active');
}
function tp(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'text' ? 'fas fa-eye-slash text-sm' : 'fas fa-eye text-sm';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

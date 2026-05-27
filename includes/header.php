<?php
$user = currentUser();
$pageTitle = $pageTitle ?? APP_NAME;
$unread = $user ? unreadCount($user['id']) : 0;
$msgs = $user ? unreadMessages($user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#2d3f9e">
<meta name="description" content="RentEase - Find and manage rental properties with ease">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?= sanitize($pageTitle) ?> | <?= APP_NAME ?></title>
<link rel="manifest" href="<?= APP_URL ?>/manifest.json">
<link rel="icon" href="<?= APP_URL ?>/assets/images/icon-192.png">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/images/icon-192.png">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'sans-serif'],
                display: ['DM Serif Display', 'serif'],
            },
            colors: {
                primary: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#2d3f9e'
                ,800:'#0f4c81',900:'#0c3a62' },
                accent: { 400:'#fb923c',500:'#f97316',600:'#ea580c' },
            }
        }
    }
}
</script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen">

<!-- Top Navigation -->
<nav class="navbar fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="<?= APP_URL ?>/index.php" class="flex items-center gap-2.5 group">
                <div class="w-9 h-9 bg-blue-900 rounded-xl flex items-center justify-center shadow-md group-hover:scale-105 transition-transform">
                    <i class="fas fa-home text-white text-sm"></i>
                </div>
                <span class="font-display text-xl text-primary-800">Rent<span class="text-accent-500">Ease</span></span>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center gap-1">
                <a href="<?= APP_URL ?>/index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="<?= APP_URL ?>/pages/search.php" class="nav-link">Properties</a>
                <?php if ($user): ?>
                    <?php if (in_array($user['role'], ['landlord','agent','admin'])): ?>
                    <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="nav-link">Dashboard</a>
                    <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="nav-link">My Listings</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'tenant'): ?>
                    <a href="<?= APP_URL ?>/pages/tenant/dashboard.php" class="nav-link">Dashboard</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="nav-link">Admin</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-2">
                <?php if ($user): ?>
                    <!-- Messages -->
                    <a href="<?= APP_URL ?>/pages/messages.php" class="relative p-2 text-slate-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-colors">
                        <i class="fas fa-envelope text-lg"></i>
                        <?php if ($msgs > 0): ?>
                        <span class="badge"><?= $msgs ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- Notifications -->
                    <a href="<?= APP_URL ?>/pages/notifications.php" class="relative p-2 text-slate-500 hover:text-primary-700 hover:bg-primary-50 rounded-lg transition-colors">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($unread > 0): ?>
                        <span class="badge"><?= $unread ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- User Menu -->
                    <div class="relative" x-data="{ open: false }">
                        <button onclick="toggleDropdown('userMenu')" class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full hover:bg-slate-100 transition-colors">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-semibold">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-slate-700"><?= sanitize(explode(' ', $user['name'])[0]) ?></span>
                            <i class="fas fa-chevron-down text-xs text-slate-400"></i>
                        </button>
                        <div id="userMenu" class="dropdown-menu hidden absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-100 mb-1">
                                <p class="text-sm font-semibold text-slate-800"><?= sanitize($user['name']) ?></p>
                                <p class="text-xs text-slate-500 capitalize"><?= $user['role'] ?></p>
                            </div>
                            <a href="<?= APP_URL ?>/pages/profile.php" class="dropdown-item"><i class="fas fa-user-circle w-4"></i> My Profile</a>
                            <a href="<?= APP_URL ?>/pages/messages.php" class="dropdown-item"><i class="fas fa-envelope w-4"></i> Messages</a>
                            <?php if ($user['role'] === 'tenant'): ?>
                            <a href="<?= APP_URL ?>/pages/tenant/complaints.php" class="dropdown-item"><i class="fas fa-exclamation-circle w-4"></i> Complaints</a>
                            <?php endif; ?>
                            <div class="border-t border-slate-100 mt-1 pt-1">
                                <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="dropdown-item text-red-500 hover:bg-red-50"><i class="fas fa-sign-out-alt w-4"></i> Logout</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/pages/login.php" class="btn-outline text-sm">Sign In</a>
                    <a href="<?= APP_URL ?>/pages/register.php" class="btn-primary text-sm">Get Started</a>
                <?php endif; ?>

                <!-- Mobile Menu Toggle -->
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-colors ml-1">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden md:hidden border-t border-slate-200 bg-white px-4 py-3">
        <div class="flex flex-col gap-1">
            <a href="<?= APP_URL ?>/index.php" class="mobile-nav-link">Home</a>
            <a href="<?= APP_URL ?>/pages/search.php" class="mobile-nav-link">Properties</a>
            <?php if ($user): ?>
                <?php if (in_array($user['role'], ['landlord','agent'])): ?>
                <a href="<?= APP_URL ?>/pages/landlord/dashboard.php" class="mobile-nav-link">Dashboard</a>
                <a href="<?= APP_URL ?>/pages/landlord/properties.php" class="mobile-nav-link">My Listings</a>
                <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="mobile-nav-link">Add Property</a>
                <?php elseif ($user['role'] === 'tenant'): ?>
                <a href="<?= APP_URL ?>/pages/tenant/dashboard.php" class="mobile-nav-link">Dashboard</a>
                <a href="<?= APP_URL ?>/pages/tenant/complaints.php" class="mobile-nav-link">Complaints</a>
                <?php elseif ($user['role'] === 'admin'): ?>
                <a href="<?= APP_URL ?>/pages/admin/dashboard.php" class="mobile-nav-link">Admin Panel</a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/pages/messages.php" class="mobile-nav-link">Messages <?php if($msgs>0): ?><span class="badge-inline"><?=$msgs?></span><?php endif; ?></a>
                <a href="<?= APP_URL ?>/pages/profile.php" class="mobile-nav-link">Profile</a>
                <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="mobile-nav-link text-red-500">Logout</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="mobile-nav-link">Sign In</a>
                <a href="<?= APP_URL ?>/pages/register.php" class="mobile-nav-link font-semibold text-primary-700">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Page offset for fixed nav -->
<div class="h-16"></div>

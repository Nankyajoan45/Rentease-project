<!-- Footer -->
<footer class="bg-slate-900 text-slate-300 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Brand -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-9 h-9 bg-blue-900 rounded-xl flex items-center justify-center">
                        <i class="fas fa-home text-white text-sm"></i>
                    </div>
                    <span class="font-display text-xl text-white">Rent<span class="text-accent-400">Ease</span></span>
                </div>
                <p class="text-sm text-slate-400 leading-relaxed">Uganda's trusted platform for finding and managing rental properties with ease and confidence.</p>
                <div class="flex gap-3 mt-4">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <!-- Quick Links -->
            <div>
                <h4 class="font-semibold text-white mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= APP_URL ?>/index.php" class="footer-link">Home</a></li>
                    <li><a href="<?= APP_URL ?>/pages/search.php" class="footer-link">Browse Properties</a></li>
                    <li><a href="<?= APP_URL ?>/pages/register.php" class="footer-link">List Your Property</a></li>
                    <li><a href="<?= APP_URL ?>/pages/about.php" class="footer-link">About Us</a></li>
                </ul>
            </div>
            <!-- For Tenants -->
            <div>
                <h4 class="font-semibold text-white mb-4">For Tenants</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="<?= APP_URL ?>/pages/search.php" class="footer-link">Find a House</a></li>
                    <li><a href="<?= APP_URL ?>/pages/search.php?type=apartment" class="footer-link">Apartments</a></li>
                    <li><a href="<?= APP_URL ?>/pages/search.php?type=room" class="footer-link">Rooms</a></li>
                </ul>
            </div>
            <!-- Contact -->
            <div>
                <h4 class="font-semibold text-white mb-4">Contact Us</h4>
                <ul class="space-y-3 text-sm text-slate-400">
                    <li class="flex items-start gap-2"><i class="fas fa-map-marker-alt mt-1 text-accent-400"></i><span>Plot 45 Arua Road, Arua, Uganda</span></li>
                    <li class="flex items-center gap-2"><i class="fas fa-phone text-accent-400"></i><span>+256 770 863 080/ +256 768 840 706</span></li>
                    <li class="flex items-center gap-2"><i class="fas fa-envelope text-accent-400"></i><span>info@rentease.ug</span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 mt-10 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-500">
            <p>&copy; <?= date('Y') ?> RentEase Uganda. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#" class="hover:text-slate-300 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-slate-300 transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<!-- Bottom Nav for Mobile (PWA feel) -->
<?php if (isset($user) && $user): ?>
<div class="mobile-bottom-nav md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex z-40 safe-area-bottom">
    <a href="<?= APP_URL ?>/index.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="<?= APP_URL ?>/pages/search.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : '' ?>">
        <i class="fas fa-search"></i><span>Search</span>
    </a>
    <?php if (in_array($user['role'], ['landlord','agent','admin'])): ?>
    <a href="<?= APP_URL ?>/pages/landlord/add-property.php" class="bottom-nav-item-center">
        <div class="add-btn"><i class="fas fa-plus text-white text-lg"></i></div>
    </a>
    <?php else: ?>
    <a href="<?= APP_URL ?>/pages/messages.php" class="bottom-nav-item relative">
        <i class="fas fa-envelope"></i><span>Messages</span>
        <?php if($msgs>0): ?><span class="badge-sm"><?=$msgs?></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/pages/messages.php" class="bottom-nav-item relative <?= basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : '' ?>">
        <i class="fas fa-envelope"></i><span>Messages</span>
        <?php if($msgs>0): ?><span class="badge-sm"><?=$msgs?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/pages/profile.php" class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
        <i class="fas fa-user"></i><span>Profile</span>
    </a>
</div>
<div class="h-16 md:hidden"></div>
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= APP_URL ?>/sw.js')
                .then(reg => console.log('SW registered'))
                .catch(err => console.log('SW error:', err));
        });
    }
</script>
</body>
</html>

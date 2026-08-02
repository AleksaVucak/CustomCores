<?php
/**
 * CustomCore — Shared site footer (closes main through document end).
 *
 * File responsibility:
 *   Closes the main content region and outputs footer links plus shared scripts.
 *   Loads assets/js/builder.js when $currentPage is "builder" (Commit 5.2).
 *   Loads assets/js/cart.js when $currentPage is "cart" (Commit 6.2).
 *   Loads assets/js/checkout.js when $currentPage is "checkout" (Commit 6.4).
 *   Loads assets/js/reviews.js when $loadReviewForm is truthy (Commit 7.2).
 *   Loads assets/js/contact.js when $currentPage is "contact" (Commit 7.5).
 *   Loads Leaflet CDN + assets/js/store-map.js when $currentPage is "locations" (Commit 8.4).
 *   Loads Chart.js CDN + assets/js/charts.js when $loadCharts is truthy (Commit 5.8).
 *
 * Included after page body content on each layout-using page.
 */

declare(strict_types=1);

if (!function_exists('customcore_e')) {
    require_once __DIR__ . '/functions.php';
}

$app = customcore_app_config();
$siteName = (string) ($app['name'] ?? 'CustomCore');
$year = date('Y');
?>
    </main>

    <footer class="site-footer" role="contentinfo">
        <div class="site-footer__inner">
            <p class="site-footer__brand">
                &copy; <?php echo customcore_e($year); ?>
                <?php echo customcore_e($siteName); ?>
            </p>

            <ul class="site-footer__links">
                <li><a href="<?php echo customcore_e(customcore_url('about.php')); ?>">About</a></li>
                <li><a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help</a></li>
                <li><a href="<?php echo customcore_e(customcore_url('privacy.php')); ?>">Privacy</a></li>
                <li><a href="<?php echo customcore_e(customcore_url('accessibility.php')); ?>">Accessibility</a></li>
                <li><a href="<?php echo customcore_e(customcore_url('contact.php')); ?>">Contact</a></li>
            </ul>
        </div>
    </footer>

    <script src="<?php echo customcore_e(customcore_url('assets/js/main.js')); ?>" defer></script>
    <?php if (isset($currentPage) && $currentPage === 'builder') : ?>
        <script src="<?php echo customcore_e(customcore_url('assets/js/builder.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (isset($currentPage) && $currentPage === 'cart') : ?>
        <script src="<?php echo customcore_e(customcore_url('assets/js/cart.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (isset($currentPage) && $currentPage === 'checkout') : ?>
        <script src="<?php echo customcore_e(customcore_url('assets/js/checkout.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (!empty($loadReviewForm)) : ?>
        <script src="<?php echo customcore_e(customcore_url('assets/js/reviews.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (isset($currentPage) && $currentPage === 'contact') : ?>
        <script src="<?php echo customcore_e(customcore_url('assets/js/contact.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (isset($currentPage) && $currentPage === 'locations') : ?>
        <script
            src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""
            defer
        ></script>
        <script src="<?php echo customcore_e(customcore_url('assets/js/store-map.js')); ?>" defer></script>
    <?php endif; ?>
    <?php if (!empty($loadCharts)) : ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script src="<?php echo customcore_e(customcore_url('assets/js/charts.js')); ?>" defer></script>
    <?php endif; ?>
</body>
</html>

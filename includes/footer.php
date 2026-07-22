<?php
/**
 * CustomCore — Shared site footer (closes main through document end).
 *
 * File responsibility:
 *   Closes the main content region and outputs footer links plus shared scripts.
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
</body>
</html>

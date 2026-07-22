<?php
/**
 * CustomCore — Administrator Dashboard (Commit 4.7 landing).
 *
 * File responsibility:
 *   Entry point for the protected administrator area. This commit establishes
 *   role-based access control: only 'admin' users reach this page. The full
 *   management dashboard (counts, alerts, reports) is built in Stage 9; this
 *   page demonstrates and enforces the authorization boundary.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';

customcore_require_admin();

$adminName = customcore_current_user_name();

$pageTitle = 'Admin dashboard — CustomCore';
$pageDescription = 'CustomCore administrator area.';
$pageKeywords = 'CustomCore, admin, dashboard';
$currentPage = 'admin';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page" aria-labelledby="admin-heading">
    <header class="admin-page__header">
        <h1 id="admin-heading">Administrator dashboard</h1>
        <p class="admin-page__intro">
            Welcome, <?php echo customcore_e($adminName !== '' ? $adminName : 'Administrator'); ?>.
            This is the protected administrator area.
        </p>
    </header>

    <div class="flash flash--success" role="status">
        Role-based access control is active — only administrators can see this page.
    </div>

    <section class="admin-page__section" aria-labelledby="admin-tools-heading">
        <h2 id="admin-tools-heading">Management tools</h2>
        <p>
            The full administrator toolset — products, options, orders, users,
            consultations, review moderation, themes, reports, and monitoring —
            is built in Stage 9. Access control for every one of those routes is
            already enforced by <code>customcore_require_admin()</code>.
        </p>
        <p class="admin-page__note">
            Planned sections: product management, order management, user
            management, consultation responses, review moderation, theme
            selection, reports, and service monitoring.
        </p>
    </section>

    <div class="form-actions">
        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('index.php')); ?>">
            Back to store
        </a>
        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('profile.php')); ?>">
            My account
        </a>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

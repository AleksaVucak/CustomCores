<?php
/**
 * CustomCore — Homepage.
 *
 * File responsibility:
 *   Public landing page. Featured products and richer hero content arrive in Stage 3.
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'CustomCore — Custom Gaming PC Store & Builder';
$pageDescription = 'Browse configurable gaming PCs and build a compatible custom system with CustomCore.';
$pageKeywords = 'CustomCore, gaming PC, custom PC builder, prebuilt gaming computer';
$currentPage = 'home';

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>CustomCore</h1>
    <p class="hero__support">
        Custom gaming PC store and guided PC builder — clear options, live pricing,
        and compatibility feedback for students, gamers, and creators.
    </p>
    <p class="hero__actions">
        <a class="button" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Shop prebuilts</a>
        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Start PC Builder</a>
    </p>
</section>

<section class="content-section">
    <h2>Foundation in progress</h2>
    <p>
        Shared layout, navigation, and configuration are active. Catalogue data,
        accounts, checkout, and administrator tools are added in later stages.
    </p>
    <p>
        Read the
        <a href="<?php echo customcore_e(customcore_url('about.php')); ?>">About</a>
        page for the full business case, or browse project planning docs in the repository
        <code>docs/</code> folder.
    </p>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

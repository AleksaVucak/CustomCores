<?php
/**
 * CustomCore — Shared site header (document start through opening main).
 *
 * File responsibility:
 *   Outputs the HTML head, skip link, site header chrome, and opens <main>.
 *   Expects pages to set $pageTitle and optionally $pageDescription, $pageKeywords,
 *   and $currentPage before including this file.
 *
 * Optional SEO overrides (Commit 14.1):
 *   $pageCanonical — project-relative canonical target, or false to omit the
 *                    <link rel="canonical">. Defaults to a self-referencing URL.
 *   $pageNoindex   — set true to force <meta name="robots"> noindex. Admin and
 *                    per-user private pages are noindexed automatically.
 *
 * Authentication requirements:
 *   None for the include itself. Private pages add auth checks before this include.
 *
 * Required setup on each page:
 *   require_once __DIR__ . '/includes/functions.php';
 *   $pageTitle = '...';
 *   $currentPage = 'home'; // optional, for active nav state
 *   require_once __DIR__ . '/includes/header.php';
 */

declare(strict_types=1);

if (!function_exists('customcore_e')) {
    require_once __DIR__ . '/functions.php';
}

require_once __DIR__ . '/flash.php';
customcore_flash_bootstrap();

$app = customcore_app_config();
$siteName = (string) ($app['name'] ?? 'CustomCore');
$defaultDescription = (string) ($app['tagline'] ?? 'Custom gaming PC store and guided PC builder');

if (!isset($pageTitle) || !is_string($pageTitle) || $pageTitle === '') {
    $pageTitle = $siteName;
}

if (!isset($pageDescription) || !is_string($pageDescription) || $pageDescription === '') {
    $pageDescription = $defaultDescription;
}

if (!isset($pageKeywords) || !is_string($pageKeywords)) {
    $pageKeywords = 'CustomCore, gaming PC, custom PC builder, prebuilt gaming computer';
}

if (!isset($currentPage) || !is_string($currentPage)) {
    $currentPage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo customcore_e($pageDescription); ?>">
    <meta name="keywords" content="<?php echo customcore_e($pageKeywords); ?>">
    <title><?php echo customcore_e($pageTitle); ?></title>

    <?php
    // Search-engine indexing policy (Commit 14.1). Public content pages are
    // indexable; admin and per-user private pages are excluded.
    $robotsDirective = customcore_is_noindex_page() ? 'noindex, nofollow' : 'index, follow';
    ?>
    <meta name="robots" content="<?php echo customcore_e($robotsDirective); ?>">

    <?php
    // Canonical URL (Commit 14.1). A page may set $pageCanonical to a
    // project-relative target, or to false to suppress the tag entirely.
    $canonicalUrl = customcore_canonical_url($pageCanonical ?? null);
    ?>
    <?php if ($canonicalUrl !== null) : ?>
        <link rel="canonical" href="<?php echo customcore_e($canonicalUrl); ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo customcore_e($siteName); ?>">
    <meta property="og:title" content="<?php echo customcore_e($pageTitle); ?>">
    <meta property="og:description" content="<?php echo customcore_e($pageDescription); ?>">
    <?php if ($canonicalUrl !== null) : ?>
        <meta property="og:url" content="<?php echo customcore_e($canonicalUrl); ?>">
    <?php endif; ?>
    <?php $ogImage = customcore_image_url('assets/images/og/social-share.jpg'); ?>
    <?php if ($ogImage !== null) : ?>
        <meta property="og:image" content="<?php echo customcore_e($ogImage); ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?php echo customcore_e($ogImage); ?>">
    <?php endif; ?>

    <!-- Favicon, theme colour, and web app manifest (Commit 14.1) -->
    <link rel="icon" type="image/svg+xml" href="<?php echo customcore_e(customcore_url('favicon.svg')); ?>">
    <link rel="icon" href="<?php echo customcore_e(customcore_url('favicon.svg')); ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?php echo customcore_e(customcore_url('favicon.svg')); ?>">
    <link rel="manifest" href="<?php echo customcore_e(customcore_url('site.webmanifest')); ?>">
    <meta name="theme-color" content="#12151c">

    <link rel="stylesheet" href="<?php echo customcore_e(customcore_url('assets/css/main.css')); ?>">
    <?php if (!empty($loadAdminCss)) : ?>
        <link rel="stylesheet" href="<?php echo customcore_e(customcore_url('assets/css/admin.css')); ?>">
    <?php endif; ?>
    <?php if ($currentPage === 'locations') : ?>
        <!-- Leaflet map styles for the store & service map (Commit 8.4) -->
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin="anonymous"
        >
    <?php endif; ?>
    <?php
    // Active theme stylesheet (Stage 10): resolved from MySQL settings with a
    // safe fallback chain, and linked LAST so it overrides main.css/admin.css.
    require_once __DIR__ . '/theme.php';
    $themeHref = customcore_active_theme_href();
    ?>
    <?php if ($themeHref !== null) : ?>
        <link rel="stylesheet" href="<?php echo customcore_e($themeHref); ?>">
    <?php endif; ?>
</head>
<body class="page-<?php echo customcore_e($currentPage !== '' ? $currentPage : 'default'); ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header" role="banner">
        <div class="site-header__inner">
            <a class="site-logo" href="<?php echo customcore_e(customcore_url('index.php')); ?>">
                <?php echo customcore_e($siteName); ?>
            </a>

            <?php require __DIR__ . '/navigation.php'; ?>
        </div>
    </header>

    <?php customcore_flash_render(); ?>

    <main id="main-content" class="site-main" tabindex="-1">

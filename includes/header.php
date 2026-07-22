<?php
/**
 * CustomCore — Shared site header (document start through opening main).
 *
 * File responsibility:
 *   Outputs the HTML head, skip link, site header chrome, and opens <main>.
 *   Expects pages to set $pageTitle and optionally $pageDescription, $pageKeywords,
 *   and $currentPage before including this file.
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
    <!-- Favicon and expanded SEO metadata arrive in Stage 14 -->
    <link rel="stylesheet" href="<?php echo customcore_e(customcore_url('assets/css/main.css')); ?>">
    <!-- Active theme stylesheet is loaded from MySQL settings in Stage 10 -->
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

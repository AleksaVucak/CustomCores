<?php
/**
 * CustomCore — Primary customer navigation.
 *
 * File responsibility:
 *   Renders the main menu used across public and private customer pages.
 *   Desktop layout is styled in Commit 1.5. Mobile toggle behaviour is
 *   completed in Commit 1.7 (`assets/js/main.js` + `.is-open` / `nav-enhanced`).
 *
 * Included from:
 *   includes/header.php
 */

declare(strict_types=1);

if (!function_exists('customcore_url')) {
    require_once __DIR__ . '/functions.php';
}

require_once __DIR__ . '/auth.php';

$navLoggedIn = customcore_is_logged_in();
$navIsAdmin = customcore_is_admin();
$navUserName = customcore_current_user_name();
$navHasProfile = is_file(dirname(__DIR__) . '/profile.php');
$navHasLogout = is_file(dirname(__DIR__) . '/logout.php');
$navHasAdmin = is_file(dirname(__DIR__) . '/admin/index.php');

$navItems = [
    'home' => ['label' => 'Home', 'href' => 'index.php'],
    'about' => ['label' => 'About', 'href' => 'about.php'],
    'catalogue' => ['label' => 'Catalogue', 'href' => 'catalogue.php'],
    'builder' => ['label' => 'PC Builder', 'href' => 'builder.php'],
    'media' => ['label' => 'Learning Centre', 'href' => 'media.php'],
    'locations' => ['label' => 'Locations', 'href' => 'store-locations.php'],
    'help' => ['label' => 'Help', 'href' => 'help/index.html'],
    'contact' => ['label' => 'Contact', 'href' => 'contact.php'],
];
?>
<button
    type="button"
    class="nav-toggle"
    id="nav-toggle"
    aria-controls="primary-navigation"
    aria-expanded="false"
    aria-label="Open menu"
>
    Menu
</button>

<nav class="site-nav" id="primary-navigation" aria-label="Primary">
    <ul class="site-nav__list">
        <?php foreach ($navItems as $key => $item): ?>
            <li class="site-nav__item">
                <a
                    class="site-nav__link<?php echo customcore_e(customcore_nav_class($key)); ?>"
                    href="<?php echo customcore_e(customcore_url($item['href'])); ?>"
                    <?php echo customcore_is_current_page($key) ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo customcore_e($item['label']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <ul class="site-nav__account">
        <?php if ($navLoggedIn) : ?>
            <?php if ($navIsAdmin && $navHasAdmin) : ?>
                <li>
                    <a
                        class="site-nav__link site-nav__link--admin<?php echo customcore_e(customcore_nav_class('admin')); ?>"
                        href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>"
                    >Admin</a>
                </li>
            <?php endif; ?>
            <li>
                <?php if ($navHasProfile) : ?>
                    <a
                        class="site-nav__link<?php echo customcore_e(customcore_nav_class('profile')); ?>"
                        href="<?php echo customcore_e(customcore_url('profile.php')); ?>"
                    >Hi, <?php echo customcore_e($navUserName !== '' ? $navUserName : 'Account'); ?></a>
                <?php else : ?>
                    <span class="site-nav__link site-nav__greeting">Hi, <?php echo customcore_e($navUserName !== '' ? $navUserName : 'Account'); ?></span>
                <?php endif; ?>
            </li>
            <li>
                <a
                    class="site-nav__link<?php echo customcore_e(customcore_nav_class('cart')); ?>"
                    href="<?php echo customcore_e(customcore_url('cart.php')); ?>"
                >Cart</a>
            </li>
            <?php if ($navHasLogout) : ?>
                <li>
                    <a
                        class="site-nav__link<?php echo customcore_e(customcore_nav_class('logout')); ?>"
                        href="<?php echo customcore_e(customcore_url('logout.php')); ?>"
                    >Log out</a>
                </li>
            <?php endif; ?>
        <?php else : ?>
            <li>
                <a
                    class="site-nav__link<?php echo customcore_e(customcore_nav_class('login')); ?>"
                    href="<?php echo customcore_e(customcore_url('login.php')); ?>"
                >Log in</a>
            </li>
            <li>
                <a
                    class="site-nav__link<?php echo customcore_e(customcore_nav_class('register')); ?>"
                    href="<?php echo customcore_e(customcore_url('register.php')); ?>"
                >Register</a>
            </li>
            <li>
                <a
                    class="site-nav__link<?php echo customcore_e(customcore_nav_class('cart')); ?>"
                    href="<?php echo customcore_e(customcore_url('cart.php')); ?>"
                >Cart</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

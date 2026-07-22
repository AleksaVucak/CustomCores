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
    </ul>
</nav>

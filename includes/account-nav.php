<?php
/**
 * CustomCore — Customer account side navigation.
 *
 * File responsibility:
 *   Renders the private-area account nav used on profile, edit-profile, and
 *   later customer pages (builds, wishlist, orders, consultations).
 *
 * Expected before include:
 *   $accountNavCurrent — string key of the active item (e.g. 'profile').
 *
 * Authentication requirements:
 *   Pages that include this must already call customcore_require_login().
 */

declare(strict_types=1);

if (!function_exists('customcore_url')) {
    require_once __DIR__ . '/functions.php';
}

if (!isset($accountNavCurrent) || !is_string($accountNavCurrent)) {
    $accountNavCurrent = 'profile';
}

$accountNavItems = [
    'profile' => [
        'label' => 'Profile',
        'href' => 'profile.php',
        'ready' => true,
    ],
    'edit-profile' => [
        'label' => 'Edit profile',
        'href' => 'edit-profile.php',
        'ready' => is_file(dirname(__DIR__) . '/edit-profile.php'),
    ],
    'builds' => [
        'label' => 'Saved builds',
        'href' => 'saved-builds.php',
        'ready' => is_file(dirname(__DIR__) . '/saved-builds.php'),
    ],
    'wishlist' => [
        'label' => 'Wishlist',
        'href' => 'wishlist.php',
        'ready' => is_file(dirname(__DIR__) . '/wishlist.php'),
    ],
    'orders' => [
        'label' => 'Orders',
        'href' => 'orders.php',
        'ready' => is_file(dirname(__DIR__) . '/orders.php'),
    ],
    'consultations' => [
        'label' => 'Consultations',
        'href' => 'consultation-history.php',
        'ready' => is_file(dirname(__DIR__) . '/consultation-history.php'),
    ],
    'logout' => [
        'label' => 'Log out',
        'href' => 'logout.php',
        'ready' => is_file(dirname(__DIR__) . '/logout.php'),
    ],
];
?>
<nav class="account-nav" aria-label="Account">
    <p class="account-nav__label">Account</p>
    <ul class="account-nav__list">
        <?php foreach ($accountNavItems as $key => $item) : ?>
            <?php if (empty($item['ready'])) {
                continue;
            } ?>
            <li class="account-nav__item">
                <a
                    class="account-nav__link<?php echo $accountNavCurrent === $key ? ' is-active' : ''; ?>"
                    href="<?php echo customcore_e(customcore_url((string) $item['href'])); ?>"
                    <?php echo $accountNavCurrent === $key ? 'aria-current="page"' : ''; ?>
                ><?php echo customcore_e((string) $item['label']); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

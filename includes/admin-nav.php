<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator secondary navigation.
// Renders the tool nav inside the admin area. Only tools whose PHP files already exist are linked;
// later commits light up automatically when their pages are added.
// Expects:
//   $adminNavCurrent (string), key matching customcore_admin_tools() entries, or "dashboard" for
//     admin/index.php.
// Included from admin pages after customcore_require_admin().

declare(strict_types=1);

if (!function_exists('customcore_admin_tools')) {
    require_once __DIR__ . '/admin.php';
}

if (!isset($adminNavCurrent) || !is_string($adminNavCurrent) || $adminNavCurrent === '') {
    $adminNavCurrent = 'dashboard';
}

$adminTools = customcore_admin_tools();
?>
<nav class="admin-nav" aria-label="Administrator tools">
    <ul class="admin-nav__list">
        <li class="admin-nav__item">
            <a
                class="admin-nav__link<?php echo $adminNavCurrent === 'dashboard' ? ' is-active' : ''; ?>"
                href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>"
                <?php echo $adminNavCurrent === 'dashboard' ? 'aria-current="page"' : ''; ?>
            >Dashboard</a>
        </li>
        <?php foreach ($adminTools as $tool) : ?>
            <li class="admin-nav__item">
                <?php if ($tool['available']) : ?>
                    <a
                        class="admin-nav__link<?php echo $adminNavCurrent === $tool['key'] ? ' is-active' : ''; ?>"
                        href="<?php echo customcore_e(customcore_url($tool['href'])); ?>"
                        <?php echo $adminNavCurrent === $tool['key'] ? 'aria-current="page"' : ''; ?>
                    ><?php echo customcore_e($tool['label']); ?></a>
                <?php else : ?>
                    <span
                        class="admin-nav__link admin-nav__link--soon"
                        title="<?php echo customcore_e('Arrives in commit ' . $tool['commit']); ?>"
                    ><?php echo customcore_e($tool['label']); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

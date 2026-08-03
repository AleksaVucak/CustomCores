<?php
/**
 * CustomCore — Administrator theme switcher (Commit 10.4).
 *
 * File responsibility:
 *   Protected page that lists the three seeded site themes and lets an
 *   administrator choose which one is active sitewide. The selection is stored
 *   in site_settings.active_theme_id and applied by includes/theme.php on every
 *   public and admin page (stylesheet linked last, after main.css / admin.css).
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 *
 * Security:
 *   - Every write requires a valid CSRF token.
 *   - Theme IDs are validated against the themes table and an on-disk CSS file.
 *   - Post/Redirect/Get prevents duplicate submissions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-themes.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

// ---------------------------------------------------------------------------
// Handle theme activation — CSRF + PRG
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect('admin/themes.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $themeId = isset($_POST['theme_id']) ? (int) $_POST['theme_id'] : 0;

    if ($action !== 'activate') {
        customcore_flash_error('Unknown theme action.');
        customcore_redirect('admin/themes.php');
    }

    $result = customcore_admin_theme_set_active($pdo, $themeId);
    if ($result['ok']) {
        customcore_flash_success($result['message'] . ' Public and admin pages now load this stylesheet.');
    } else {
        customcore_flash_error($result['message']);
    }

    customcore_redirect('admin/themes.php');
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
$themes = [];
$activeId = null;
$listError = null;

try {
    $themes = customcore_admin_theme_list($pdo);
    $activeId = customcore_admin_theme_active_id($pdo);
} catch (Throwable $e) {
    $listError = customcore_is_debug()
        ? $e->getMessage()
        : 'Theme settings are temporarily unavailable.';
}

$activeTheme = null;
foreach ($themes as $theme) {
    if ($activeId !== null && (int) $theme['id'] === $activeId) {
        $activeTheme = $theme;
        break;
    }
}

$adminNavCurrent = 'themes';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Themes — CustomCore admin';
$pageDescription = 'Choose the active CustomCore site-wide CSS theme.';
$pageKeywords = 'CustomCore, admin, themes, CSS templates';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-themes" aria-labelledby="admin-themes-heading">
    <header class="admin-page__header">
        <h1 id="admin-themes-heading">Themes</h1>
        <p class="admin-page__intro">
            Choose the active site-wide CSS template. The selection is stored in
            MySQL and applied to every public and administrator page immediately.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('index.php')); ?>">View homepage</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php elseif ($themes === []) : ?>
        <p class="flash flash--warning" role="status">
            No themes are seeded yet. Import
            <code>database/seed-themes.sql</code>
            so the three templates appear here.
        </p>
    <?php else : ?>

        <div class="admin-themes__status" role="status">
            <p class="admin-themes__status-label">Currently active</p>
            <?php if ($activeTheme !== null) : ?>
                <p class="admin-themes__status-value">
                    <strong><?php echo customcore_e((string) $activeTheme['name']); ?></strong>
                    <span class="admin-themes__status-meta">
                        (<code><?php echo customcore_e((string) $activeTheme['slug']); ?></code>
                        ·
                        <?php echo customcore_e((string) $activeTheme['css_file']); ?>
                    </span>
                </p>
            <?php elseif ($activeId !== null) : ?>
                <p class="admin-themes__status-value">
                    Theme id <?php echo (int) $activeId; ?> is selected, but that row is missing.
                    Choose a valid theme below.
                </p>
            <?php else : ?>
                <p class="admin-themes__status-value">
                    No active theme is stored yet. The site is using the seeded /
                    config fallback until you activate one below.
                </p>
            <?php endif; ?>
        </div>

        <div class="admin-themes__grid" role="list">
            <?php foreach ($themes as $theme) : ?>
                <?php
                $isActive = $activeId !== null && (int) $theme['id'] === $activeId;
                $canActivate = !empty($theme['css_exists']);
                $cardClass = 'admin-theme-card';
                if ($isActive) {
                    $cardClass .= ' is-active';
                }
                if (!$canActivate) {
                    $cardClass .= ' is-unavailable';
                }
                ?>
                <article class="<?php echo customcore_e($cardClass); ?>" role="listitem">
                    <header class="admin-theme-card__header">
                        <h2 class="admin-theme-card__title">
                            <?php echo customcore_e((string) $theme['name']); ?>
                        </h2>
                        <div class="admin-theme-card__badges">
                            <?php if ($isActive) : ?>
                                <span class="admin-badge admin-badge--featured">Active</span>
                            <?php endif; ?>
                            <?php if ((int) $theme['is_active_default'] === 1) : ?>
                                <span class="admin-badge admin-badge--muted">Fallback default</span>
                            <?php endif; ?>
                            <?php if (!$canActivate) : ?>
                                <span class="admin-badge admin-badge--danger">CSS missing</span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <p class="admin-theme-card__blurb">
                        <?php echo customcore_e((string) $theme['blurb']); ?>
                    </p>

                    <dl class="admin-dl admin-dl--stacked admin-theme-card__meta">
                        <div>
                            <dt>Slug</dt>
                            <dd><code><?php echo customcore_e((string) $theme['slug']); ?></code></dd>
                        </div>
                        <div>
                            <dt>Stylesheet</dt>
                            <dd><code><?php echo customcore_e((string) $theme['css_file']); ?></code></dd>
                        </div>
                    </dl>

                    <?php if ($isActive) : ?>
                        <p class="admin-theme-card__note">This theme is already active sitewide.</p>
                    <?php elseif ($canActivate) : ?>
                        <form
                            class="admin-inline-form admin-theme-card__form"
                            method="post"
                            action="<?php echo customcore_e(customcore_url('admin/themes.php')); ?>"
                        >
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="theme_id" value="<?php echo (int) $theme['id']; ?>">
                            <button type="submit" class="button button--primary">
                                Activate <?php echo customcore_e((string) $theme['name']); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <p class="admin-theme-card__note admin-theme-card__note--warn">
                            Cannot activate — the CSS file is missing or its path is invalid.
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="admin-page__note">
            Themes layer on top of <code>assets/css/main.css</code> (and
            <code>assets/css/admin.css</code> in this area). Changing the active
            theme updates <code>site_settings.active_theme_id</code>; the shared
            header resolves that value on every page load.
        </p>

    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

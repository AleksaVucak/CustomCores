<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator user list & search.
// Protected account index. Searches users by name or email; filters by role and status; paginates;
// and handles the POST enable/disable toggle via Post/Redirect/Get. Deeper edits (role changes,
// full profile) live on admin/user-edit.php.
// Access: Administrator role (customcore_require_admin()).
// Security:
//   The enable/disable toggle requires a valid CSRF token.
//   Self-lockout and last-admin invariants are enforced before any write.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-users.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();
$currentAdminId = customcore_current_user_id();

/** Build a query string preserving the current filters (+ optional page). */
function customcore_admin_users_query(array $filters, ?int $page = null): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if (($filters['role'] ?? '') !== '') {
        $params['role'] = (string) $filters['role'];
    }
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = (string) $filters['status'];
    }
    if ($page !== null && $page > 1) {
        $params['page'] = $page;
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

// Handle enable/disable toggle, CSRF + guards + PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    $returnFilters = [
        'search' => isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '',
        'role' => isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '',
        'status' => isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '',
    ];
    $returnUrl = 'admin/users.php' . customcore_admin_users_query($returnFilters);

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($returnUrl);
    }

    $targetId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $desiredActive = isset($_POST['activate']) && $_POST['activate'] === '1';

    $target = customcore_admin_user_fetch($pdo, $targetId);
    if ($target === null) {
        customcore_flash_error('That account could not be found.');
        customcore_redirect($returnUrl);
    }

    if (!$desiredActive) {
        [$allowed, $reason] = customcore_admin_user_guard($pdo, $target, $currentAdminId, 'deactivate');
        if (!$allowed) {
            customcore_flash_error($reason);
            customcore_redirect($returnUrl);
        }
    }

    customcore_admin_user_set_active($pdo, $targetId, $desiredActive);
    $name = trim((string) $target['first_name'] . ' ' . (string) $target['last_name']);
    $name = $name !== '' ? $name : (string) $target['email'];
    customcore_flash_success(
        ($desiredActive ? 'Enabled' : 'Disabled') . ' the account for ' . $name . '.'
    );
    customcore_redirect($returnUrl);
}

// Read filters + load list
$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'role' => isset($_GET['role']) && in_array($_GET['role'] ?? '', customcore_admin_user_roles(), true)
        ? (string) $_GET['role']
        : '',
    'status' => isset($_GET['status']) && in_array($_GET['status'] ?? '', ['active', 'inactive'], true)
        ? (string) $_GET['status']
        : '',
];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$counts = ['total' => 0, 'customers' => 0, 'admins' => 0, 'active' => 0, 'inactive' => 0];
$listError = null;

try {
    $result = customcore_admin_user_list($pdo, $filters, $page, 25);
    $counts = customcore_admin_user_counts($pdo);
} catch (Throwable $e) {
    $listError = customcore_is_debug() ? $e->getMessage() : 'Accounts are temporarily unavailable.';
}

$currentQuery = customcore_admin_users_query($filters);

$adminNavCurrent = 'users';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Users | CustomCore Admin';
$pageDescription = 'Search customer and administrator accounts, and disable or re-enable logins.';
$pageKeywords = 'CustomCore, admin, users, accounts';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-users" aria-labelledby="admin-users-heading">
    <header class="admin-page__header">
        <h1 id="admin-users-heading">Users</h1>
        <p class="admin-page__intro">
            Search accounts, review activity, disable or re-enable logins, and change roles.
            Disabled accounts cannot log in but keep their order and review history.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Flash: account list load error -->
    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php endif; ?>

    <!-- Search & filter: name/email, role, status -->
    <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/users.php')); ?>">
        <div class="admin-filter__field">
            <label for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="<?php echo customcore_e($filters['search']); ?>"
                   placeholder="Name or email" maxlength="200">
        </div>
        <div class="admin-filter__field">
            <label for="filter-role">Role</label>
            <select id="filter-role" name="role">
                <option value="">All roles (<?php echo customcore_e((string) $counts['total']); ?>)</option>
                <option value="customer" <?php echo $filters['role'] === 'customer' ? 'selected' : ''; ?>>
                    Customers (<?php echo customcore_e((string) $counts['customers']); ?>)
                </option>
                <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>
                    Administrators (<?php echo customcore_e((string) $counts['admins']); ?>)
                </option>
            </select>
        </div>
        <div class="admin-filter__field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>
                    Active (<?php echo customcore_e((string) $counts['active']); ?>)
                </option>
                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>
                    Disabled (<?php echo customcore_e((string) $counts['inactive']); ?>)
                </option>
            </select>
        </div>
        <div class="admin-filter__actions">
            <button type="submit" class="button button--sm">Apply</button>
            <?php if ($currentQuery !== '') : ?>
                <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/users.php')); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results: empty state or accounts table -->
    <?php if ($result['rows'] === []) : ?>
        <p class="admin-activity__empty">No accounts match your filters.</p>
    <?php else : ?>
        <p class="admin-products__count">
            <?php echo customcore_e((string) $result['total']); ?>
            account<?php echo $result['total'] === 1 ? '' : 's'; ?> found
            · page <?php echo customcore_e((string) $result['page']); ?>
            of <?php echo customcore_e((string) $result['pages']); ?>
        </p>
        <!-- Users table: role, status, orders, account actions -->
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--users">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Orders</th>
                        <th scope="col">Joined</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $user) : ?>
                        <?php
                        $uid = (int) $user['id'];
                        $isActive = (int) $user['is_active'] === 1;
                        $isSelf = $uid === $currentAdminId;
                        $fullName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
                        ?>
                        <tr class="<?php echo $isActive ? '' : 'is-disabled-row'; ?>">
                            <td>
                                <span class="admin-product-cell__name"><?php echo customcore_e($fullName !== '' ? $fullName : 'No name'); ?></span>
                                <?php if ($isSelf) : ?><span class="admin-badge admin-badge--featured">You</span><?php endif; ?>
                            </td>
                            <td><?php echo customcore_e((string) $user['email']); ?></td>
                            <td>
                                <?php if ((string) $user['role'] === 'admin') : ?>
                                    <span class="admin-badge admin-badge--warn">Administrator</span>
                                <?php else : ?>
                                    <span class="admin-badge admin-badge--muted">Customer</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo customcore_e((string) (int) $user['order_count']); ?></td>
                            <td><?php echo customcore_e(date('M j, Y', strtotime((string) $user['created_at']) ?: time())); ?></td>
                            <td>
                                <?php if ($isActive) : ?>
                                    <span class="admin-badge admin-badge--ok">Active</span>
                                <?php else : ?>
                                    <span class="admin-badge admin-badge--danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <a class="button button--ghost button--sm"
                                       href="<?php echo customcore_e(customcore_url('admin/user-edit.php?id=' . $uid)); ?>">Manage</a>
                                    <?php if (!$isSelf) : ?>
                                        <form method="post" action="<?php echo customcore_e(customcore_url('admin/users.php')); ?>" class="admin-inline-form">
                                            <?php echo customcore_csrf_field(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo customcore_e((string) $uid); ?>">
                                            <input type="hidden" name="activate" value="<?php echo $isActive ? '0' : '1'; ?>">
                                            <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                                            <input type="hidden" name="role" value="<?php echo customcore_e($filters['role']); ?>">
                                            <input type="hidden" name="status" value="<?php echo customcore_e($filters['status']); ?>">
                                            <?php if ($isActive) : ?>
                                                <button type="submit" class="button button--danger button--sm"
                                                        onclick="return confirm('Disable this account? The user will not be able to log in.');">Disable</button>
                                            <?php else : ?>
                                                <button type="submit" class="button button--success button--sm">Enable</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <?php if ($result['pages'] > 1) : ?>
            <nav class="admin-pagination" aria-label="User pages">
                <?php if ($result['page'] > 1) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/users.php' . customcore_admin_users_query($filters, $result['page'] - 1))); ?>">← Previous</a>
                <?php endif; ?>
                <span class="admin-pagination__status">
                    Page <?php echo customcore_e((string) $result['page']); ?> of <?php echo customcore_e((string) $result['pages']); ?>
                </span>
                <?php if ($result['page'] < $result['pages']) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/users.php' . customcore_admin_users_query($filters, $result['page'] + 1))); ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

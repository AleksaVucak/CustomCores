<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator user detail & management.
// Protected per-account screen. Shows profile, activity summary, and recent orders, and lets an
// administrator enable/disable the login and change the role via Post/Redirect/Get.
// Access: Administrator role (customcore_require_admin()).
// Security:
//   Both write actions require a valid CSRF token.
//   Role is validated against the users.role ENUM allow-list.
//   Self-lockout and last-active-admin invariants are enforced before writes.
//   The password hash is never loaded or displayed.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-users.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();
$currentAdminId = customcore_current_user_id();

// Resolve the account id (GET on view, POST on write)
$userId = 0;
$rawId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['user_id'] ?? null) : ($_GET['id'] ?? null);
if (is_string($rawId) && ctype_digit($rawId)) {
    $userId = (int) $rawId;
}

if ($userId <= 0) {
    customcore_flash_error('Invalid account ID.');
    customcore_redirect('admin/users.php');
}

$editUrl = 'admin/user-edit.php?id=' . $userId;

// Handle write actions (status / role), CSRF + guards + PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($editUrl);
    }

    $target = customcore_admin_user_fetch($pdo, $userId);
    if ($target === null) {
        customcore_flash_error('That account could not be found.');
        customcore_redirect('admin/users.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_status') {
        $desiredActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
        if ($desiredActive === ((int) $target['is_active'] === 1)) {
            customcore_flash_warning('The account status is already up to date.');
        } else {
            [$allowed, $reason] = $desiredActive
                ? [true, '']
                : customcore_admin_user_guard($pdo, $target, $currentAdminId, 'deactivate');
            if (!$allowed) {
                customcore_flash_error($reason);
            } else {
                customcore_admin_user_set_active($pdo, $userId, $desiredActive);
                customcore_flash_success($desiredActive ? 'Account enabled.' : 'Account disabled.');
            }
        }
    } elseif ($action === 'update_role') {
        $newRole = isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '';
        if (!in_array($newRole, customcore_admin_user_roles(), true)) {
            customcore_flash_error('That role is not valid.');
        } elseif ($newRole === (string) $target['role']) {
            customcore_flash_warning('The role is already "' . customcore_admin_user_role_label($newRole) . '".');
        } elseif ((int) $target['id'] === $currentAdminId) {
            customcore_flash_error('You cannot change your own role.');
        } else {
            $isDemotion = (string) $target['role'] === 'admin' && $newRole === 'customer';
            [$allowed, $reason] = $isDemotion
                ? customcore_admin_user_guard($pdo, $target, $currentAdminId, 'demote')
                : [true, ''];
            if (!$allowed) {
                customcore_flash_error($reason);
            } else {
                customcore_admin_user_set_role($pdo, $userId, $newRole);
                customcore_flash_success('Role changed to "' . customcore_admin_user_role_label($newRole) . '".');
            }
        }
    } else {
        customcore_flash_error('Unknown action.');
    }

    customcore_redirect($editUrl);
}

// Load account for display
$user = customcore_admin_user_fetch($pdo, $userId);
if ($user === null) {
    customcore_flash_error('That account could not be found.');
    customcore_redirect('admin/users.php');
}

$activity = customcore_admin_user_activity($pdo, $userId);
$recentOrders = customcore_admin_user_recent_orders($pdo, $userId, 5);
$fullName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
$isActive = (int) $user['is_active'] === 1;
$isSelf = $userId === $currentAdminId;
$hasAddress = (string) $user['address_line1'] !== '' || (string) $user['city'] !== '';

$adminNavCurrent = 'users';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Manage Account | CustomCore Admin';
$pageDescription = 'Administrator view of a CustomCore account.';
$pageKeywords = 'CustomCore, admin, account';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-user-detail" aria-labelledby="admin-user-heading">
    <header class="admin-page__header">
        <h1 id="admin-user-heading"><?php echo customcore_e($fullName !== '' ? $fullName : (string) $user['email']); ?></h1>
        <p class="admin-page__intro">
            <?php echo customcore_e((string) $user['email']); ?>
            ·
            <?php if ((string) $user['role'] === 'admin') : ?>
                <span class="admin-badge admin-badge--warn">Administrator</span>
            <?php else : ?>
                <span class="admin-badge admin-badge--muted">Customer</span>
            <?php endif; ?>
            <?php if ($isActive) : ?>
                <span class="admin-badge admin-badge--ok">Active</span>
            <?php else : ?>
                <span class="admin-badge admin-badge--danger">Disabled</span>
            <?php endif; ?>
            <?php if ($isSelf) : ?><span class="admin-badge admin-badge--featured">This is you</span><?php endif; ?>
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/users.php')); ?>">← Back to users</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Profile and activity summary cards -->
    <div class="admin-order-detail__grid">
        <section class="admin-card" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="admin-card__title">Profile</h2>
            <dl class="admin-dl">
                <dt>Name</dt>
                <dd><?php echo customcore_e($fullName !== '' ? $fullName : 'No name'); ?></dd>
                <dt>Email</dt>
                <dd><a href="mailto:<?php echo customcore_e((string) $user['email']); ?>"><?php echo customcore_e((string) $user['email']); ?></a></dd>
                <dt>Phone</dt>
                <dd><?php echo customcore_e((string) $user['phone'] !== '' ? (string) $user['phone'] : 'Not given'); ?></dd>
                <dt>Address</dt>
                <dd>
                    <?php if ($hasAddress) : ?>
                        <?php echo customcore_e((string) $user['address_line1']); ?>
                        <?php if ((string) $user['address_line2'] !== '') : ?>
                            <br><?php echo customcore_e((string) $user['address_line2']); ?>
                        <?php endif; ?>
                        <br>
                        <?php echo customcore_e(trim((string) $user['city'] . ', ' . (string) $user['province'] . ' ' . (string) $user['postal_code'])); ?>
                    <?php else : ?>
                        
                    <?php endif; ?>
                </dd>
                <dt>Joined</dt>
                <dd><?php echo customcore_e(date('M j, Y', strtotime((string) $user['created_at']) ?: time())); ?></dd>
            </dl>
        </section>

        <section class="admin-card" aria-labelledby="activity-heading">
            <h2 id="activity-heading" class="admin-card__title">Activity</h2>
            <dl class="admin-dl">
                <dt>Orders</dt>
                <dd><?php echo customcore_e((string) $activity['orders']); ?></dd>
                <dt>Lifetime spend</dt>
                <dd>$<?php echo customcore_e(number_format($activity['orders_total'], 2)); ?></dd>
                <dt>Reviews</dt>
                <dd><?php echo customcore_e((string) $activity['reviews']); ?></dd>
                <dt>Consultations</dt>
                <dd><?php echo customcore_e((string) $activity['consultations']); ?></dd>
                <dt>Wishlist items</dt>
                <dd><?php echo customcore_e((string) $activity['wishlist']); ?></dd>
            </dl>
        </section>
    </div>

    <!-- Self-account notice: cannot change own status or role -->
    <?php if ($isSelf) : ?>
        <p class="flash flash--warning" role="status">
            You are viewing your own account. To prevent accidental lockout, you cannot
            change your own status or role here.
        </p>
    <?php endif; ?>

    <!-- Account status and role management forms -->
    <div class="admin-order-detail__grid admin-order-detail__grid--forms">
        <section class="admin-card" aria-labelledby="status-heading">
            <h2 id="status-heading" class="admin-card__title">Account status</h2>
            <p class="admin-order-detail__note">
                Disabled accounts cannot log in but keep all history intact.
            </p>
            <?php if ($isSelf) : ?>
                <p>You cannot change your own account status.</p>
            <?php else : ?>
                <form method="post" action="<?php echo customcore_e(customcore_url('admin/user-edit.php')); ?>" class="admin-inline-form">
                    <?php echo customcore_csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" value="<?php echo customcore_e((string) $userId); ?>">
                    <input type="hidden" name="is_active" value="<?php echo $isActive ? '0' : '1'; ?>">
                    <?php if ($isActive) : ?>
                        <button type="submit" class="button button--danger button--sm"
                                onclick="return confirm('Disable this account? The user will not be able to log in.');">Disable account</button>
                    <?php else : ?>
                        <button type="submit" class="button button--success button--sm">Enable account</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <section class="admin-card" aria-labelledby="role-heading">
            <h2 id="role-heading" class="admin-card__title">Role</h2>
            <p class="admin-order-detail__note">
                Administrators can access this entire admin area. Grant it carefully.
            </p>
            <?php if ($isSelf) : ?>
                <p>You cannot change your own role.</p>
            <?php else : ?>
                <form method="post" action="<?php echo customcore_e(customcore_url('admin/user-edit.php')); ?>" class="admin-inline-form">
                    <?php echo customcore_csrf_field(); ?>
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?php echo customcore_e((string) $userId); ?>">
                    <label class="form-field" for="user-role">
                        <span class="form-field__label">Account role</span>
                        <select id="user-role" name="role">
                            <?php foreach (customcore_admin_user_roles() as $r) : ?>
                                <option value="<?php echo customcore_e($r); ?>" <?php echo (string) $user['role'] === $r ? 'selected' : ''; ?>>
                                    <?php echo customcore_e(customcore_admin_user_role_label($r)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="button button--sm">Save role</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <!-- Recent orders: empty state or orders table -->
    <section class="admin-card" aria-labelledby="orders-heading">
        <h2 id="orders-heading" class="admin-card__title">Recent orders</h2>
        <?php if ($recentOrders === []) : ?>
            <p class="admin-activity__empty">This account has not placed any orders.</p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Status</th>
                            <th scope="col">Total</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order) : ?>
                            <tr>
                                <td>
                                    <?php echo customcore_e((string) $order['order_number']); ?>
                                    <span class="admin-table__sub"><?php echo customcore_e(customcore_order_format_datetime((string) $order['created_at'])); ?></span>
                                </td>
                                <td>
                                    <span class="order-status <?php echo customcore_e(customcore_order_status_class((string) $order['status'])); ?>">
                                        <?php echo customcore_e(customcore_order_status_label((string) $order['status'])); ?>
                                    </span>
                                </td>
                                <td>$<?php echo customcore_e(number_format((float) $order['total'], 2)); ?></td>
                                <td>
                                    <a class="button button--ghost button--sm"
                                       href="<?php echo customcore_e(customcore_url('admin/order-details.php?id=' . (int) $order['id'])); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

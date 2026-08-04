<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator consultation detail.
// Protected per-request screen. Shows the customer, the full advice request, any attachments (with
// secure admin download links), and lets an admin change the status and write/clear a response via
// Post/Redirect/Get.
// Access: Administrator role (customcore_require_admin()).
// Security:
//   Both write actions require a valid CSRF token.
//   Status is validated against the consultation_requests.status ENUM.
//   All output escaped via customcore_e(); customer text is never trusted.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/consultations.php';
require_once __DIR__ . '/../includes/admin-consultations.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

// Resolve the request id (GET on view, POST on write)
$requestId = 0;
$rawId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['request_id'] ?? null) : ($_GET['id'] ?? null);
if (is_string($rawId) && ctype_digit($rawId)) {
    $requestId = (int) $rawId;
}

if ($requestId <= 0) {
    customcore_flash_error('Invalid consultation ID.');
    customcore_redirect('admin/consultations.php');
}

$detailUrl = 'admin/consultation-details.php?id=' . $requestId;

// Handle write actions (status / response), CSRF + PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($detailUrl);
    }

    $request = customcore_admin_consultation_fetch($pdo, $requestId);
    if ($request === null) {
        customcore_flash_error('That consultation request could not be found.');
        customcore_redirect('admin/consultations.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_status') {
        $newStatus = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
        if ($newStatus === (string) $request['status']) {
            customcore_flash_warning('Status is already "' . customcore_consultation_status_label($newStatus) . '".');
        } elseif (customcore_admin_consultation_update_status($pdo, $requestId, $newStatus)) {
            customcore_flash_success('Status updated to "' . customcore_consultation_status_label($newStatus) . '".');
        } else {
            customcore_flash_error('That status is not valid.');
        }
    } elseif ($action === 'save_response') {
        $response = isset($_POST['admin_response']) && is_string($_POST['admin_response']) ? $_POST['admin_response'] : '';
        $applied = customcore_admin_consultation_save_response(
            $pdo,
            $requestId,
            $response,
            (string) $request['status']
        );
        if (trim($response) === '') {
            customcore_flash_success('Response cleared.');
        } elseif ($applied !== (string) $request['status']) {
            customcore_flash_success('Response saved and the request was marked "'
                . customcore_consultation_status_label($applied) . '".');
        } else {
            customcore_flash_success('Response saved.');
        }
    } else {
        customcore_flash_error('Unknown action.');
    }

    customcore_redirect($detailUrl);
}

// Load request + attachments for display
$request = customcore_admin_consultation_fetch($pdo, $requestId);
if ($request === null) {
    customcore_flash_error('That consultation request could not be found.');
    customcore_redirect('admin/consultations.php');
}

$attachments = customcore_admin_consultation_attachments($pdo, $requestId);
$customerName = trim((string) $request['first_name'] . ' ' . (string) $request['last_name']);
$hasResponse = isset($request['admin_response']) && trim((string) $request['admin_response']) !== '';

$adminNavCurrent = 'consultations';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Consultation #' . $requestId . ' | CustomCore Admin';
$pageDescription = 'Administrator view of a CustomCore consultation request.';
$pageKeywords = 'CustomCore, admin, consultation';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-consult-detail" aria-labelledby="admin-consult-heading">
    <header class="admin-page__header">
        <h1 id="admin-consult-heading">Consultation #<?php echo customcore_e((string) $requestId); ?></h1>
        <p class="admin-page__intro">
            Submitted <?php echo customcore_e(customcore_consultation_format_datetime((string) $request['created_at'])); ?>
            · <span class="consult-status <?php echo customcore_e(customcore_consultation_status_class((string) $request['status'])); ?>">
                <?php echo customcore_e(customcore_consultation_status_label((string) $request['status'])); ?>
            </span>
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/consultations.php')); ?>">← Back to consultations</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Customer summary and attachment download cards -->
    <div class="admin-order-detail__grid">
        <section class="admin-card" aria-labelledby="customer-heading">
            <h2 id="customer-heading" class="admin-card__title">Customer</h2>
            <dl class="admin-dl">
                <dt>Name</dt>
                <dd><?php echo customcore_e($customerName !== '' ? $customerName : 'No name'); ?></dd>
                <dt>Email</dt>
                <dd><a href="mailto:<?php echo customcore_e((string) $request['email']); ?>"><?php echo customcore_e((string) $request['email']); ?></a></dd>
                <dt>Account</dt>
                <dd>
                    <?php if ((int) $request['user_active'] === 1) : ?>
                        <span class="admin-badge admin-badge--ok">Active</span>
                    <?php else : ?>
                        <span class="admin-badge admin-badge--danger">Disabled</span>
                    <?php endif; ?>
                    <a class="admin-table__sub" href="<?php echo customcore_e(customcore_url('admin/user-edit.php?id=' . (int) $request['user_id'])); ?>">Manage account</a>
                </dd>
                <dt>Budget</dt>
                <dd><?php echo customcore_e((string) $request['budget'] !== '' ? (string) $request['budget'] : 'Not given'); ?></dd>
            </dl>
        </section>

        <section class="admin-card" aria-labelledby="attachments-heading">
            <h2 id="attachments-heading" class="admin-card__title">Attachments (<?php echo customcore_e((string) count($attachments)); ?>)</h2>
            <?php if ($attachments === []) : ?>
                <p class="admin-activity__empty">No files were attached to this request.</p>
            <?php else : ?>
                <ul class="admin-attachments">
                    <?php foreach ($attachments as $file) : ?>
                        <li>
                            <a href="<?php echo customcore_e(customcore_url('admin/consultation-attachment.php?id=' . (int) $file['id'])); ?>">
                                <?php echo customcore_e((string) $file['original_filename']); ?>
                            </a>
                            <span class="admin-table__sub">
                                <?php echo customcore_e(customcore_consultation_format_size((int) $file['file_size'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <!-- Full advice request details -->
    <section class="admin-card" aria-labelledby="request-heading">
        <h2 id="request-heading" class="admin-card__title">Request details</h2>
        <dl class="admin-dl admin-dl--stacked">
            <dt>Games played</dt>
            <dd><?php echo nl2br(customcore_e((string) $request['games'])); ?></dd>
            <dt>Software used</dt>
            <dd><?php echo nl2br(customcore_e((string) $request['software'])); ?></dd>
            <dt>Performance goals</dt>
            <dd><?php echo nl2br(customcore_e((string) $request['performance_goals'])); ?></dd>
            <dt>Additional notes</dt>
            <dd>
                <?php if ((string) ($request['notes'] ?? '') !== '') : ?>
                    <?php echo nl2br(customcore_e((string) $request['notes'])); ?>
                <?php else : ?>
                    
                <?php endif; ?>
            </dd>
        </dl>
    </section>

    <!-- Status update and customer response forms -->
    <div class="admin-order-detail__grid admin-order-detail__grid--forms">
        <section class="admin-card" aria-labelledby="status-heading">
            <h2 id="status-heading" class="admin-card__title">Update status</h2>
            <form method="post" action="<?php echo customcore_e(customcore_url('admin/consultation-details.php')); ?>" class="admin-inline-form">
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="request_id" value="<?php echo customcore_e((string) $requestId); ?>">
                <label class="form-field" for="consult-status">
                    <span class="form-field__label">Request status</span>
                    <select id="consult-status" name="status">
                        <?php foreach (customcore_consultation_statuses() as $s) : ?>
                            <option value="<?php echo customcore_e($s); ?>" <?php echo (string) $request['status'] === $s ? 'selected' : ''; ?>>
                                <?php echo customcore_e(customcore_consultation_status_label($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="button button--sm">Save status</button>
            </form>
        </section>

        <section class="admin-card" aria-labelledby="response-heading">
            <h2 id="response-heading" class="admin-card__title">Response to customer</h2>
            <p class="admin-order-detail__note">
                Saving a response marks an open request as “Answered” and is shown to the
                customer in their consultation history.
                <?php if ($hasResponse && isset($request['responded_at']) && $request['responded_at'] !== null) : ?>
                    Last responded <?php echo customcore_e(customcore_consultation_format_datetime((string) $request['responded_at'])); ?>.
                <?php endif; ?>
            </p>
            <form method="post" action="<?php echo customcore_e(customcore_url('admin/consultation-details.php')); ?>" class="admin-inline-form">
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="save_response">
                <input type="hidden" name="request_id" value="<?php echo customcore_e((string) $requestId); ?>">
                <label class="form-field form-field--wide" for="admin-response">
                    <span class="form-field__label">Your response</span>
                    <textarea id="admin-response" name="admin_response" rows="6"
                              maxlength="<?php echo customcore_e((string) CUSTOMCORE_ADMIN_CONSULT_RESPONSE_MAX); ?>"
                              placeholder="Recommend a build, ask a follow-up question, or share next steps."><?php echo customcore_e((string) ($request['admin_response'] ?? '')); ?></textarea>
                </label>
                <button type="submit" class="button button--sm">Save response</button>
            </form>
        </section>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

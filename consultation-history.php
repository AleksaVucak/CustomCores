<?php
/**
 * CustomCore — Consultation Request History (Commit 7.6).
 *
 * File responsibility:
 *   Lists the logged-in customer's PC consultation requests with their status,
 *   the details they submitted, any administrator response, and links to
 *   securely download their own attachments. Ownership is enforced on every
 *   query — customers see only their own requests.
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login). All queries scoped to the
 *   session user_id.
 *
 * Completion test:
 *   Customers see only their requests; foreign IDs are never exposed.
 *
 * Security:
 *   - Ownership enforced via WHERE user_id = :uid on every query.
 *   - Optional status filter whitelisted against the ENUM set.
 *   - Attachment downloads go through consultation-attachment.php (owner-checked).
 *   - All output escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/consultations.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'consultations';

$pageTitle = 'Consultation history — CustomCore';
$pageDescription = 'Track your CustomCore PC consultation requests and our responses.';
$pageKeywords = 'CustomCore, consultation history, PC advice, support requests';
$currentPage = 'consultations';

// ---------------------------------------------------------------------------
// Optional status filter (whitelisted)
// ---------------------------------------------------------------------------

$statusFilter = '';
if (isset($_GET['status']) && is_string($_GET['status'])) {
    $candidate = strtolower(trim($_GET['status']));
    if (in_array($candidate, customcore_consultation_statuses(), true)) {
        $statusFilter = $candidate;
    }
}

// ---------------------------------------------------------------------------
// Load the user's consultation requests (owner-scoped)
// ---------------------------------------------------------------------------

$requests = [];
$attachmentsByRequest = [];
$loadError = null;

try {
    $pdo = customcore_pdo();
    $allRequests = customcore_consultation_list($pdo, $userId);

    // Apply the optional filter in PHP (list is already owner-scoped + small).
    if ($statusFilter !== '') {
        foreach ($allRequests as $req) {
            if ((string) $req['status'] === $statusFilter) {
                $requests[] = $req;
            }
        }
    } else {
        $requests = $allRequests;
    }

    // Load attachments for the visible requests (ownership re-checked in helper).
    foreach ($requests as $req) {
        $rid = (int) $req['id'];
        $attachmentsByRequest[$rid] = customcore_consultation_attachments($pdo, $rid, $userId);
    }

    $totalForUser = count($allRequests);
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your consultation history right now. Please try again later.';
    $totalForUser = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page consultation-history-page" aria-labelledby="consultations-heading">
    <header class="profile-page__header">
        <h1 id="consultations-heading">Consultation history</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/support.html#history')); ?>">Consultation &amp; support guide</a>
            — track your requests and our responses.
        </p>
    </header>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($loadError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($loadError); ?>
                </div>
            <?php elseif ($totalForUser === 0): ?>
                <div class="order-history-empty">
                    <p>You have not requested a consultation yet.</p>
                    <div class="order-history-empty__actions">
                        <a class="button button--primary" href="<?php echo customcore_e(customcore_url('consultation.php')); ?>">
                            Request a consultation
                        </a>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                            Try the PC builder
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <div class="order-history-toolbar">
                    <p class="order-history-toolbar__summary">
                        <?php if ($statusFilter !== ''): ?>
                            Showing
                            <strong><?php echo customcore_e((string) count($requests)); ?></strong>
                            <?php echo customcore_e(customcore_consultation_status_label($statusFilter)); ?>
                            request<?php echo count($requests) === 1 ? '' : 's'; ?>
                            of
                            <strong><?php echo customcore_e((string) $totalForUser); ?></strong>
                            total.
                        <?php else: ?>
                            You have
                            <strong><?php echo customcore_e((string) $totalForUser); ?></strong>
                            consultation request<?php echo $totalForUser === 1 ? '' : 's'; ?>.
                        <?php endif; ?>
                    </p>

                    <nav class="order-history-filters" aria-label="Filter requests by status">
                        <a
                            class="order-history-filters__link<?php echo $statusFilter === '' ? ' is-active' : ''; ?>"
                            href="<?php echo customcore_e(customcore_url('consultation-history.php')); ?>"
                        >All</a>
                        <?php foreach (customcore_consultation_statuses() as $st): ?>
                            <a
                                class="order-history-filters__link<?php echo $statusFilter === $st ? ' is-active' : ''; ?>"
                                href="<?php echo customcore_e(customcore_url('consultation-history.php?status=' . rawurlencode($st))); ?>"
                            ><?php echo customcore_e(customcore_consultation_status_label($st)); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <div class="consultation-history-actions">
                    <a class="button button--primary button--sm" href="<?php echo customcore_e(customcore_url('consultation.php')); ?>">
                        New request
                    </a>
                </div>

                <?php if ($requests === []): ?>
                    <div class="order-history-empty order-history-empty--filtered">
                        <p>
                            No
                            <?php echo customcore_e(customcore_consultation_status_label($statusFilter)); ?>
                            requests found.
                        </p>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('consultation-history.php')); ?>">
                            Show all requests
                        </a>
                    </div>
                <?php else: ?>
                    <ul class="consultation-list">
                        <?php foreach ($requests as $req): ?>
                            <?php
                            $rid = (int) $req['id'];
                            $status = (string) $req['status'];
                            $created = customcore_consultation_format_datetime((string) $req['created_at']);
                            $adminResponse = isset($req['admin_response']) && is_string($req['admin_response'])
                                ? trim($req['admin_response'])
                                : '';
                            $respondedAt = isset($req['responded_at']) && $req['responded_at'] !== null
                                ? customcore_consultation_format_datetime((string) $req['responded_at'])
                                : '';
                            $notes = isset($req['notes']) && is_string($req['notes']) ? trim($req['notes']) : '';
                            $attachments = $attachmentsByRequest[$rid] ?? [];
                            ?>
                            <li class="consultation-card">
                                <header class="consultation-card__header">
                                    <div>
                                        <h2 class="consultation-card__title">
                                            Request #<?php echo customcore_e((string) $rid); ?>
                                        </h2>
                                        <p class="consultation-card__date">
                                            Submitted <?php echo customcore_e($created); ?>
                                        </p>
                                    </div>
                                    <span class="consult-status <?php echo customcore_e(customcore_consultation_status_class($status)); ?>">
                                        <?php echo customcore_e(customcore_consultation_status_label($status)); ?>
                                    </span>
                                </header>

                                <dl class="consultation-card__details">
                                    <div class="consultation-card__row">
                                        <dt>Budget</dt>
                                        <dd><?php echo customcore_e((string) $req['budget']); ?></dd>
                                    </div>
                                    <div class="consultation-card__row">
                                        <dt>Games</dt>
                                        <dd><?php echo nl2br(customcore_e((string) $req['games'])); ?></dd>
                                    </div>
                                    <div class="consultation-card__row">
                                        <dt>Software</dt>
                                        <dd><?php echo nl2br(customcore_e((string) $req['software'])); ?></dd>
                                    </div>
                                    <div class="consultation-card__row">
                                        <dt>Performance goals</dt>
                                        <dd><?php echo nl2br(customcore_e((string) $req['performance_goals'])); ?></dd>
                                    </div>
                                    <?php if ($notes !== ''): ?>
                                        <div class="consultation-card__row">
                                            <dt>Notes</dt>
                                            <dd><?php echo nl2br(customcore_e($notes)); ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>

                                <?php if ($attachments !== []): ?>
                                    <div class="consultation-card__attachments">
                                        <h3 class="consultation-card__subheading">
                                            Attachments (<?php echo customcore_e((string) count($attachments)); ?>)
                                        </h3>
                                        <ul class="consultation-attachments">
                                            <?php foreach ($attachments as $att): ?>
                                                <?php
                                                $attId = (int) $att['id'];
                                                $attName = (string) $att['original_filename'];
                                                $attSize = customcore_consultation_format_size((int) $att['file_size']);
                                                $downloadHref = customcore_url('consultation-attachment.php?id=' . $attId);
                                                ?>
                                                <li class="consultation-attachments__item">
                                                    <a href="<?php echo customcore_e($downloadHref); ?>">
                                                        <?php echo customcore_e($attName); ?>
                                                    </a>
                                                    <span class="consultation-attachments__size">
                                                        <?php echo customcore_e($attSize); ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($adminResponse !== ''): ?>
                                    <div class="consultation-card__response">
                                        <h3 class="consultation-card__subheading">Our response</h3>
                                        <p class="consultation-card__response-body"><?php echo nl2br(customcore_e($adminResponse)); ?></p>
                                        <?php if ($respondedAt !== ''): ?>
                                            <p class="consultation-card__response-meta">
                                                Responded <?php echo customcore_e($respondedAt); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="consultation-card__pending-note">
                                        Our team has not responded yet. We'll email you when there's an update.
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

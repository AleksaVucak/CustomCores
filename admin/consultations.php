<?php
/**
 * CustomCore — Administrator consultation list & search (Commit 9.7).
 *
 * File responsibility:
 *   Protected consultation queue. Searches requests by customer name, email, or
 *   budget; filters by status; paginates; surfaces open/in-progress requests
 *   first; and links to the per-request detail screen.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/consultations.php';
require_once __DIR__ . '/../includes/admin-consultations.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'status' => isset($_GET['status']) && in_array($_GET['status'] ?? '', customcore_consultation_statuses(), true)
        ? (string) $_GET['status']
        : '',
];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$statusCounts = ['all' => 0, 'needs_attention' => 0];
$listError = null;

try {
    $result = customcore_admin_consultation_list($pdo, $filters, $page, 25);
    $statusCounts = customcore_admin_consultation_status_counts($pdo);
} catch (Throwable $e) {
    $listError = customcore_is_debug() ? $e->getMessage() : 'Consultations are temporarily unavailable.';
}

/** Build a query string preserving the current search/status (+ optional page). */
function customcore_admin_consultations_query(array $filters, ?int $page = null): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = (string) $filters['status'];
    }
    if ($page !== null && $page > 1) {
        $params['page'] = $page;
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

$adminNavCurrent = 'consultations';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Consultations — CustomCore admin';
$pageDescription = 'Review and respond to CustomCore PC consultation requests.';
$pageKeywords = 'CustomCore, admin, consultations';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-consultations" aria-labelledby="admin-consultations-heading">
    <header class="admin-page__header">
        <h1 id="admin-consultations-heading">Consultations</h1>
        <p class="admin-page__intro">
            Review PC advice requests, read attachments, respond to customers, and manage
            each request's status. Open and in-progress requests are listed first.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php endif; ?>

    <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/consultations.php')); ?>">
        <div class="admin-filter__field">
            <label for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="<?php echo customcore_e($filters['search']); ?>"
                   placeholder="Customer name, email, or budget" maxlength="200">
        </div>
        <div class="admin-filter__field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses (<?php echo customcore_e((string) ($statusCounts['all'] ?? 0)); ?>)</option>
                <?php foreach (customcore_consultation_statuses() as $s) : ?>
                    <option value="<?php echo customcore_e($s); ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>>
                        <?php echo customcore_e(customcore_consultation_status_label($s)); ?>
                        (<?php echo customcore_e((string) ($statusCounts[$s] ?? 0)); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter__actions">
            <button type="submit" class="button button--sm">Apply</button>
            <?php if ($filters['search'] !== '' || $filters['status'] !== '') : ?>
                <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/consultations.php')); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($result['rows'] === []) : ?>
        <p class="admin-activity__empty">No consultation requests match your filters.</p>
    <?php else : ?>
        <p class="admin-products__count">
            <?php echo customcore_e((string) $result['total']); ?>
            request<?php echo $result['total'] === 1 ? '' : 's'; ?> found
            · <?php echo customcore_e((string) ($statusCounts['needs_attention'] ?? 0)); ?> need attention
            · page <?php echo customcore_e((string) $result['page']); ?>
            of <?php echo customcore_e((string) $result['pages']); ?>
        </p>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--consultations">
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Budget</th>
                        <th scope="col">Files</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row) : ?>
                        <?php $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']); ?>
                        <tr>
                            <td>
                                <span class="admin-product-cell__name"><?php echo customcore_e($name !== '' ? $name : '—'); ?></span>
                                <span class="admin-table__sub"><?php echo customcore_e((string) $row['email']); ?></span>
                            </td>
                            <td><?php echo customcore_e((string) $row['budget'] !== '' ? (string) $row['budget'] : '—'); ?></td>
                            <td><?php echo customcore_e((string) (int) $row['attachment_count']); ?></td>
                            <td><?php echo customcore_e(customcore_consultation_format_datetime((string) $row['created_at'])); ?></td>
                            <td>
                                <span class="consult-status <?php echo customcore_e(customcore_consultation_status_class((string) $row['status'])); ?>">
                                    <?php echo customcore_e(customcore_consultation_status_label((string) $row['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a class="button button--ghost button--sm"
                                   href="<?php echo customcore_e(customcore_url('admin/consultation-details.php?id=' . (int) $row['id'])); ?>">
                                    Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1) : ?>
            <nav class="admin-pagination" aria-label="Consultation pages">
                <?php if ($result['page'] > 1) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/consultations.php' . customcore_admin_consultations_query($filters, $result['page'] - 1))); ?>">← Previous</a>
                <?php endif; ?>
                <span class="admin-pagination__status">
                    Page <?php echo customcore_e((string) $result['page']); ?> of <?php echo customcore_e((string) $result['pages']); ?>
                </span>
                <?php if ($result['page'] < $result['pages']) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/consultations.php' . customcore_admin_consultations_query($filters, $result['page'] + 1))); ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

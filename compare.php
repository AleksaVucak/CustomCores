<?php
/**
 * CustomCore — Product Comparison (Commit 3.7).
 *
 * File responsibility:
 *   Side-by-side comparison of 2–4 selected catalogue products. Fields are
 *   consistent across columns (price, stock, brand, category, spec_*).
 *   Selection is URL-based so comparisons are shareable and work without JS.
 *
 * URL formats:
 *   compare.php?ids=1,2,3
 *   compare.php?ids[]=1&ids[]=2&ids[]=3
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'Compare systems — CustomCore';
$pageDescription = 'Compare CustomCore gaming and creator PCs side by side — price, specs, stock, and tier.';
$pageKeywords = 'CustomCore, compare, gaming PC, specs, side by side';
$currentPage = 'catalogue';

const COMPARE_MIN = 2;
const COMPARE_MAX = 4;

// ---------------------------------------------------------------------------
// Parse selected IDs from GET (array or comma-separated string)
// ---------------------------------------------------------------------------

$requestedIds = [];

if (isset($_GET['ids'])) {
    $rawIds = $_GET['ids'];

    if (is_array($rawIds)) {
        foreach ($rawIds as $raw) {
            if (is_string($raw) || is_int($raw)) {
                $requestedIds[] = (string) $raw;
            }
        }
    } elseif (is_string($rawIds)) {
        $requestedIds = preg_split('/[\s,]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

$selectedIds = [];
foreach ($requestedIds as $raw) {
    $raw = trim((string) $raw);
    if ($raw === '' || !ctype_digit($raw)) {
        continue;
    }
    $id = (int) $raw;
    if ($id < 1 || in_array($id, $selectedIds, true)) {
        continue;
    }
    $selectedIds[] = $id;
    if (count($selectedIds) >= COMPARE_MAX) {
        break;
    }
}

// ---------------------------------------------------------------------------
// Load products
// ---------------------------------------------------------------------------

$comparedProducts = [];
$allProducts = [];
$compareError = null;
$truncatedWarning = null;

if (count($requestedIds) > COMPARE_MAX) {
    $truncatedWarning = 'You can compare up to ' . COMPARE_MAX . ' systems at once. Showing the first '
        . COMPARE_MAX . '.';
}

try {
    $pdo = customcore_pdo();

    // Full catalogue list for the picker form
    $allStmt = $pdo->query(
        'SELECT p.id, p.name, p.base_price, c.name AS category_name
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1
         ORDER BY c.sort_order ASC, p.base_price ASC, p.name ASC'
    );
    $allProducts = $allStmt ? $allStmt->fetchAll() : [];

    if ($selectedIds !== []) {
        $placeholders = [];
        $params = [];
        foreach ($selectedIds as $i => $id) {
            $key = ':id' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT p.id, p.name, p.slug, p.brand, p.short_description, p.base_price,
                       p.stock_quantity, p.spec_cpu, p.spec_gpu, p.spec_ram, p.spec_storage,
                       p.is_featured,
                       c.name AS category_name, c.slug AS category_slug
                FROM products p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.is_active = 1
                  AND p.id IN (' . implode(', ', $placeholders) . ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fetched = $stmt->fetchAll();

        // Preserve the user's selection order
        $byId = [];
        foreach ($fetched as $row) {
            $byId[(int) $row['id']] = $row;
        }
        foreach ($selectedIds as $id) {
            if (isset($byId[$id])) {
                $comparedProducts[] = $byId[$id];
            }
        }

        $missing = count($selectedIds) - count($comparedProducts);
        if ($missing > 0) {
            $truncatedWarning = ($truncatedWarning !== null ? $truncatedWarning . ' ' : '')
                . 'One or more selected products were unavailable and were omitted.';
            // Sync selectedIds to what actually loaded
            $selectedIds = array_map(static function (array $p): int {
                return (int) $p['id'];
            }, $comparedProducts);
        }
    }
} catch (Throwable $exception) {
    $compareError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Comparison data is temporarily unavailable.';
    $comparedProducts = [];
    $allProducts = [];
}

$compareCount = count($comparedProducts);
$canCompare = ($compareCount >= COMPARE_MIN && $compareCount <= COMPARE_MAX);

/**
 * Build a compare URL for a given set of product IDs.
 *
 * @param list<int> $ids
 */
function compare_url(array $ids): string
{
    $ids = array_values(array_unique(array_filter($ids, static function ($id): bool {
        return is_int($id) ? $id > 0 : ((int) $id) > 0;
    })));
    $ids = array_map('intval', $ids);
    $ids = array_slice($ids, 0, COMPARE_MAX);

    if ($ids === []) {
        return customcore_url('compare.php');
    }

    return customcore_url('compare.php?ids=' . implode(',', $ids));
}

/**
 * @param list<int> $ids
 * @return list<int>
 */
function compare_without(array $ids, int $removeId): array
{
    return array_values(array_filter($ids, static function (int $id) use ($removeId): bool {
        return $id !== $removeId;
    }));
}

$compareRows = [
    'brand'    => 'Brand',
    'category' => 'Category',
    'price'    => 'Base price',
    'stock'    => 'Availability',
    'cpu'      => 'Processor',
    'gpu'      => 'Graphics',
    'ram'      => 'Memory',
    'storage'  => 'Storage',
    'featured' => 'Featured',
    'blurb'    => 'Summary',
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section compare-page" aria-labelledby="compare-heading">
    <header class="compare-page__header">
        <h1 id="compare-heading">Compare systems</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/catalogue.html')); ?>">Catalogue guide</a>
        </p>
        <p class="compare-page__intro">
            Select <?php echo customcore_e((string) COMPARE_MIN); ?>–
            <?php echo customcore_e((string) COMPARE_MAX); ?> CustomCore systems
            and review the same fields side by side — price, stock, tier, and default specs.
        </p>
    </header>

    <?php if ($compareError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($compareError); ?>
        </div>
    <?php endif; ?>

    <?php if ($truncatedWarning !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($truncatedWarning); ?>
        </div>
    <?php endif; ?>

    <?php if ($allProducts !== []) : ?>
        <form
            class="compare-picker"
            method="get"
            action="<?php echo customcore_e(customcore_url('compare.php')); ?>"
        >
            <h2 class="compare-picker__heading">Choose systems to compare</h2>
            <p class="compare-picker__hint">
                Tick <?php echo customcore_e((string) COMPARE_MIN); ?> to
                <?php echo customcore_e((string) COMPARE_MAX); ?> products, then compare.
                You can also select systems from the
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">catalogue</a>.
            </p>

            <div class="compare-picker__list" role="group" aria-label="Products available to compare">
                <?php foreach ($allProducts as $item) : ?>
                    <?php
                    $itemId = (int) ($item['id'] ?? 0);
                    $itemName = (string) ($item['name'] ?? 'Product');
                    $itemCat = (string) ($item['category_name'] ?? '');
                    $itemPrice = number_format((float) ($item['base_price'] ?? 0), 2);
                    $isChecked = in_array($itemId, $selectedIds, true);
                    ?>
                    <label class="compare-picker__item">
                        <input
                            type="checkbox"
                            name="ids[]"
                            value="<?php echo customcore_e((string) $itemId); ?>"
                            <?php echo $isChecked ? ' checked' : ''; ?>
                        >
                        <span class="compare-picker__name"><?php echo customcore_e($itemName); ?></span>
                        <span class="compare-picker__meta">
                            <?php echo customcore_e($itemCat); ?>
                            · $<?php echo customcore_e($itemPrice); ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="compare-picker__actions">
                <button type="submit" class="button">Compare selected</button>
                <?php if ($selectedIds !== []) : ?>
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('compare.php')); ?>">Clear selection</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($compareCount > 0 && $compareCount < COMPARE_MIN) : ?>
        <p class="empty-state">
            Select at least <?php echo customcore_e((string) COMPARE_MIN); ?> systems to see a side-by-side comparison.
            You currently have <strong><?php echo customcore_e((string) $compareCount); ?></strong> selected.
        </p>
    <?php elseif ($canCompare) : ?>
        <div class="compare-toolbar">
            <p class="compare-toolbar__count" aria-live="polite">
                Comparing <strong><?php echo customcore_e((string) $compareCount); ?></strong> systems
            </p>
            <p class="compare-toolbar__note">
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Back to catalogue</a>
            </p>
        </div>

        <div class="compare-table-wrap" tabindex="0" role="region" aria-label="Product comparison table">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th scope="col" class="compare-table__feature">Feature</th>
                        <?php foreach ($comparedProducts as $product) : ?>
                            <?php
                            $pid = (int) $product['id'];
                            $pname = (string) $product['name'];
                            $purl = customcore_url('product.php?id=' . $pid);
                            $removeUrl = compare_url(compare_without($selectedIds, $pid));
                            ?>
                            <th scope="col" class="compare-table__product">
                                <a href="<?php echo customcore_e($purl); ?>"><?php echo customcore_e($pname); ?></a>
                                <a class="compare-table__remove" href="<?php echo customcore_e($removeUrl); ?>">Remove</a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compareRows as $rowKey => $rowLabel) : ?>
                        <tr>
                            <th scope="row"><?php echo customcore_e($rowLabel); ?></th>
                            <?php foreach ($comparedProducts as $product) : ?>
                                <?php
                                $stock = (int) ($product['stock_quantity'] ?? 0);
                                $cell = '';
                                switch ($rowKey) {
                                    case 'brand':
                                        $cell = (string) ($product['brand'] ?? '');
                                        break;
                                    case 'category':
                                        $cell = (string) ($product['category_name'] ?? '');
                                        break;
                                    case 'price':
                                        $cell = '$' . number_format((float) ($product['base_price'] ?? 0), 2);
                                        break;
                                    case 'stock':
                                        $cell = $stock > 0
                                            ? 'In stock (' . $stock . ')'
                                            : 'Out of stock';
                                        break;
                                    case 'cpu':
                                        $cell = (string) ($product['spec_cpu'] ?? '');
                                        break;
                                    case 'gpu':
                                        $cell = (string) ($product['spec_gpu'] ?? '');
                                        break;
                                    case 'ram':
                                        $cell = (string) ($product['spec_ram'] ?? '');
                                        break;
                                    case 'storage':
                                        $cell = (string) ($product['spec_storage'] ?? '');
                                        break;
                                    case 'featured':
                                        $cell = !empty($product['is_featured']) ? 'Yes' : 'No';
                                        break;
                                    case 'blurb':
                                        $cell = (string) ($product['short_description'] ?? '');
                                        break;
                                }
                                if ($cell === '') {
                                    $cell = '—';
                                }
                                $extraClass = '';
                                if ($rowKey === 'stock' && $stock <= 0) {
                                    $extraClass = ' is-out';
                                }
                                if ($rowKey === 'price') {
                                    $extraClass = ' compare-table__price';
                                }
                                ?>
                                <td class="<?php echo customcore_e(trim($extraClass)); ?>">
                                    <?php echo customcore_e($cell); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="compare-table__actions-row">
                        <th scope="row"><span class="visually-hidden">Actions</span></th>
                        <?php foreach ($comparedProducts as $product) : ?>
                            <?php $purl = customcore_url('product.php?id=' . (int) $product['id']); ?>
                            <td>
                                <a class="button" href="<?php echo customcore_e($purl); ?>">View details</a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php elseif ($compareCount === 0 && $compareError === null && $allProducts === []) : ?>
        <p class="empty-state">
            No active products are available to compare yet.
            Import the catalogue seeds, then return here.
        </p>
    <?php elseif ($compareCount === 0 && $compareError === null) : ?>
        <p class="empty-state">
            No systems selected yet. Use the checklist above, or pick products from the
            <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">catalogue</a>.
        </p>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

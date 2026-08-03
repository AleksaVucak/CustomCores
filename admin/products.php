<?php
/**
 * CustomCore — Administrator product list & search (Commit 9.2).
 *
 * File responsibility:
 *   Protected catalogue management index. Lists products with search and
 *   category/status filters, links to the add/edit screens, and handles the
 *   POST enable/disable (soft delete) toggle via Post/Redirect/Get.
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
require_once __DIR__ . '/../includes/admin-products.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

/**
 * Rebuild the current filter query string so redirects keep the admin's view.
 */
function customcore_admin_products_query(array $filters): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if ((int) ($filters['category_id'] ?? 0) > 0) {
        $params['category'] = (int) $filters['category_id'];
    }
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = (string) $filters['status'];
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

$pdo = customcore_pdo();

// --- Handle enable/disable toggle (state change) -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    // Preserve the admin's active filters across the redirect.
    $returnFilters = [
        'search' => isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '',
        'category_id' => isset($_POST['category']) ? (int) $_POST['category'] : 0,
        'status' => isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '',
    ];
    $returnUrl = 'admin/products.php' . customcore_admin_products_query($returnFilters);

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($returnUrl);
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

    if ($productId < 1 || !in_array($action, ['enable', 'disable'], true)) {
        customcore_flash_error('That action could not be completed.');
        customcore_redirect($returnUrl);
    }

    $product = customcore_admin_product_fetch($pdo, $productId);
    if ($product === null) {
        customcore_flash_error('That product no longer exists.');
        customcore_redirect($returnUrl);
    }

    try {
        customcore_admin_product_set_active($pdo, $productId, $action === 'enable');
        if ($action === 'enable') {
            customcore_flash_success('“' . (string) $product['name'] . '” is now visible in the catalogue.');
        } else {
            customcore_flash_success('“' . (string) $product['name'] . '” has been disabled and hidden from the catalogue.');
        }
    } catch (Throwable $exception) {
        customcore_flash_error(
            customcore_is_debug()
                ? $exception->getMessage()
                : 'The product status could not be updated.'
        );
    }

    customcore_redirect($returnUrl);
}

// --- Read filters and load the list -----------------------------------------
$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'category_id' => isset($_GET['category']) ? (int) $_GET['category'] : 0,
    'status' => isset($_GET['status']) && in_array($_GET['status'] ?? '', ['active', 'inactive'], true)
        ? (string) $_GET['status']
        : '',
];

$products = [];
$categories = [];
$listError = null;

try {
    $categories = customcore_admin_product_categories($pdo);
    $products = customcore_admin_product_list($pdo, $filters);
} catch (Throwable $exception) {
    $listError = customcore_is_debug()
        ? $exception->getMessage()
        : 'The product list is temporarily unavailable.';
}

$adminNavCurrent = 'products';
$loadAdminCss = true;

$pageTitle = 'Manage products — CustomCore admin';
$pageDescription = 'Add, edit, price, stock, and disable CustomCore catalogue products.';
$pageKeywords = 'CustomCore, admin, products, catalogue management';
$currentPage = 'admin';

$currentQuery = customcore_admin_products_query($filters);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Product management: filters, toolbar, and catalogue table -->
<section class="content-section admin-page admin-products" aria-labelledby="admin-products-heading">
    <header class="admin-page__header">
        <h1 id="admin-products-heading">Manage products</h1>
        <p class="admin-page__intro">
            Search, edit, and price catalogue systems. Disabling a product hides it
            from the store while preserving its order and review history.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Toolbar: add-product entry point -->
    <div class="admin-toolbar">
        <a class="button" href="<?php echo customcore_e(customcore_url('admin/product-add.php')); ?>">
            + Add product
        </a>
    </div>

    <!-- Filters: search + category + status (GET) -->
    <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">
        <div class="admin-filter__field">
            <label for="filter-q">Search</label>
            <input
                type="search"
                id="filter-q"
                name="q"
                value="<?php echo customcore_e($filters['search']); ?>"
                placeholder="Name, brand, or slug"
                maxlength="200"
            >
        </div>
        <div class="admin-filter__field">
            <label for="filter-category">Category</label>
            <select id="filter-category" name="category">
                <option value="0">All categories</option>
                <?php foreach ($categories as $category) : ?>
                    <option
                        value="<?php echo customcore_e((string) $category['id']); ?>"
                        <?php echo $filters['category_id'] === $category['id'] ? 'selected' : ''; ?>
                    ><?php echo customcore_e($category['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter__field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Disabled</option>
            </select>
        </div>
        <div class="admin-filter__actions">
            <button type="submit" class="button button--sm">Apply</button>
            <?php if ($currentQuery !== '') : ?>
                <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results: error banner, empty state, or product listing -->
    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php elseif ($products === []) : ?>
        <p class="admin-activity__empty">
            No products match your filters.
            <?php if ($currentQuery !== '') : ?>
                <a href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Clear filters</a>.
            <?php endif; ?>
        </p>
    <?php else : ?>
        <p class="admin-products__count">
            Showing <?php echo customcore_e((string) count($products)); ?>
            product<?php echo count($products) === 1 ? '' : 's'; ?>.
        </p>
        <!-- Product table: image, category, price, stock, status, and row actions -->
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--products">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col">Price</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product) : ?>
                        <?php
                        $thumbUrl = customcore_product_image_url($product['image_path'] ?? null);
                        $isActive = (int) $product['is_active'] === 1;
                        $stock = (int) $product['stock_quantity'];
                        $lowStock = $stock > 0 && $stock <= customcore_admin_low_stock_threshold();
                        ?>
                        <tr>
                            <td>
                                <div class="admin-product-cell">
                                    <?php if ($thumbUrl !== null) : ?>
                                        <img
                                            class="admin-product-cell__thumb"
                                            src="<?php echo customcore_e($thumbUrl); ?>"
                                            alt=""
                                            width="56"
                                            height="56"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    <?php else : ?>
                                        <span class="admin-product-cell__thumb admin-product-cell__thumb--empty" aria-hidden="true">No image</span>
                                    <?php endif; ?>
                                    <span class="admin-product-cell__body">
                                        <span class="admin-product-cell__name"><?php echo customcore_e((string) $product['name']); ?></span>
                                        <span class="admin-table__sub">
                                            <?php echo customcore_e((string) $product['brand']); ?>
                                            <?php if ((int) $product['is_featured'] === 1) : ?>
                                                · <span class="admin-badge admin-badge--featured">Featured</span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo customcore_e((string) ($product['category_name'] ?? '—')); ?></td>
                            <td>$<?php echo customcore_e(number_format((float) $product['base_price'], 2)); ?></td>
                            <td>
                                <?php echo customcore_e((string) $stock); ?>
                                <?php if ($stock === 0) : ?>
                                    <span class="admin-badge admin-badge--danger">Out</span>
                                <?php elseif ($lowStock) : ?>
                                    <span class="admin-badge admin-badge--warn">Low</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isActive) : ?>
                                    <span class="admin-badge admin-badge--ok">Active</span>
                                <?php else : ?>
                                    <span class="admin-badge admin-badge--muted">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <a
                                        class="button button--ghost button--sm"
                                        href="<?php echo customcore_e(customcore_url('admin/product-edit.php?id=' . (int) $product['id'])); ?>"
                                    >Edit</a>
                                    <a
                                        class="button button--ghost button--sm"
                                        href="<?php echo customcore_e(customcore_url('admin/product-options.php?product_id=' . (int) $product['id'])); ?>"
                                    >Options</a>
                                    <?php if ($isActive) : ?>
                                        <a
                                            class="admin-actions__link"
                                            href="<?php echo customcore_e(customcore_url('product.php?slug=' . rawurlencode((string) $product['slug']))); ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >View</a>
                                    <?php endif; ?>
                                    <form
                                        class="admin-inline-form"
                                        method="post"
                                        action="<?php echo customcore_e(customcore_url('admin/products.php')); ?>"
                                        onsubmit="return confirm('<?php echo $isActive ? 'Disable' : 'Enable'; ?> this product?');"
                                    >
                                        <?php echo customcore_csrf_field(); ?>
                                        <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $product['id']); ?>">
                                        <input type="hidden" name="action" value="<?php echo $isActive ? 'disable' : 'enable'; ?>">
                                        <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                                        <input type="hidden" name="category" value="<?php echo customcore_e((string) $filters['category_id']); ?>">
                                        <input type="hidden" name="status" value="<?php echo customcore_e($filters['status']); ?>">
                                        <button
                                            type="submit"
                                            class="button button--sm <?php echo $isActive ? 'button--danger' : 'button--success'; ?>"
                                        ><?php echo $isActive ? 'Disable' : 'Enable'; ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';

<?php
/**
 * CustomCore — Catalogue with Filters, Sorting & Compare entry (Commits 3.3–3.7).
 *
 * File responsibility:
 *   Displays active products with category, price range, brand, and in-stock
 *   filters plus sort controls. Product cards include checkboxes that submit
 *   selected IDs to compare.php (Commit 3.7). All filters work individually
 *   and combined via PDO prepared statements.
 *
 * Filters (GET parameters):
 *   ?category=slug        — tier filter
 *   ?brand=CustomCore      — brand filter
 *   ?price_min=500         — minimum price
 *   ?price_max=2000        — maximum price
 *   ?in_stock=1            — only products with stock > 0
 *   ?sort=price_asc        — sort order
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'Catalogue — CustomCore Gaming PCs';
$pageDescription = 'Browse all CustomCore configurable prebuilt gaming and creator PCs with filters and sorting.';
$pageKeywords = 'CustomCore, catalogue, gaming PC, prebuilt, Budget, Esports, High-Performance, Creator';
$currentPage = 'catalogue';

// ---------------------------------------------------------------------------
// Collect and sanitize filter inputs
// ---------------------------------------------------------------------------

$filterCategory = '';
if (isset($_GET['category']) && is_string($_GET['category'])) {
    $filterCategory = strtolower(trim($_GET['category']));
    if ($filterCategory !== '' && !preg_match('/^[a-z0-9\-]+$/', $filterCategory)) {
        $filterCategory = '';
    }
}

$filterBrand = '';
if (isset($_GET['brand']) && is_string($_GET['brand'])) {
    $filterBrand = trim($_GET['brand']);
}

$filterPriceMin = '';
if (isset($_GET['price_min']) && is_string($_GET['price_min']) && $_GET['price_min'] !== '') {
    $val = (float) $_GET['price_min'];
    if ($val >= 0) {
        $filterPriceMin = (string) $val;
    }
}

$filterPriceMax = '';
if (isset($_GET['price_max']) && is_string($_GET['price_max']) && $_GET['price_max'] !== '') {
    $val = (float) $_GET['price_max'];
    if ($val > 0) {
        $filterPriceMax = (string) $val;
    }
}

$filterInStock = false;
if (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') {
    $filterInStock = true;
}

$allowedSorts = [
    'featured'   => 'p.is_featured DESC, p.base_price ASC',
    'price_asc'  => 'p.base_price ASC, p.name ASC',
    'price_desc' => 'p.base_price DESC, p.name ASC',
    'name_asc'   => 'p.name ASC',
    'name_desc'  => 'p.name DESC',
    'newest'     => 'p.created_at DESC, p.name ASC',
];

$sortKey = 'featured';
if (isset($_GET['sort']) && is_string($_GET['sort']) && isset($allowedSorts[$_GET['sort']])) {
    $sortKey = $_GET['sort'];
}

$hasActiveFilters = ($filterCategory !== '' || $filterBrand !== ''
    || $filterPriceMin !== '' || $filterPriceMax !== '' || $filterInStock);

// ---------------------------------------------------------------------------
// Query data
// ---------------------------------------------------------------------------

$products = [];
$categories = [];
$brands = [];
$activeCategory = null;
$catalogueError = null;

try {
    $pdo = customcore_pdo();

    // Categories for tier chips + filter dropdown
    $categoryStmt = $pdo->query(
        'SELECT id, name, slug, description, sort_order
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $categories = $categoryStmt ? $categoryStmt->fetchAll() : [];

    // Brands for filter dropdown
    $brandStmt = $pdo->query(
        'SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand ASC'
    );
    $brands = $brandStmt ? array_column($brandStmt->fetchAll(), 'brand') : [];

    // Resolve category slug
    if ($filterCategory !== '') {
        foreach ($categories as $category) {
            if ((string) ($category['slug'] ?? '') === $filterCategory) {
                $activeCategory = $category;
                break;
            }
        }
        if ($activeCategory === null) {
            $filterCategory = '';
        }
    }

    // Build filtered query with prepared statements
    $sql = 'SELECT p.id, p.name, p.slug, p.brand, p.short_description, p.base_price,
                   p.stock_quantity, p.image_path, p.spec_cpu, p.spec_gpu,
                   p.spec_ram, p.spec_storage, p.is_featured,
                   c.name AS category_name, c.slug AS category_slug
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1';

    $params = [];

    if ($activeCategory !== null) {
        $sql .= ' AND c.slug = :cat_slug';
        $params[':cat_slug'] = (string) $activeCategory['slug'];
    }

    if ($filterBrand !== '') {
        $sql .= ' AND p.brand = :brand';
        $params[':brand'] = $filterBrand;
    }

    if ($filterPriceMin !== '') {
        $sql .= ' AND p.base_price >= :price_min';
        $params[':price_min'] = (float) $filterPriceMin;
    }

    if ($filterPriceMax !== '') {
        $sql .= ' AND p.base_price <= :price_max';
        $params[':price_max'] = (float) $filterPriceMax;
    }

    if ($filterInStock) {
        $sql .= ' AND p.stock_quantity > 0';
    }

    $sql .= ' ORDER BY ' . $allowedSorts[$sortKey];

    $productStmt = $pdo->prepare($sql);
    $productStmt->execute($params);
    $products = $productStmt->fetchAll();
} catch (Throwable $exception) {
    $catalogueError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Catalogue data is temporarily unavailable.';
}

$productCount = count($products);

// ---------------------------------------------------------------------------
// Helper: build a filter URL preserving current state
// ---------------------------------------------------------------------------

function catalogue_filter_url(array $overrides = []): string
{
    $base = [
        'category'  => $GLOBALS['filterCategory'],
        'brand'     => $GLOBALS['filterBrand'],
        'price_min' => $GLOBALS['filterPriceMin'],
        'price_max' => $GLOBALS['filterPriceMax'],
        'in_stock'  => $GLOBALS['filterInStock'] ? '1' : '',
        'sort'      => $GLOBALS['sortKey'],
    ];

    $merged = array_merge($base, $overrides);

    // Remove empty values
    $query = array_filter($merged, static function ($v) {
        return $v !== '' && $v !== null;
    });

    // Remove defaults
    if (isset($query['sort']) && $query['sort'] === 'featured') {
        unset($query['sort']);
    }

    $qs = http_build_query($query);
    $url = customcore_url('catalogue.php');

    return $qs !== '' ? $url . '?' . $qs : $url;
}

$sortLabels = [
    'featured'   => 'Featured',
    'price_asc'  => 'Price: low → high',
    'price_desc' => 'Price: high → low',
    'name_asc'   => 'Name: A → Z',
    'name_desc'  => 'Name: Z → A',
    'newest'     => 'Newest first',
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section catalogue-page" aria-labelledby="catalogue-heading">
    <header class="catalogue-page__header">
        <h1 id="catalogue-heading">Catalogue</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/catalogue.html')); ?>">Catalogue guide</a>
        </p>
        <p class="catalogue-page__intro">
            Configurable prebuilt gaming and creator PCs loaded from MySQL.
            Use filters, sorting, and search to find your system.
        </p>
    </header>

    <form class="search-form search-form--compact" method="get" action="<?php echo customcore_e(customcore_url('search.php')); ?>" role="search">
        <label class="search-form__label" for="catalogue-search-q">Search the catalogue</label>
        <div class="search-form__row">
            <input
                type="search"
                id="catalogue-search-q"
                name="q"
                class="search-form__input"
                placeholder="Search by name, brand, tier, or specs"
                maxlength="100"
                autocomplete="off"
            >
            <button type="submit" class="button">Search</button>
        </div>
    </form>

    <?php if ($catalogueError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($catalogueError); ?>
        </div>
    <?php endif; ?>

    <div class="layout-split layout-split--catalogue">
        <aside class="catalogue-filters" aria-label="Product filters">
            <form method="get" action="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                <h2 class="catalogue-filters__heading">Filters</h2>

                <div class="filter-group">
                    <label class="filter-group__label" for="filter-category">Category</label>
                    <select id="filter-category" name="category" class="filter-group__select">
                        <option value="">All tiers</option>
                        <?php foreach ($categories as $cat) : ?>
                            <?php $catSlug = (string) ($cat['slug'] ?? ''); ?>
                            <option
                                value="<?php echo customcore_e($catSlug); ?>"
                                <?php echo $filterCategory === $catSlug ? ' selected' : ''; ?>
                            ><?php echo customcore_e((string) ($cat['name'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-group__label" for="filter-brand">Brand</label>
                    <select id="filter-brand" name="brand" class="filter-group__select">
                        <option value="">All brands</option>
                        <?php foreach ($brands as $brand) : ?>
                            <option
                                value="<?php echo customcore_e($brand); ?>"
                                <?php echo $filterBrand === $brand ? ' selected' : ''; ?>
                            ><?php echo customcore_e($brand); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <fieldset class="filter-group">
                    <legend class="filter-group__label">Price range</legend>
                    <div class="filter-group__row">
                        <label class="filter-group__sub-label" for="filter-price-min">Min $</label>
                        <input
                            type="number"
                            id="filter-price-min"
                            name="price_min"
                            class="filter-group__input"
                            value="<?php echo customcore_e($filterPriceMin); ?>"
                            min="0"
                            step="50"
                            placeholder="0"
                        >
                        <label class="filter-group__sub-label" for="filter-price-max">Max $</label>
                        <input
                            type="number"
                            id="filter-price-max"
                            name="price_max"
                            class="filter-group__input"
                            value="<?php echo customcore_e($filterPriceMax); ?>"
                            min="0"
                            step="50"
                            placeholder="any"
                        >
                    </div>
                </fieldset>

                <div class="filter-group">
                    <label class="filter-group__checkbox">
                        <input
                            type="checkbox"
                            name="in_stock"
                            value="1"
                            <?php echo $filterInStock ? ' checked' : ''; ?>
                        >
                        In stock only
                    </label>
                </div>

                <div class="filter-group">
                    <label class="filter-group__label" for="filter-sort">Sort by</label>
                    <select id="filter-sort" name="sort" class="filter-group__select">
                        <?php foreach ($sortLabels as $key => $label) : ?>
                            <option
                                value="<?php echo customcore_e($key); ?>"
                                <?php echo $sortKey === $key ? ' selected' : ''; ?>
                            ><?php echo customcore_e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group filter-group--actions">
                    <button type="submit" class="button">Apply</button>
                    <?php if ($hasActiveFilters) : ?>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Clear all</a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <div class="catalogue-results">
            <?php if ($categories !== []) : ?>
                <nav class="catalogue-tiers" aria-label="Quick tier filter">
                    <a
                        class="catalogue-tiers__link<?php echo $filterCategory === '' ? ' is-active' : ''; ?>"
                        href="<?php echo customcore_e(catalogue_filter_url(['category' => ''])); ?>"
                    >All</a>
                    <?php foreach ($categories as $cat) : ?>
                        <?php
                        $slug = (string) ($cat['slug'] ?? '');
                        $name = (string) ($cat['name'] ?? '');
                        $isActive = $filterCategory === $slug;
                        ?>
                        <a
                            class="catalogue-tiers__link<?php echo $isActive ? ' is-active' : ''; ?>"
                            href="<?php echo customcore_e(catalogue_filter_url(['category' => $slug])); ?>"
                        ><?php echo customcore_e($name); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <div class="catalogue-toolbar">
                <p class="catalogue-toolbar__count" aria-live="polite">
                    <strong><?php echo customcore_e((string) $productCount); ?></strong>
                    <?php echo $productCount === 1 ? 'system' : 'systems'; ?>
                    <?php if ($activeCategory !== null) : ?>
                        in <strong><?php echo customcore_e((string) $activeCategory['name']); ?></strong>
                    <?php endif; ?>
                    <?php if ($filterBrand !== '') : ?>
                        by <strong><?php echo customcore_e($filterBrand); ?></strong>
                    <?php endif; ?>
                    <?php if ($filterPriceMin !== '' || $filterPriceMax !== '') : ?>
                        (<?php
                        if ($filterPriceMin !== '' && $filterPriceMax !== '') {
                            echo '$' . customcore_e($filterPriceMin) . ' – $' . customcore_e($filterPriceMax);
                        } elseif ($filterPriceMin !== '') {
                            echo 'from $' . customcore_e($filterPriceMin);
                        } else {
                            echo 'up to $' . customcore_e($filterPriceMax);
                        }
                        ?>)
                    <?php endif; ?>
                </p>
                <p class="catalogue-toolbar__sort-label">
                    Sorted by: <strong><?php echo customcore_e($sortLabels[$sortKey]); ?></strong>
                    · <a href="<?php echo customcore_e(customcore_url('compare.php')); ?>">Compare</a>
                    · <a href="<?php echo customcore_e(customcore_url('reviews.php')); ?>">Reviews</a>
                </p>
            </div>

            <?php if ($activeCategory !== null && (string) ($activeCategory['description'] ?? '') !== '') : ?>
                <p class="catalogue-tier-blurb">
                    <?php echo customcore_e((string) $activeCategory['description']); ?>
                </p>
            <?php endif; ?>

            <?php if ($products === [] && $catalogueError === null) : ?>
                <p class="empty-state">
                    No systems match the current filters.
                    <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Clear all filters</a>
                    to see every product.
                </p>
            <?php elseif ($products !== []) : ?>
                <form
                    class="catalogue-compare-form"
                    method="get"
                    action="<?php echo customcore_e(customcore_url('compare.php')); ?>"
                >
                    <div class="catalogue-compare-bar">
                        <p class="catalogue-compare-bar__text">
                            Tick 2–4 systems, then compare them side by side.
                        </p>
                        <button type="submit" class="button">Compare selected</button>
                    </div>

                    <div class="card-grid catalogue-grid">
                        <?php foreach ($products as $product) : ?>
                            <?php
                            $productId = (int) ($product['id'] ?? 0);
                            $productName = (string) ($product['name'] ?? 'Product');
                            $productUrl = customcore_url('product.php?id=' . $productId);
                            $price = number_format((float) ($product['base_price'] ?? 0), 2);
                            $categoryName = (string) ($product['category_name'] ?? '');
                            $short = (string) ($product['short_description'] ?? '');
                            $specCpu = (string) ($product['spec_cpu'] ?? '');
                            $specGpu = (string) ($product['spec_gpu'] ?? '');
                            $specRam = (string) ($product['spec_ram'] ?? '');
                            $stock = (int) ($product['stock_quantity'] ?? 0);
                            $isFeatured = !empty($product['is_featured']);
                            $inStock = $stock > 0;
                            $compareInputId = 'compare-product-' . $productId;
                            ?>
                            <article class="card product-card">
                                <div class="product-card__media" aria-hidden="true">
                                    <span class="product-card__media-label">PC</span>
                                </div>
                                <?php if ($isFeatured) : ?>
                                    <p class="product-card__badge">Featured</p>
                                <?php endif; ?>
                                <h3 class="card__title">
                                    <a href="<?php echo customcore_e($productUrl); ?>">
                                        <?php echo customcore_e($productName); ?>
                                    </a>
                                </h3>
                                <?php if ($categoryName !== '') : ?>
                                    <p class="card__meta"><?php echo customcore_e($categoryName); ?></p>
                                <?php endif; ?>
                                <?php if ($short !== '') : ?>
                                    <p class="product-card__blurb"><?php echo customcore_e($short); ?></p>
                                <?php endif; ?>
                                <ul class="product-card__specs">
                                    <?php if ($specCpu !== '') : ?>
                                        <li><?php echo customcore_e($specCpu); ?></li>
                                    <?php endif; ?>
                                    <?php if ($specGpu !== '') : ?>
                                        <li><?php echo customcore_e($specGpu); ?></li>
                                    <?php endif; ?>
                                    <?php if ($specRam !== '') : ?>
                                        <li><?php echo customcore_e($specRam); ?></li>
                                    <?php endif; ?>
                                </ul>
                                <p class="card__price">From $<?php echo customcore_e($price); ?></p>
                                <p class="product-card__stock<?php echo $inStock ? '' : ' is-out'; ?>">
                                    <?php echo $inStock
                                        ? customcore_e('In stock (' . $stock . ')')
                                        : 'Out of stock'; ?>
                                </p>
                                <p class="product-card__compare">
                                    <label class="product-card__compare-label" for="<?php echo customcore_e($compareInputId); ?>">
                                        <input
                                            type="checkbox"
                                            id="<?php echo customcore_e($compareInputId); ?>"
                                            name="ids[]"
                                            value="<?php echo customcore_e((string) $productId); ?>"
                                        >
                                        Add to compare
                                    </label>
                                </p>
                                <p class="product-card__actions">
                                    <a class="button" href="<?php echo customcore_e($productUrl); ?>">View details</a>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="catalogue-compare-bar catalogue-compare-bar--footer">
                        <p class="catalogue-compare-bar__text">
                            Ready to compare? Submit 2–4 selected systems.
                        </p>
                        <button type="submit" class="button">Compare selected</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

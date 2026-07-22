<?php
/**
 * CustomCore — Catalogue listing (Commit 3.3).
 *
 * File responsibility:
 *   Displays all active prebuilt systems from MySQL in a responsive product grid.
 *   Optional ?category=slug limits results to one tier (used by homepage links).
 *   Search lives on search.php (Commit 3.5). Full filter/sort controls arrive in
 *   Commit 3.6; product detail is product.php (Commit 3.4).
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'Catalogue — CustomCore Gaming PCs';
$pageDescription = 'Browse all CustomCore configurable prebuilt gaming and creator PCs across Budget, Esports, High-Performance, and Creator tiers.';
$pageKeywords = 'CustomCore, catalogue, gaming PC, prebuilt, Budget, Esports, High-Performance, Creator';
$currentPage = 'catalogue';

$categorySlug = '';
if (isset($_GET['category']) && is_string($_GET['category'])) {
    $categorySlug = strtolower(trim($_GET['category']));
    // Allow only safe slug characters (letters, numbers, hyphens).
    if ($categorySlug !== '' && !preg_match('/^[a-z0-9\-]+$/', $categorySlug)) {
        $categorySlug = '';
    }
}

$products = [];
$categories = [];
$activeCategory = null;
$catalogueError = null;

try {
    $pdo = customcore_pdo();

    $categoryStmt = $pdo->query(
        'SELECT id, name, slug, description, sort_order
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $categories = $categoryStmt ? $categoryStmt->fetchAll() : [];

    if ($categorySlug !== '') {
        foreach ($categories as $category) {
            if ((string) ($category['slug'] ?? '') === $categorySlug) {
                $activeCategory = $category;
                break;
            }
        }

        // Unknown slug → show all products rather than a hard error.
        if ($activeCategory === null) {
            $categorySlug = '';
        }
    }

    $sql = 'SELECT p.id, p.name, p.slug, p.brand, p.short_description, p.base_price,
                   p.stock_quantity, p.image_path, p.spec_cpu, p.spec_gpu,
                   p.spec_ram, p.spec_storage, p.is_featured,
                   c.name AS category_name, c.slug AS category_slug
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1';

    $params = [];

    if ($activeCategory !== null) {
        $sql .= ' AND c.slug = :category_slug';
        $params[':category_slug'] = (string) $activeCategory['slug'];
    }

    $sql .= ' ORDER BY c.sort_order ASC, p.base_price ASC, p.name ASC';

    $productStmt = $pdo->prepare($sql);
    $productStmt->execute($params);
    $products = $productStmt->fetchAll();
} catch (Throwable $exception) {
    $catalogueError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Catalogue data is temporarily unavailable.';
}

$productCount = count($products);
$headingLabel = $activeCategory !== null
    ? ((string) $activeCategory['name'] . ' systems')
    : 'All systems';

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
            Every listed system is an active catalogue product — not hardcoded HTML.
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

    <?php if ($categories !== []) : ?>
        <nav class="catalogue-tiers" aria-label="Catalogue performance tiers">
            <a
                class="catalogue-tiers__link<?php echo $activeCategory === null ? ' is-active' : ''; ?>"
                href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>"
            >All</a>
            <?php foreach ($categories as $category) : ?>
                <?php
                $slug = (string) ($category['slug'] ?? '');
                $name = (string) ($category['name'] ?? '');
                $isActive = $activeCategory !== null
                    && (string) ($activeCategory['slug'] ?? '') === $slug;
                $tierUrl = customcore_url('catalogue.php?category=' . rawurlencode($slug));
                ?>
                <a
                    class="catalogue-tiers__link<?php echo $isActive ? ' is-active' : ''; ?>"
                    href="<?php echo customcore_e($tierUrl); ?>"
                ><?php echo customcore_e($name); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <div class="catalogue-toolbar">
        <p class="catalogue-toolbar__count" aria-live="polite">
            <strong><?php echo customcore_e((string) $productCount); ?></strong>
            <?php echo $productCount === 1 ? 'system' : 'systems'; ?>
            shown
            <?php if ($activeCategory !== null) : ?>
                in <strong><?php echo customcore_e((string) $activeCategory['name']); ?></strong>
            <?php endif; ?>
        </p>
        <p class="catalogue-toolbar__note">
            <a href="<?php echo customcore_e(customcore_url('search.php')); ?>">Open search page</a>
            · Full filters and sorting arrive next
        </p>
    </div>

    <?php if ($activeCategory !== null && (string) ($activeCategory['description'] ?? '') !== '') : ?>
        <p class="catalogue-tier-blurb">
            <?php echo customcore_e((string) $activeCategory['description']); ?>
        </p>
    <?php endif; ?>

    <?php if ($products === [] && $catalogueError === null) : ?>
        <p class="empty-state">
            No active products match this view.
            <?php if ($activeCategory !== null) : ?>
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Show all systems</a>
            <?php else : ?>
                Import the catalogue seeds to populate the store.
            <?php endif; ?>
        </p>
    <?php elseif ($products !== []) : ?>
        <h2 class="visually-hidden"><?php echo customcore_e($headingLabel); ?></h2>
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
                    <p class="product-card__actions">
                        <a class="button" href="<?php echo customcore_e($productUrl); ?>">View details</a>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

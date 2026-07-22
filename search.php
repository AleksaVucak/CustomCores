<?php
/**
 * CustomCore — Product search (Commit 3.5).
 *
 * File responsibility:
 *   Searches active catalogue products by name, category, brand, and description.
 *   Handles valid hits and empty results. Uses PDO prepared statements only.
 *
 * URL format:
 *   search.php?q=rtx
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'Search — CustomCore';
$pageDescription = 'Search CustomCore gaming PCs by name, category, brand, or description.';
$pageKeywords = 'CustomCore, search, gaming PC, catalogue';
$currentPage = 'catalogue';

$query = '';
if (isset($_GET['q']) && is_string($_GET['q'])) {
    $query = trim($_GET['q']);
    // Cap length to keep LIKE patterns reasonable.
    if (strlen($query) > 100) {
        $query = substr($query, 0, 100);
    }
}

$products = [];
$searchError = null;
$searched = ($query !== '');

if ($searched) {
    try {
        $pdo = customcore_pdo();

        // Escape LIKE wildcards in user input so % and _ are literal.
        $likeRaw = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $like = '%' . $likeRaw . '%';

        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.brand, p.short_description, p.base_price,
                    p.stock_quantity, p.spec_cpu, p.spec_gpu, p.spec_ram, p.is_featured,
                    c.name AS category_name, c.slug AS category_slug
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1
               AND (
                    p.name LIKE :q1
                 OR p.brand LIKE :q2
                 OR p.short_description LIKE :q3
                 OR p.description LIKE :q4
                 OR c.name LIKE :q5
                 OR p.spec_cpu LIKE :q6
                 OR p.spec_gpu LIKE :q7
               )
             ORDER BY p.is_featured DESC, p.base_price ASC, p.name ASC
             LIMIT 50'
        );

        $stmt->execute([
            ':q1' => $like,
            ':q2' => $like,
            ':q3' => $like,
            ':q4' => $like,
            ':q5' => $like,
            ':q6' => $like,
            ':q7' => $like,
        ]);

        $products = $stmt->fetchAll();
    } catch (Throwable $exception) {
        $searchError = customcore_is_debug()
            ? $exception->getMessage()
            : 'Search is temporarily unavailable.';
        $products = [];
    }
}

$resultCount = count($products);

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section search-page" aria-labelledby="search-heading">
    <header class="search-page__header">
        <h1 id="search-heading">Search catalogue</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/catalogue.html')); ?>">Catalogue guide</a>
        </p>
        <p class="search-page__intro">
            Find systems by name, category tier, brand, description, or key specs
            (for example CPU or GPU).
        </p>
    </header>

    <form class="search-form" method="get" action="<?php echo customcore_e(customcore_url('search.php')); ?>" role="search">
        <label class="search-form__label" for="search-q">Search terms</label>
        <div class="search-form__row">
            <input
                type="search"
                id="search-q"
                name="q"
                class="search-form__input"
                value="<?php echo customcore_e($query); ?>"
                placeholder="e.g. RTX, Budget, Creator, Ryzen"
                maxlength="100"
                autocomplete="off"
                required
            >
            <button type="submit" class="button">Search</button>
        </div>
    </form>

    <?php if ($searchError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($searchError); ?>
        </div>
    <?php endif; ?>

    <?php if (!$searched) : ?>
        <p class="empty-state">
            Enter a search term above to find matching systems.
            You can also
            <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">browse the full catalogue</a>.
        </p>
    <?php else : ?>
        <div class="catalogue-toolbar">
            <p class="catalogue-toolbar__count" aria-live="polite">
                <?php if ($resultCount === 0) : ?>
                    No systems matched
                    <strong>&ldquo;<?php echo customcore_e($query); ?>&rdquo;</strong>
                <?php else : ?>
                    <strong><?php echo customcore_e((string) $resultCount); ?></strong>
                    <?php echo $resultCount === 1 ? 'result' : 'results'; ?>
                    for <strong>&ldquo;<?php echo customcore_e($query); ?>&rdquo;</strong>
                <?php endif; ?>
            </p>
            <p class="catalogue-toolbar__note">
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse all systems</a>
            </p>
        </div>

        <?php if ($resultCount === 0 && $searchError === null) : ?>
            <p class="empty-state">
                Try a different keyword, a tier name (Budget, Esports, High-Performance, Creator),
                a brand, or part of a GPU/CPU label. Or
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">view every product</a>.
            </p>
        <?php elseif ($resultCount > 0) : ?>
            <div class="card-grid catalogue-grid">
                <?php foreach ($products as $product) : ?>
                    <?php
                    $productId = (int) ($product['id'] ?? 0);
                    $productName = (string) ($product['name'] ?? 'Product');
                    $productUrl = customcore_url('product.php?id=' . $productId);
                    $price = number_format((float) ($product['base_price'] ?? 0), 2);
                    $categoryName = (string) ($product['category_name'] ?? '');
                    $brand = (string) ($product['brand'] ?? '');
                    $short = (string) ($product['short_description'] ?? '');
                    $specCpu = (string) ($product['spec_cpu'] ?? '');
                    $specGpu = (string) ($product['spec_gpu'] ?? '');
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
                        <h2 class="card__title">
                            <a href="<?php echo customcore_e($productUrl); ?>">
                                <?php echo customcore_e($productName); ?>
                            </a>
                        </h2>
                        <p class="card__meta">
                            <?php echo customcore_e($categoryName); ?>
                            <?php if ($brand !== '') : ?>
                                · <?php echo customcore_e($brand); ?>
                            <?php endif; ?>
                        </p>
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
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

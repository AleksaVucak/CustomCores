<?php
/**
 * CustomCore — Homepage (Commit 3.1).
 *
 * File responsibility:
 *   Public landing page with hero, featured products and category tiers loaded
 *   from MySQL, a learning-centre teaser (media placeholder until Stage 8),
 *   and primary calls to action.
 *
 * Authentication requirements:
 *   None (public).
 *
 * Data sources:
 *   - products (is_featured = 1, is_active = 1)
 *   - categories (is_active = 1)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'CustomCore — Custom Gaming PC Store & Builder';
$pageDescription = 'Browse configurable gaming PCs and build a compatible custom system with CustomCore.';
$pageKeywords = 'CustomCore, gaming PC, custom PC builder, prebuilt gaming computer';
$currentPage = 'home';

$featuredProducts = [];
$categories = [];
$homeDataError = null;

try {
    $pdo = customcore_pdo();

    $featuredStmt = $pdo->query(
        'SELECT p.id, p.name, p.slug, p.short_description, p.base_price,
                p.image_path, p.spec_cpu, p.spec_gpu, p.spec_ram, p.spec_storage,
                c.name AS category_name, c.slug AS category_slug
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1
           AND p.is_featured = 1
         ORDER BY p.base_price ASC
         LIMIT 8'
    );
    $featuredProducts = $featuredStmt ? $featuredStmt->fetchAll() : [];

    $categoryStmt = $pdo->query(
        'SELECT id, name, slug, description, sort_order
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $categories = $categoryStmt ? $categoryStmt->fetchAll() : [];
} catch (Throwable $exception) {
    // Homepage stays usable if the database is not configured yet.
    $homeDataError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Catalogue data is temporarily unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero hero--home" aria-labelledby="home-hero-heading">
    <p class="hero__brand">CustomCore</p>
    <h1 id="home-hero-heading">Build a gaming PC you can trust</h1>
    <p class="hero__support">
        Configurable prebuilts across four performance tiers, plus a guided custom
        builder with live pricing and clear compatibility feedback.
    </p>
    <p class="hero__actions">
        <a class="button" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Shop prebuilts</a>
        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Start PC Builder</a>
    </p>
</section>

<p class="context-help">
    New here?
    <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Open the Help centre</a>
    or read our
    <a href="<?php echo customcore_e(customcore_url('about.php')); ?>">business case</a>.
</p>

<?php if ($homeDataError !== null) : ?>
    <div class="flash flash--warning" role="status">
        <?php echo customcore_e($homeDataError); ?>
        <?php if (!customcore_is_debug()) : ?>
            Check back soon, or browse the planned catalogue once the database is connected.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="layout-split layout-split--home">
    <section class="content-section home-featured" aria-labelledby="featured-heading">
        <div class="section-heading">
            <h2 id="featured-heading">Featured systems</h2>
            <p>
                Hand-picked builds from MySQL
                <?php if ($featuredProducts !== []) : ?>
                    (<?php echo customcore_e((string) count($featuredProducts)); ?> shown)
                <?php endif; ?>
            </p>
        </div>

        <?php if ($featuredProducts === []) : ?>
            <p class="empty-state">
                No featured products are available yet.
                <?php if ($homeDataError === null) : ?>
                    Import the catalogue seeds, or mark products as featured in the database.
                <?php endif; ?>
            </p>
        <?php else : ?>
            <div class="card-grid">
                <?php foreach ($featuredProducts as $product) : ?>
                    <?php
                    $productId = (int) ($product['id'] ?? 0);
                    $productName = (string) ($product['name'] ?? 'Product');
                    $productUrl = customcore_url('product.php?id=' . $productId);
                    $price = number_format((float) ($product['base_price'] ?? 0), 2);
                    $categoryName = (string) ($product['category_name'] ?? '');
                    $short = (string) ($product['short_description'] ?? '');
                    $specCpu = (string) ($product['spec_cpu'] ?? '');
                    $specGpu = (string) ($product['spec_gpu'] ?? '');
                    ?>
                    <article class="card product-card">
                        <div class="product-card__media" aria-hidden="true">
                            <span class="product-card__media-label">PC</span>
                        </div>
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
                        <?php if ($specCpu !== '' || $specGpu !== '') : ?>
                            <ul class="product-card__specs">
                                <?php if ($specCpu !== '') : ?>
                                    <li><?php echo customcore_e($specCpu); ?></li>
                                <?php endif; ?>
                                <?php if ($specGpu !== '') : ?>
                                    <li><?php echo customcore_e($specGpu); ?></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                        <p class="card__price">From $<?php echo customcore_e($price); ?></p>
                        <p class="product-card__actions">
                            <a class="button" href="<?php echo customcore_e($productUrl); ?>">View details</a>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="section-footer-link">
            <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse the full catalogue</a>
        </p>
    </section>

    <section class="content-section home-categories" aria-labelledby="categories-heading">
        <div class="section-heading">
            <h2 id="categories-heading">Performance tiers</h2>
            <p>Four catalogue categories for every budget and goal.</p>
        </div>

        <?php if ($categories === []) : ?>
            <p class="empty-state">
                Categories will appear here after the catalogue seed is imported.
            </p>
        <?php else : ?>
            <ul class="tier-list">
                <?php foreach ($categories as $category) : ?>
                    <?php
                    $catName = (string) ($category['name'] ?? '');
                    $catSlug = (string) ($category['slug'] ?? '');
                    $catDesc = (string) ($category['description'] ?? '');
                    $catUrl = customcore_url('catalogue.php?category=' . rawurlencode($catSlug));
                    ?>
                    <li class="tier-list__item">
                        <a class="tier-card" href="<?php echo customcore_e($catUrl); ?>">
                            <h3 class="tier-card__title"><?php echo customcore_e($catName); ?></h3>
                            <?php if ($catDesc !== '') : ?>
                                <p class="tier-card__text"><?php echo customcore_e($catDesc); ?></p>
                            <?php endif; ?>
                            <span class="tier-card__cta">Shop <?php echo customcore_e($catName); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="content-section home-media" aria-labelledby="media-heading">
    <div class="section-heading">
        <h2 id="media-heading">Learning centre</h2>
        <p>Short guides for choosing tiers, reading specs, and starting a custom build.</p>
    </div>

    <div class="media-teaser">
        <div class="media-teaser__placeholder" role="img" aria-label="Video placeholder for upcoming learning centre guides">
            <span class="media-teaser__badge">Video coming in Stage 8</span>
            <p class="media-teaser__caption">
                Guided walkthroughs will play here once copyright-safe media is added.
            </p>
        </div>
        <div class="media-teaser__copy">
            <p>
                Prefer reading first? Visit Help for catalogue and builder topics, or jump
                straight into the interactive PC Builder when you are ready to configure parts.
            </p>
            <p class="hero__actions">
                <a class="button" href="<?php echo customcore_e(customcore_url('media.php')); ?>">Watch guides</a>
                <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Open Help</a>
            </p>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

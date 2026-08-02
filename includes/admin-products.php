<?php
/**
 * CustomCore — Administrator product management helpers (Commit 9.2).
 *
 * File responsibility:
 *   Shared, security-first helpers for the admin product CRUD screens:
 *   validation, slug generation/uniqueness, list/search queries, create/update,
 *   active-status toggling, and secure product-image uploads (real MIME check,
 *   size cap, generated on-disk names under uploads/products/).
 *
 * Usage:
 *   require_once __DIR__ . '/admin-products.php';
 *
 * Security notes:
 *   - All writes use PDO prepared statements.
 *   - Slugs are unique (checked with an optional self-exclusion on edit).
 *   - Image uploads never trust the browser MIME/extension: the type is
 *     detected with finfo and matched to an allowlist; the on-disk filename is
 *     randomly generated; the original name is never used on disk.
 *   - Products are disabled (is_active = 0), never hard-deleted, to preserve
 *     order/review history integrity.
 */

declare(strict_types=1);

if (!function_exists('customcore_app_config')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Maximum length limits mirrored from the products table schema.
 */
const CUSTOMCORE_PRODUCT_NAME_MAX = 200;
const CUSTOMCORE_PRODUCT_SLUG_MAX = 200;
const CUSTOMCORE_PRODUCT_BRAND_MAX = 100;
const CUSTOMCORE_PRODUCT_SHORT_MAX = 500;
const CUSTOMCORE_PRODUCT_SPEC_MAX = 150;
const CUSTOMCORE_PRODUCT_PRICE_MAX = 999999.99;
const CUSTOMCORE_PRODUCT_STOCK_MAX = 100000;

/**
 * Accepted product image extensions mapped to their allowed finfo MIME types.
 *
 * @return array<string, list<string>>
 */
function customcore_admin_product_image_types(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];
}

/**
 * Absolute filesystem path to the product upload directory.
 */
function customcore_admin_product_upload_dir(): string
{
    $app = customcore_app_config();
    $relative = 'uploads/products';
    if (isset($app['paths']['uploads_products']) && is_string($app['paths']['uploads_products'])) {
        $relative = trim($app['paths']['uploads_products'], '/');
    }

    return dirname(__DIR__) . '/' . $relative;
}

/**
 * Maximum accepted product image size in bytes.
 */
function customcore_admin_product_image_max_bytes(): int
{
    $app = customcore_app_config();
    $max = (int) ($app['upload_max_bytes'] ?? (2 * 1024 * 1024));

    return $max > 0 ? $max : (2 * 1024 * 1024);
}

/**
 * Categories available for assignment (active only), ordered for a dropdown.
 *
 * @return list<array{id:int, name:string, slug:string}>
 */
function customcore_admin_product_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, slug
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $rows = $stmt ? $stmt->fetchAll() : [];

    $categories = [];
    foreach ($rows as $row) {
        $categories[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ];
    }

    return $categories;
}

/**
 * Whether a category id exists and is active.
 */
function customcore_admin_product_category_exists(PDO $pdo, int $categoryId): bool
{
    if ($categoryId < 1) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id AND is_active = 1');
    $stmt->execute([':id' => $categoryId]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Convert a product name to a URL-safe slug base.
 */
function customcore_admin_product_slugify(string $value): string
{
    $value = trim($value);
    // Transliterate to ASCII where possible.
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    if ($value === '') {
        $value = 'product';
    }

    if (strlen($value) > CUSTOMCORE_PRODUCT_SLUG_MAX) {
        $value = rtrim(substr($value, 0, CUSTOMCORE_PRODUCT_SLUG_MAX), '-');
    }

    return $value;
}

/**
 * Ensure a slug is unique in products, appending -2, -3, … when needed.
 *
 * @param int|null $excludeId Product id to ignore (for edits).
 */
function customcore_admin_product_unique_slug(PDO $pdo, string $slugBase, ?int $excludeId = null): string
{
    $slugBase = customcore_admin_product_slugify($slugBase);
    $candidate = $slugBase;
    $suffix = 2;

    $sql = 'SELECT id FROM products WHERE slug = :slug';
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude';
    }
    $stmt = $pdo->prepare($sql);

    while (true) {
        $params = [':slug' => $candidate];
        if ($excludeId !== null) {
            $params[':exclude'] = $excludeId;
        }
        $stmt->execute($params);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }

        $tail = '-' . $suffix;
        $maxBase = CUSTOMCORE_PRODUCT_SLUG_MAX - strlen($tail);
        $candidate = rtrim(substr($slugBase, 0, max(1, $maxBase)), '-') . $tail;
        $suffix++;
    }
}

/**
 * Validate and normalise product form input.
 *
 * @param array<string, mixed> $input Raw $_POST.
 * @param int|null $excludeId Product being edited (for slug uniqueness).
 * @return array{
 *   errors: array<string, string>,
 *   values: array{
 *     category_id:int, name:string, slug:string, brand:string,
 *     short_description:string, description:string, base_price:float,
 *     stock_quantity:int, spec_cpu:string, spec_gpu:string, spec_ram:string,
 *     spec_storage:string, is_featured:int, is_active:int
 *   }
 * }
 */
function customcore_admin_product_validate(PDO $pdo, array $input, ?int $excludeId = null): array
{
    $errors = [];

    $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        $errors['name'] = 'A product name is required.';
    } elseif (mb_strlen($name) > CUSTOMCORE_PRODUCT_NAME_MAX) {
        $errors['name'] = 'Name must be ' . CUSTOMCORE_PRODUCT_NAME_MAX . ' characters or fewer.';
        $name = mb_substr($name, 0, CUSTOMCORE_PRODUCT_NAME_MAX);
    }

    $categoryId = 0;
    if (isset($input['category_id']) && (is_string($input['category_id']) || is_int($input['category_id']))) {
        $categoryId = (int) $input['category_id'];
    }
    if ($categoryId < 1) {
        $errors['category_id'] = 'Please choose a category.';
    } elseif (!customcore_admin_product_category_exists($pdo, $categoryId)) {
        $errors['category_id'] = 'The selected category is not available.';
    }

    $brand = isset($input['brand']) && is_string($input['brand']) ? trim($input['brand']) : '';
    if ($brand === '') {
        $brand = 'CustomCore';
    } elseif (mb_strlen($brand) > CUSTOMCORE_PRODUCT_BRAND_MAX) {
        $errors['brand'] = 'Brand must be ' . CUSTOMCORE_PRODUCT_BRAND_MAX . ' characters or fewer.';
        $brand = mb_substr($brand, 0, CUSTOMCORE_PRODUCT_BRAND_MAX);
    }

    $shortDescription = isset($input['short_description']) && is_string($input['short_description'])
        ? trim($input['short_description'])
        : '';
    if (mb_strlen($shortDescription) > CUSTOMCORE_PRODUCT_SHORT_MAX) {
        $errors['short_description'] = 'Short description must be ' . CUSTOMCORE_PRODUCT_SHORT_MAX . ' characters or fewer.';
        $shortDescription = mb_substr($shortDescription, 0, CUSTOMCORE_PRODUCT_SHORT_MAX);
    }

    $description = isset($input['description']) && is_string($input['description']) ? trim($input['description']) : '';
    if ($description === '') {
        $errors['description'] = 'A full description is required.';
    } elseif (mb_strlen($description) > 20000) {
        $errors['description'] = 'Description is too long.';
        $description = mb_substr($description, 0, 20000);
    }

    $basePrice = 0.0;
    $rawPrice = $input['base_price'] ?? '';
    if (is_string($rawPrice) || is_int($rawPrice) || is_float($rawPrice)) {
        $rawPrice = trim((string) $rawPrice);
        if ($rawPrice === '' || !is_numeric($rawPrice)) {
            $errors['base_price'] = 'Enter a valid price.';
        } else {
            $basePrice = round((float) $rawPrice, 2);
            if ($basePrice < 0) {
                $errors['base_price'] = 'Price cannot be negative.';
            } elseif ($basePrice > CUSTOMCORE_PRODUCT_PRICE_MAX) {
                $errors['base_price'] = 'Price is too high.';
            }
        }
    } else {
        $errors['base_price'] = 'Enter a valid price.';
    }

    $stock = 0;
    $rawStock = $input['stock_quantity'] ?? '';
    if (is_string($rawStock) || is_int($rawStock)) {
        $rawStock = trim((string) $rawStock);
        if ($rawStock === '' || !preg_match('/^\d+$/', $rawStock)) {
            $errors['stock_quantity'] = 'Enter stock as a whole number (0 or more).';
        } else {
            $stock = (int) $rawStock;
            if ($stock > CUSTOMCORE_PRODUCT_STOCK_MAX) {
                $errors['stock_quantity'] = 'Stock quantity is too high.';
                $stock = CUSTOMCORE_PRODUCT_STOCK_MAX;
            }
        }
    } else {
        $errors['stock_quantity'] = 'Enter stock as a whole number (0 or more).';
    }

    $specs = [];
    foreach (['spec_cpu', 'spec_gpu', 'spec_ram', 'spec_storage'] as $specKey) {
        $val = isset($input[$specKey]) && is_string($input[$specKey]) ? trim($input[$specKey]) : '';
        if (mb_strlen($val) > CUSTOMCORE_PRODUCT_SPEC_MAX) {
            $val = mb_substr($val, 0, CUSTOMCORE_PRODUCT_SPEC_MAX);
        }
        $specs[$specKey] = $val;
    }

    $isFeatured = !empty($input['is_featured']) ? 1 : 0;
    // Default new products to active unless explicitly unchecked.
    $isActive = !empty($input['is_active']) ? 1 : 0;

    // Slug: optional manual override, otherwise derived from the name.
    $slugInput = isset($input['slug']) && is_string($input['slug']) ? trim($input['slug']) : '';
    $slugBase = $slugInput !== '' ? $slugInput : $name;
    $slug = '';
    if ($name !== '') {
        $slug = customcore_admin_product_unique_slug($pdo, $slugBase, $excludeId);
    }

    return [
        'errors' => $errors,
        'values' => [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'brand' => $brand,
            'short_description' => $shortDescription,
            'description' => $description,
            'base_price' => $basePrice,
            'stock_quantity' => $stock,
            'spec_cpu' => $specs['spec_cpu'],
            'spec_gpu' => $specs['spec_gpu'],
            'spec_ram' => $specs['spec_ram'],
            'spec_storage' => $specs['spec_storage'],
            'is_featured' => $isFeatured,
            'is_active' => $isActive,
        ],
    ];
}

/**
 * Validate a single uploaded product image (optional field).
 *
 * @param mixed $file A single $_FILES entry.
 * @return array{
 *   ok: bool,
 *   provided: bool,
 *   error: ?string,
 *   file: ?array{tmp_name:string, extension:string, mime_type:string, size:int}
 * }
 */
function customcore_admin_product_validate_image($file): array
{
    if (!is_array($file) || !isset($file['error'])) {
        return ['ok' => true, 'provided' => false, 'error' => null, 'file' => null];
    }

    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'provided' => false, 'error' => null, 'file' => null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        $message = ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE)
            ? 'The image is larger than the allowed size.'
            : 'The image could not be uploaded. Please try again.';
        return ['ok' => false, 'provided' => true, 'error' => $message, 'file' => null];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'provided' => true, 'error' => 'The image could not be verified.', 'file' => null];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'provided' => true, 'error' => 'The image file is empty.', 'file' => null];
    }

    $maxBytes = customcore_admin_product_image_max_bytes();
    if ($size > $maxBytes) {
        $maxLabel = number_format($maxBytes / (1024 * 1024), 1) . ' MB';
        return ['ok' => false, 'provided' => true, 'error' => 'The image exceeds the ' . $maxLabel . ' limit.', 'file' => null];
    }

    $detectedMime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detectedMime = (string) finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
    }

    $matchedExt = null;
    foreach (customcore_admin_product_image_types() as $ext => $mimes) {
        if (in_array($detectedMime, $mimes, true)) {
            $matchedExt = $ext;
            break;
        }
    }

    if ($matchedExt === null) {
        return [
            'ok' => false,
            'provided' => true,
            'error' => 'The image must be a JPG, PNG, WEBP, or GIF file.',
            'file' => null,
        ];
    }

    return [
        'ok' => true,
        'provided' => true,
        'error' => null,
        'file' => [
            'tmp_name' => $tmp,
            'extension' => $matchedExt,
            'mime_type' => $detectedMime,
            'size' => $size,
        ],
    ];
}

/**
 * Move a validated product image into uploads/products/ with a generated name.
 *
 * @param array{tmp_name:string, extension:string, mime_type:string, size:int} $file
 * @return string Project-relative path (e.g. "uploads/products/ab12cd.jpg").
 * @throws RuntimeException on storage failure.
 */
function customcore_admin_product_store_image(array $file): string
{
    $dir = customcore_admin_product_upload_dir();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Product upload directory is unavailable.');
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Product upload directory is not writable.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
    $target = $dir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Failed to store the uploaded image.');
    }
    @chmod($target, 0644);

    $app = customcore_app_config();
    $relativeDir = isset($app['paths']['uploads_products']) && is_string($app['paths']['uploads_products'])
        ? trim($app['paths']['uploads_products'], '/')
        : 'uploads/products';

    return $relativeDir . '/' . $storedName;
}

/**
 * Delete a previously uploaded product image (best effort, uploads/ only).
 *
 * Never touches seeded assets under assets/images/.
 */
function customcore_admin_product_delete_image(?string $relativePath): void
{
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (str_contains($relativePath, '..')) {
        return;
    }
    if (!preg_match('#^uploads/products/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp|gif)$#i', $relativePath)) {
        return;
    }

    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Insert a new product. Returns the new product id.
 *
 * @param array<string, mixed> $values Output of customcore_admin_product_validate()['values'].
 * @param string $imagePath Stored image path ('' if none).
 */
function customcore_admin_product_create(PDO $pdo, array $values, string $imagePath = ''): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO products
            (category_id, name, slug, brand, short_description, description,
             base_price, stock_quantity, image_path, spec_cpu, spec_gpu,
             spec_ram, spec_storage, is_featured, is_active)
         VALUES
            (:category_id, :name, :slug, :brand, :short_description, :description,
             :base_price, :stock_quantity, :image_path, :spec_cpu, :spec_gpu,
             :spec_ram, :spec_storage, :is_featured, :is_active)'
    );

    $stmt->execute([
        ':category_id' => $values['category_id'],
        ':name' => $values['name'],
        ':slug' => $values['slug'],
        ':brand' => $values['brand'],
        ':short_description' => $values['short_description'],
        ':description' => $values['description'],
        ':base_price' => $values['base_price'],
        ':stock_quantity' => $values['stock_quantity'],
        ':image_path' => $imagePath,
        ':spec_cpu' => $values['spec_cpu'],
        ':spec_gpu' => $values['spec_gpu'],
        ':spec_ram' => $values['spec_ram'],
        ':spec_storage' => $values['spec_storage'],
        ':is_featured' => $values['is_featured'],
        ':is_active' => $values['is_active'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing product. When $imagePath is null the current image is kept.
 *
 * @param array<string, mixed> $values Output of customcore_admin_product_validate()['values'].
 * @param string|null $imagePath New image path, or null to leave unchanged.
 */
function customcore_admin_product_update(PDO $pdo, int $productId, array $values, ?string $imagePath = null): void
{
    $sql =
        'UPDATE products SET
            category_id = :category_id,
            name = :name,
            slug = :slug,
            brand = :brand,
            short_description = :short_description,
            description = :description,
            base_price = :base_price,
            stock_quantity = :stock_quantity,
            spec_cpu = :spec_cpu,
            spec_gpu = :spec_gpu,
            spec_ram = :spec_ram,
            spec_storage = :spec_storage,
            is_featured = :is_featured,
            is_active = :is_active';

    $params = [
        ':category_id' => $values['category_id'],
        ':name' => $values['name'],
        ':slug' => $values['slug'],
        ':brand' => $values['brand'],
        ':short_description' => $values['short_description'],
        ':description' => $values['description'],
        ':base_price' => $values['base_price'],
        ':stock_quantity' => $values['stock_quantity'],
        ':spec_cpu' => $values['spec_cpu'],
        ':spec_gpu' => $values['spec_gpu'],
        ':spec_ram' => $values['spec_ram'],
        ':spec_storage' => $values['spec_storage'],
        ':is_featured' => $values['is_featured'],
        ':is_active' => $values['is_active'],
        ':id' => $productId,
    ];

    if ($imagePath !== null) {
        $sql .= ', image_path = :image_path';
        $params[':image_path'] = $imagePath;
    }

    $sql .= ' WHERE id = :id';

    $pdo->prepare($sql)->execute($params);
}

/**
 * Fetch a single product row for editing.
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_product_fetch(PDO $pdo, int $productId): ?array
{
    if ($productId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id'
    );
    $stmt->execute([':id' => $productId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Set a product's active status (soft disable/enable).
 */
function customcore_admin_product_set_active(PDO $pdo, int $productId, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE products SET is_active = :active WHERE id = :id');
    $stmt->execute([
        ':active' => $active ? 1 : 0,
        ':id' => $productId,
    ]);
}

/**
 * List/search products for the admin table.
 *
 * @param array{search?:string, category_id?:int, status?:string} $filters
 * @return list<array<string, mixed>>
 */
function customcore_admin_product_list(PDO $pdo, array $filters = []): array
{
    $sql =
        'SELECT p.id, p.name, p.slug, p.brand, p.base_price, p.stock_quantity,
                p.image_path, p.is_active, p.is_featured, p.updated_at,
                c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE 1 = 1';
    $params = [];

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        // Distinct placeholders: named parameters cannot be reused when PDO
        // emulated prepares are disabled.
        $sql .= ' AND (p.name LIKE :search_name OR p.brand LIKE :search_brand OR p.slug LIKE :search_slug)';
        $like = '%' . $search . '%';
        $params[':search_name'] = $like;
        $params[':search_brand'] = $like;
        $params[':search_slug'] = $like;
    }

    $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : 0;
    if ($categoryId > 0) {
        $sql .= ' AND p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : '';
    if ($status === 'active') {
        $sql .= ' AND p.is_active = 1';
    } elseif ($status === 'inactive') {
        $sql .= ' AND p.is_active = 0';
    }

    $sql .= ' ORDER BY p.updated_at DESC, p.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Distinct brands present in the catalogue (for a datalist suggestion).
 *
 * @return list<string>
 */
function customcore_admin_product_brands(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT DISTINCT brand FROM products ORDER BY brand ASC');

    return $stmt ? array_map('strval', array_column($stmt->fetchAll(), 'brand')) : [];
}

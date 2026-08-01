<?php
/**
 * CustomCore — Wishlist helper functions (Commit 7.1).
 *
 * File responsibility:
 *   Shared wishlist operations: get or create the user's wishlist, add and
 *   remove products, list items with product detail, check membership, count
 *   items, and clear the wishlist. All mutations are scoped to the current
 *   user_id (ownership enforced) so a wishlist is private to its owner.
 *
 * Data model:
 *   - wishlists       — one row per user (UNIQUE user_id).
 *   - wishlist_items  — products on a wishlist (UNIQUE wishlist_id+product_id).
 *
 * Authentication requirements:
 *   A logged-in user is expected. Callers must call customcore_require_login()
 *   before invoking these helpers.
 */

declare(strict_types=1);

if (!function_exists('customcore_current_user_id')) {
    require_once __DIR__ . '/auth.php';
}

/**
 * Get (or create) the user's wishlist row. Returns the wishlist ID.
 */
function customcore_wishlist_id(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM wishlists WHERE user_id = :uid LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();

    if ($row !== false) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO wishlists (user_id) VALUES (:uid)'
    );
    $insert->execute([':uid' => $userId]);

    return (int) $pdo->lastInsertId();
}

/**
 * Add a product to the wishlist. Returns true if newly added, false if it was
 * already present (idempotent — never creates duplicate rows).
 */
function customcore_wishlist_add(PDO $pdo, int $wishlistId, int $productId): bool
{
    if ($wishlistId < 1 || $productId < 1) {
        return false;
    }

    // INSERT IGNORE relies on the UNIQUE(wishlist_id, product_id) index.
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO wishlist_items (wishlist_id, product_id)
         VALUES (:wid, :pid)'
    );
    $insert->execute([':wid' => $wishlistId, ':pid' => $productId]);

    return $insert->rowCount() > 0;
}

/**
 * Remove a product from the user's wishlist (ownership enforced via JOIN).
 * Returns the number of rows removed (0 or 1).
 */
function customcore_wishlist_remove(PDO $pdo, int $userId, int $productId): int
{
    if ($userId < 1 || $productId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'DELETE wi FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         WHERE w.user_id = :uid AND wi.product_id = :pid'
    );
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);

    return $stmt->rowCount();
}

/**
 * Remove every item from the user's wishlist. Returns rows removed.
 */
function customcore_wishlist_clear(PDO $pdo, int $userId): int
{
    if ($userId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'DELETE wi FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         WHERE w.user_id = :uid'
    );
    $stmt->execute([':uid' => $userId]);

    return $stmt->rowCount();
}

/**
 * Whether a product is currently on the user's wishlist.
 */
function customcore_wishlist_contains(PDO $pdo, int $userId, int $productId): bool
{
    if ($userId < 1 || $productId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         WHERE w.user_id = :uid AND wi.product_id = :pid
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId, ':pid' => $productId]);

    return $stmt->fetchColumn() !== false;
}

/**
 * List all wishlist items for a user with product detail for display.
 *
 * Returns rows shaped for the wishlist page:
 *   product_id:int, name:string, brand:string, base_price:float,
 *   stock_quantity:int, is_active:bool, image_path:?string,
 *   short_description:?string, category_name:?string, added_at:string
 *
 * @return list<array<string, mixed>>
 */
function customcore_wishlist_items(PDO $pdo, int $userId): array
{
    if ($userId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT wi.product_id, wi.created_at AS added_at,
                p.name, p.brand, p.base_price, p.stock_quantity,
                p.is_active, p.image_path, p.short_description,
                c.name AS category_name
         FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         JOIN products p ON p.id = wi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE w.user_id = :uid
         ORDER BY wi.created_at DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'product_id' => (int) $row['product_id'],
            'name' => (string) $row['name'],
            'brand' => (string) ($row['brand'] ?? ''),
            'base_price' => (float) $row['base_price'],
            'stock_quantity' => (int) $row['stock_quantity'],
            'is_active' => (int) $row['is_active'] === 1,
            'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
            'short_description' => $row['short_description'] !== null ? (string) $row['short_description'] : null,
            'category_name' => $row['category_name'] !== null ? (string) $row['category_name'] : null,
            'added_at' => (string) $row['added_at'],
        ];
    }

    return $items;
}

/**
 * Count products on the user's wishlist.
 */
function customcore_wishlist_count(PDO $pdo, int $userId): int
{
    if ($userId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM wishlist_items wi
         JOIN wishlists w ON w.id = wi.wishlist_id
         WHERE w.user_id = :uid'
    );
    $stmt->execute([':uid' => $userId]);

    return (int) $stmt->fetchColumn();
}

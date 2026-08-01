<?php
/**
 * CustomCore — Cart helper functions (Commit 6.1).
 *
 * File responsibility:
 *   Shared cart operations: get or create the user's DB cart, add items,
 *   list items with product/build details, and count items for the nav badge.
 *   All queries are scoped to the current user_id (ownership enforced).
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
 * Get (or create) the user's cart row. Returns the cart ID.
 */
function customcore_cart_id(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM carts WHERE user_id = :uid LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();

    if ($row !== false) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO carts (user_id) VALUES (:uid)'
    );
    $insert->execute([':uid' => $userId]);

    return (int) $pdo->lastInsertId();
}

/**
 * Add a product to the user's cart (or increment quantity if it already exists
 * with the same options snapshot).
 *
 * @param string|null $optionsJson JSON snapshot of selected option IDs (null for no options).
 */
function customcore_cart_add_product(
    PDO $pdo,
    int $cartId,
    int $productId,
    float $unitPrice,
    int $quantity = 1,
    ?string $optionsJson = null
): void {
    // Check if this product (with same options) is already in the cart.
    $existStmt = $pdo->prepare(
        'SELECT id, quantity FROM cart_items
         WHERE cart_id = :cid AND item_type = "product" AND product_id = :pid
           AND (options_json = :opts OR (options_json IS NULL AND :opts2 IS NULL))
         LIMIT 1'
    );
    $existStmt->execute([
        ':cid' => $cartId,
        ':pid' => $productId,
        ':opts' => $optionsJson,
        ':opts2' => $optionsJson,
    ]);
    $existing = $existStmt->fetch();

    if ($existing !== false) {
        $updateStmt = $pdo->prepare(
            'UPDATE cart_items SET quantity = quantity + :qty, unit_price = :price
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':qty' => $quantity,
            ':price' => $unitPrice,
            ':id' => (int) $existing['id'],
        ]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO cart_items (cart_id, item_type, product_id, saved_build_id, quantity, unit_price, options_json)
         VALUES (:cid, "product", :pid, NULL, :qty, :price, :opts)'
    );
    $insert->execute([
        ':cid' => $cartId,
        ':pid' => $productId,
        ':qty' => $quantity,
        ':price' => $unitPrice,
        ':opts' => $optionsJson,
    ]);
}

/**
 * Add a saved build to the user's cart (quantity always 1; builds are unique).
 */
function customcore_cart_add_build(PDO $pdo, int $cartId, int $savedBuildId, float $totalPrice): void
{
    // Only allow one instance of a given build in the cart.
    $existStmt = $pdo->prepare(
        'SELECT id FROM cart_items
         WHERE cart_id = :cid AND item_type = "saved_build" AND saved_build_id = :bid
         LIMIT 1'
    );
    $existStmt->execute([':cid' => $cartId, ':bid' => $savedBuildId]);

    if ($existStmt->fetch() !== false) {
        return; // already in cart
    }

    $insert = $pdo->prepare(
        'INSERT INTO cart_items (cart_id, item_type, product_id, saved_build_id, quantity, unit_price, options_json)
         VALUES (:cid, "saved_build", NULL, :bid, 1, :price, NULL)'
    );
    $insert->execute([
        ':cid' => $cartId,
        ':bid' => $savedBuildId,
        ':price' => $totalPrice,
    ]);
}

/**
 * Load all cart items with product/build detail.
 *
 * @return array<int, array{id:int, item_type:string, product_id:?int, saved_build_id:?int,
 *   quantity:int, unit_price:float, options_json:?string,
 *   name:string, brand:string, line_total:float}>
 */
function customcore_cart_items(PDO $pdo, int $cartId): array
{
    $stmt = $pdo->prepare(
        "SELECT ci.id, ci.item_type, ci.product_id, ci.saved_build_id,
                ci.quantity, ci.unit_price, ci.options_json,
                COALESCE(p.name, sb.name, 'Unknown item') AS name,
                COALESCE(p.brand, '') AS brand,
                COALESCE(p.is_active, 1) AS product_active,
                COALESCE(p.stock_quantity, 0) AS stock
         FROM cart_items ci
         LEFT JOIN products p ON ci.item_type = 'product' AND p.id = ci.product_id
         LEFT JOIN saved_builds sb ON ci.item_type = 'saved_build' AND sb.id = ci.saved_build_id
         WHERE ci.cart_id = :cid
         ORDER BY ci.created_at ASC"
    );
    $stmt->execute([':cid' => $cartId]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $qty = (int) $row['quantity'];
        $price = (float) $row['unit_price'];
        $items[] = [
            'id' => (int) $row['id'],
            'item_type' => (string) $row['item_type'],
            'product_id' => $row['product_id'] !== null ? (int) $row['product_id'] : null,
            'saved_build_id' => $row['saved_build_id'] !== null ? (int) $row['saved_build_id'] : null,
            'quantity' => $qty,
            'unit_price' => $price,
            'options_json' => $row['options_json'],
            'name' => (string) $row['name'],
            'brand' => (string) $row['brand'],
            'product_active' => (int) $row['product_active'] === 1,
            'stock' => (int) $row['stock'],
            'line_total' => round($price * $qty, 2),
        ];
    }

    return $items;
}

/**
 * Count total items in a user's cart (for the nav badge).
 */
function customcore_cart_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(ci.quantity), 0) AS total
         FROM cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         WHERE c.user_id = :uid'
    );
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();

    return $row !== false ? (int) $row['total'] : 0;
}

/**
 * Calculate the cart subtotal.
 *
 * @param array $items Result from customcore_cart_items().
 */
function customcore_cart_subtotal(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) $item['line_total'];
    }
    return round($total, 2);
}

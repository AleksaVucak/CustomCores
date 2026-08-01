<?php
/**
 * CustomCore — Cart helper functions (Commits 6.1–6.2).
 *
 * File responsibility:
 *   Shared cart operations: get or create the user's DB cart, add items,
 *   update/remove/clear lines (Commit 6.2), list items with product/build
 *   details, and count items for the nav badge.
 *   All mutations are scoped to the current user_id (ownership enforced).
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

/**
 * Load a single cart item owned by the given user.
 *
 * @return array|null Associative row or null when not found / not owned.
 */
function customcore_cart_owned_item(PDO $pdo, int $userId, int $itemId): ?array
{
    if ($itemId < 1 || $userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ci.id, ci.cart_id, ci.item_type, ci.product_id, ci.saved_build_id,
                ci.quantity, ci.unit_price, ci.options_json,
                p.stock_quantity, p.is_active
         FROM cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         LEFT JOIN products p ON ci.item_type = \'product\' AND p.id = ci.product_id
         WHERE ci.id = :iid AND c.user_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':iid' => $itemId, ':uid' => $userId]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Clamp a requested quantity for a cart line (Commit 6.2).
 *
 * Rules:
 *   - Saved builds are always quantity 1.
 *   - Products are clamped to 1–99 and never above available stock.
 *   - Quantity 0 means "remove" (caller should delete).
 *
 * @return array{quantity:int, remove:bool, warning:?string}
 */
function customcore_cart_clamp_quantity(array $itemRow, int $requestedQty): array
{
    $itemType = (string) ($itemRow['item_type'] ?? 'product');

    if ($itemType === 'saved_build') {
        return [
            'quantity' => 1,
            'remove' => false,
            'warning' => $requestedQty !== 1
                ? 'Custom builds are limited to a quantity of 1.'
                : null,
        ];
    }

    if ($requestedQty <= 0) {
        return [
            'quantity' => 0,
            'remove' => true,
            'warning' => null,
        ];
    }

    $qty = max(1, min(99, $requestedQty));
    $warning = null;

    $stock = isset($itemRow['stock_quantity']) ? (int) $itemRow['stock_quantity'] : 99;
    $isActive = !isset($itemRow['is_active']) || (int) $itemRow['is_active'] === 1;

    if (!$isActive) {
        return [
            'quantity' => (int) ($itemRow['quantity'] ?? 1),
            'remove' => false,
            'warning' => 'This product is no longer available. Remove it or leave quantity unchanged.',
        ];
    }

    if ($stock < 1) {
        return [
            'quantity' => (int) ($itemRow['quantity'] ?? 1),
            'remove' => false,
            'warning' => 'This product is out of stock. Remove it from your cart.',
        ];
    }

    if ($qty > $stock) {
        $qty = $stock;
        $warning = 'Quantity reduced to available stock (' . $stock . ').';
    }

    return [
        'quantity' => $qty,
        'remove' => false,
        'warning' => $warning,
    ];
}

/**
 * Update one cart line quantity (ownership-scoped). Quantity 0 removes the line.
 *
 * @return array{ok:bool, removed:bool, quantity:int, message:string}
 */
function customcore_cart_update_quantity(PDO $pdo, int $userId, int $itemId, int $requestedQty): array
{
    $item = customcore_cart_owned_item($pdo, $userId, $itemId);

    if ($item === null) {
        return [
            'ok' => false,
            'removed' => false,
            'quantity' => 0,
            'message' => 'Cart item not found.',
        ];
    }

    $clamped = customcore_cart_clamp_quantity($item, $requestedQty);

    if ($clamped['remove']) {
        customcore_cart_remove_item($pdo, $userId, $itemId);

        return [
            'ok' => true,
            'removed' => true,
            'quantity' => 0,
            'message' => 'Item removed from cart.',
        ];
    }

    if ($clamped['warning'] !== null && (int) $item['quantity'] === (int) $clamped['quantity']
        && (string) ($item['item_type'] ?? '') === 'product'
        && ((int) ($item['is_active'] ?? 1) !== 1 || (int) ($item['stock_quantity'] ?? 0) < 1)
    ) {
        return [
            'ok' => false,
            'removed' => false,
            'quantity' => (int) $item['quantity'],
            'message' => (string) $clamped['warning'],
        ];
    }

    $newQty = (int) $clamped['quantity'];

    $upd = $pdo->prepare(
        'UPDATE cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         SET ci.quantity = :qty
         WHERE ci.id = :iid AND c.user_id = :uid'
    );
    $upd->execute([
        ':qty' => $newQty,
        ':iid' => $itemId,
        ':uid' => $userId,
    ]);

    $message = $clamped['warning'] !== null
        ? (string) $clamped['warning']
        : 'Cart updated.';

    return [
        'ok' => true,
        'removed' => false,
        'quantity' => $newQty,
        'message' => $message,
    ];
}

/**
 * Bulk-update quantities from a map of item_id => quantity (Commit 6.2).
 *
 * @param array<int|string, int|string> $quantities
 * @return array{ok:bool, updated:int, removed:int, messages:list<string>}
 */
function customcore_cart_update_quantities(PDO $pdo, int $userId, array $quantities): array
{
    $updated = 0;
    $removed = 0;
    $messages = [];

    foreach ($quantities as $rawId => $rawQty) {
        if (!is_numeric($rawId) || (int) $rawId < 1) {
            continue;
        }

        $itemId = (int) $rawId;
        $requestedQty = is_numeric($rawQty) ? (int) $rawQty : -1;

        $result = customcore_cart_update_quantity($pdo, $userId, $itemId, $requestedQty);

        if (!$result['ok']) {
            $messages[] = $result['message'];
            continue;
        }

        if ($result['removed']) {
            $removed++;
        } else {
            $updated++;
        }

        if ($result['message'] !== 'Cart updated.' && $result['message'] !== 'Item removed from cart.') {
            $messages[] = $result['message'];
        }
    }

    if ($updated === 0 && $removed === 0 && $messages === []) {
        return [
            'ok' => true,
            'updated' => 0,
            'removed' => 0,
            'messages' => ['No quantities were changed.'],
        ];
    }

    return [
        'ok' => true,
        'updated' => $updated,
        'removed' => $removed,
        'messages' => $messages,
    ];
}

/**
 * Remove one cart line owned by the user. Returns true when a row was deleted.
 */
function customcore_cart_remove_item(PDO $pdo, int $userId, int $itemId): bool
{
    if ($itemId < 1 || $userId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'DELETE ci FROM cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         WHERE ci.id = :iid AND c.user_id = :uid'
    );
    $stmt->execute([':iid' => $itemId, ':uid' => $userId]);

    return $stmt->rowCount() > 0;
}

/**
 * Remove every line from the user's cart. Returns the number of deleted rows.
 */
function customcore_cart_clear(PDO $pdo, int $userId): int
{
    if ($userId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'DELETE ci FROM cart_items ci
         JOIN carts c ON c.id = ci.cart_id
         WHERE c.user_id = :uid'
    );
    $stmt->execute([':uid' => $userId]);

    return $stmt->rowCount();
}

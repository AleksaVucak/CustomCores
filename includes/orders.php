<?php
/**
 * CustomCore — Shared order helpers (Commits 6.7–6.8).
 *
 * File responsibility:
 *   Reusable labels, JSON decoders, and ownership-safe fetch helpers for
 *   customer order history, order details, and confirmation pages.
 *
 * Usage:
 *   require_once __DIR__ . '/orders.php';
 */

declare(strict_types=1);

/**
 * Allowed order status values (matches orders.status ENUM).
 *
 * @return list<string>
 */
function customcore_order_statuses(): array
{
    return ['pending', 'processing', 'ready', 'completed', 'cancelled'];
}

/**
 * Human-readable order status label.
 */
function customcore_order_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ready' => 'Ready for pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    return $labels[$status] ?? ucfirst($status);
}

/**
 * CSS modifier class for an order status badge (e.g. order-status--pending).
 */
function customcore_order_status_class(string $status): string
{
    $safe = preg_replace('/[^a-z]/', '', strtolower($status));
    if ($safe === null || $safe === '') {
        $safe = 'unknown';
    }

    return 'order-status--' . $safe;
}

/**
 * Human-readable payment method label (never card numbers — labels only).
 */
function customcore_order_payment_label(string $method): string
{
    $labels = [
        'pay_on_pickup' => 'Pay on pickup',
        'simulated_credit' => 'Credit card (simulated)',
        'simulated_debit' => 'Debit card (simulated)',
        'simulated_paypal' => 'PayPal (simulated)',
    ];

    return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/**
 * Format an order datetime for customer display.
 */
function customcore_order_format_datetime(string $datetime, string $format = 'M j, Y g:i A'): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }

    return date($format, $ts);
}

/**
 * Decode frozen product options JSON into readable lines.
 *
 * @return list<string>
 */
function customcore_order_decode_options(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $lines = [];
    foreach ($decoded as $key => $value) {
        if (is_array($value)) {
            if (isset($value['group'], $value['label'])) {
                $line = (string) $value['group'] . ': ' . (string) $value['label'];
                $delta = (float) ($value['delta'] ?? $value['price_delta'] ?? 0);
                if ($delta != 0.0) {
                    $line .= ' (' . ($delta > 0 ? '+' : '') . '$' . number_format($delta, 2) . ')';
                }
                $lines[] = $line;
                continue;
            }
            $value = implode(', ', array_map('strval', $value));
        }
        if (is_string($key) && !is_numeric($key)) {
            $lines[] = $key . ': ' . (string) $value;
        } else {
            $lines[] = (string) $value;
        }
    }

    return $lines;
}

/**
 * Decode frozen build snapshot JSON.
 *
 * @return list<array{category:string,component:string,price:float}>
 */
function customcore_order_decode_build_snapshot(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $parts = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $parts[] = [
            'category' => (string) ($row['category'] ?? ''),
            'component' => (string) ($row['component'] ?? ''),
            'price' => (float) ($row['price'] ?? 0),
        ];
    }

    return $parts;
}

/**
 * Load a single order owned by the given user.
 *
 * Ownership is enforced in SQL (id + user_id). Returns null when the order
 * does not exist or belongs to someone else — callers must treat both the same
 * so order IDs are not enumerable across accounts.
 *
 * @return array<string, mixed>|null
 */
function customcore_order_fetch_owned(PDO $pdo, int $orderId, int $userId): ?array
{
    if ($orderId < 1 || $userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, order_number, status, subtotal, total,
                shipping_name, shipping_phone, shipping_addr1, shipping_addr2,
                shipping_city, shipping_prov, shipping_postal, payment_method,
                created_at, updated_at
         FROM orders
         WHERE id = :id AND user_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':id' => $orderId, ':uid' => $userId]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Load order line items, scoped through the owning order.
 *
 * Joins orders so items for another user's order_id cannot leak even if a
 * caller accidentally skips the ownership check on the parent order.
 *
 * @return list<array<string, mixed>>
 */
function customcore_order_fetch_items(PDO $pdo, int $orderId, int $userId): array
{
    if ($orderId < 1 || $userId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT oi.id, oi.item_type, oi.product_id, oi.saved_build_id, oi.item_name,
                oi.quantity, oi.unit_price, oi.line_total, oi.options_json,
                oi.build_snapshot_json, oi.created_at
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE oi.order_id = :oid AND o.user_id = :uid
         ORDER BY oi.id ASC'
    );
    $stmt->execute([':oid' => $orderId, ':uid' => $userId]);

    return $stmt->fetchAll();
}

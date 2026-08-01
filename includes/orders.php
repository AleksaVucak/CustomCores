<?php
/**
 * CustomCore — Shared order helpers (Commit 6.7+).
 *
 * File responsibility:
 *   Reusable labels and small utilities for customer order history,
 *   order details, and confirmation pages. Keeps status / payment display
 *   consistent across the order workflow.
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

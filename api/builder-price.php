<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Trusted Server-Side Price Recalculation.
// Accepts a JSON payload of component IDs (representing a build), looks up real prices from the
// database, and returns the authoritative per-component and total prices. Client-sent price values
// are completely ignored, only database prices are used. This prevents a manipulated browser
// (DevTools, tampered data-price attributes) from affecting actual pricing.
// Endpoint: POST api/builder-price.php Content-Type: application/json
// Request body: { "components": [1, 8, 15, 24, 28, 35, 40, 48, 54, 57] } Array of component IDs
// (int). Invalid/inactive IDs are excluded from totals.
// Response (200): { "success": true, "items": [ { "id": 1, "category_id": 1, "name": "AMD Ryzen 5
// 7600", "price": 229.00 }... ], "total": 1245.00, "count": 10 }
// Error responses (400/500): { "success": false, "error": "..." }
// Access: None (public). The builder is available to guests. Pricing is not secret.
// Security:
//   Rejects non-POST requests.
//   Validates that the payload is well-formed JSON with an array of integers.
//   Caps the number of component IDs to prevent abuse (max 20).
//   Only returns prices for active components, preventing probing of disabled items.
//   No client-sent prices are ever trusted or returned.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// Only accept POST

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Parse and validate the JSON request body

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request body is empty.']);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload) || !isset($payload['components'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON. Expected {"components": [...]}.']);
    exit;
}

$componentIds = $payload['components'];

if (!is_array($componentIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '"components" must be an array of integers.']);
    exit;
}

// Sanitize: keep only positive integers, cap at 20.
$maxComponents = 20;
$cleanIds = [];

foreach ($componentIds as $id) {
    if (is_int($id) && $id > 0) {
        $cleanIds[] = $id;
    } elseif (is_string($id) && ctype_digit($id) && (int) $id > 0) {
        $cleanIds[] = (int) $id;
    }
}

$cleanIds = array_unique($cleanIds);

if (count($cleanIds) === 0) {
    echo json_encode([
        'success' => true,
        'items' => [],
        'total' => 0.00,
        'count' => 0,
    ]);
    exit;
}

if (count($cleanIds) > $maxComponents) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Too many components. Maximum is ' . $maxComponents . '.',
    ]);
    exit;
}

// Query the database for trusted prices

try {
    $pdo = customcore_pdo();

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, component_category_id, name, price
         FROM components
         WHERE id IN ($placeholders) AND is_active = 1"
    );
    $stmt->execute(array_values($cleanIds));
    $rows = $stmt->fetchAll();

    $items = [];
    $total = 0.00;

    foreach ($rows as $row) {
        $price = (float) $row['price'];
        $items[] = [
            'id' => (int) $row['id'],
            'category_id' => (int) $row['component_category_id'],
            'name' => (string) $row['name'],
            'price' => $price,
        ];
        $total += $price;
    }

    // Round to avoid floating-point drift.
    $total = round($total, 2);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'total' => $total,
        'count' => count($items),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    $message = customcore_is_debug()
        ? $exception->getMessage()
        : 'Price calculation failed. Please try again.';
    echo json_encode(['success' => false, 'error' => $message]);
}

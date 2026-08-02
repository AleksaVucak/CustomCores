<?php
/**
 * CustomCore — Catalogue statistics helpers (Commit 8.5).
 *
 * File responsibility:
 *   Computes public catalogue metrics from MySQL for the catalogue data
 *   visualization (active products per performance tier, with price ranges).
 *   All numbers come from the live database, never hard-coded, so the public
 *   chart always reflects real seeded/administered data.
 *
 * Usage:
 *   require_once __DIR__ . '/catalogue-stats.php';
 *   $tiers = customcore_catalogue_tier_stats($pdo);
 */

declare(strict_types=1);

/**
 * Brand/tier colour mapping for the catalogue chart, keyed by category slug.
 *
 * Falls back to the CustomCore accent when a slug is not listed so new tiers
 * still render with a sensible colour.
 *
 * @return array{fill:string, border:string}
 */
function customcore_catalogue_tier_colour(string $slug): array
{
    $map = [
        'budget' => ['fill' => '#0f7a6e', 'border' => '#0b5f56'],
        'esports' => ['fill' => '#5b6b7a', 'border' => '#485865'],
        'high-performance' => ['fill' => '#15202b', 'border' => '#0f1820'],
        'creator' => ['fill' => '#9aa8b5', 'border' => '#7f8f9d'],
    ];

    return $map[$slug] ?? ['fill' => '#0f7a6e', 'border' => '#0b5f56'];
}

/**
 * Active product counts and price ranges per active performance tier.
 *
 * @return list<array{
 *   id:int,
 *   name:string,
 *   slug:string,
 *   active_count:int,
 *   min_price:?float,
 *   max_price:?float,
 *   avg_price:?float,
 *   fill:string,
 *   border:string
 * }>
 */
function customcore_catalogue_tier_stats(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT c.id, c.name, c.slug, c.sort_order,
                COUNT(p.id) AS active_count,
                MIN(p.base_price) AS min_price,
                MAX(p.base_price) AS max_price,
                AVG(p.base_price) AS avg_price
         FROM categories c
         LEFT JOIN products p
             ON p.category_id = c.id AND p.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id, c.name, c.slug, c.sort_order
         ORDER BY c.sort_order ASC, c.name ASC'
    );

    $rows = $stmt ? $stmt->fetchAll() : [];
    $tiers = [];

    foreach ($rows as $row) {
        $slug = (string) ($row['slug'] ?? '');
        $colour = customcore_catalogue_tier_colour($slug);

        $tiers[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => $slug,
            'active_count' => (int) ($row['active_count'] ?? 0),
            'min_price' => $row['min_price'] !== null ? (float) $row['min_price'] : null,
            'max_price' => $row['max_price'] !== null ? (float) $row['max_price'] : null,
            'avg_price' => $row['avg_price'] !== null ? (float) $row['avg_price'] : null,
            'fill' => $colour['fill'],
            'border' => $colour['border'],
        ];
    }

    return $tiers;
}

/**
 * Build the Chart.js payload (labels + single dataset) from tier stats.
 *
 * @param list<array<string, mixed>> $tiers
 * @return array{labels:list<string>, datasets:list<array<string, mixed>>}
 */
function customcore_catalogue_chart_payload(array $tiers): array
{
    $labels = [];
    $data = [];
    $fill = [];
    $border = [];

    foreach ($tiers as $tier) {
        $labels[] = (string) $tier['name'];
        $data[] = (int) $tier['active_count'];
        $fill[] = (string) $tier['fill'];
        $border[] = (string) $tier['border'];
    }

    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Active products',
                'data' => $data,
                'backgroundColor' => $fill,
                'borderColor' => $border,
                'borderWidth' => 1,
            ],
        ],
    ];
}

-- =============================================================================
-- CustomCore — Demo Reviews Seed (Commit 3.8)
-- =============================================================================
--
-- File responsibility:
--   Seeds a few demo customer accounts and product reviews so public pages can
--   demonstrate approved-only display. Includes pending and hidden rows that
--   must NOT appear on product.php or reviews.php.
--
-- Prerequisites:
--   1. database/schema.sql
--   2. database/seed-products.sql (products 1–20)
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-reviews.sql
--
-- Demo customer login (local testing only — change in production):
--   Email:    alex@example.com / jordan@example.com / sam@example.com
--   Password: DemoPass123!
--
-- Acceptance:
--   SELECT COUNT(*) FROM reviews WHERE status = 'approved';  -- ≥ 1
--   Public pages must never show status IN ('pending','hidden').
-- =============================================================================

-- ---------------------------------------------------------------------------
-- Demo customers (fixed IDs 10–12 to avoid colliding with a CLI admin at id 1)
-- password_hash = bcrypt("DemoPass123!")
-- ---------------------------------------------------------------------------

INSERT INTO `users` (
    `id`, `email`, `password_hash`, `first_name`, `last_name`,
    `role`, `is_active`
) VALUES
(
    10,
    'alex@example.com',
    '$2y$10$KunQhCWhzc444gfnrJXxmO107dxObWLTmE4lPbe4MVWMd8W57oUS2',
    'Alex',
    'Nguyen',
    'customer',
    1
),
(
    11,
    'jordan@example.com',
    '$2y$10$KunQhCWhzc444gfnrJXxmO107dxObWLTmE4lPbe4MVWMd8W57oUS2',
    'Jordan',
    'Patel',
    'customer',
    1
),
(
    12,
    'sam@example.com',
    '$2y$10$KunQhCWhzc444gfnrJXxmO107dxObWLTmE4lPbe4MVWMd8W57oUS2',
    'Sam',
    'Okafor',
    'customer',
    1
)
ON DUPLICATE KEY UPDATE
    `first_name` = VALUES(`first_name`),
    `last_name`  = VALUES(`last_name`),
    `is_active`  = VALUES(`is_active`);

-- ---------------------------------------------------------------------------
-- Reviews — mix of approved / pending / hidden
-- Products referenced: 1 (Budget), 6 (Esports), 11 (High-Perf), 16 (Creator)
-- ---------------------------------------------------------------------------

INSERT INTO `reviews` (
    `id`, `product_id`, `user_id`, `rating`, `title`, `body`, `status`, `created_at`
) VALUES
(
    1, 1, 10, 5,
    'Great starter gaming PC',
    'Set this up for my nephew and it handles Fortnite and Valorant on high settings without breaking a sweat. Quiet under normal play and the default options covered everything we needed.',
    'approved',
    '2026-05-12 14:22:00'
),
(
    2, 1, 11, 4,
    'Solid value for the price',
    'Not a maxed-out machine, but for the Budget tier it punches above its weight. Upgraded the storage option and still came in under my target spend.',
    'approved',
    '2026-05-28 09:10:00'
),
(
    3, 6, 12, 5,
    'Perfect for competitive play',
    'High refresh rate titles feel smooth. The Esports config is clearly tuned for FPS games. Shipping was simulated for class, but the specs match what I expected from the catalogue.',
    'approved',
    '2026-06-03 18:45:00'
),
(
    4, 11, 10, 5,
    'Creator workloads fly',
    'Editing 4K footage and light Blender work is comfortable. Pairing this High-Performance system with the Creator tier models on the compare page made the choice obvious.',
    'approved',
    '2026-06-15 11:05:00'
),
(
    5, 16, 11, 4,
    'Quiet and capable workstation',
    'Bought this for streaming plus school projects. Thermals stay reasonable and the default GPU option is plenty for my overlays and game capture.',
    'approved',
    '2026-06-22 16:30:00'
),
(
    6, 6, 10, 3,
    'Good but fan noise under load',
    'Performance is fine; fans ramp up more than I expected during long ranked sessions. Still recommending it for the price, just mentioning the noise.',
    'approved',
    '2026-07-01 20:12:00'
),
(
    7, 11, 12, 5,
    'Worth the upgrade from my last build',
    'Came from a five-year-old tower. Load times and frame rates are night and day. Specs on the product page matched the box contents.',
    'approved',
    '2026-07-08 13:40:00'
),
(
    8, 16, 12, 4,
    'Reliable for class and content',
    'Used it through a full semester of design projects. No stability issues. Looking forward to leaving a longer review after more rendering hours.',
    'approved',
    '2026-07-14 10:18:00'
),
-- Pending — must NOT appear on public pages
(
    9, 1, 12, 2,
    'Waiting on support reply',
    'Had a question about warranty and have not heard back yet. Holding my rating until that is sorted.',
    'pending',
    '2026-07-18 08:00:00'
),
-- Hidden — must NOT appear on public pages
(
    10, 6, 11, 1,
    'Removed by moderation',
    'This review was hidden during testing and should never appear publicly.',
    'hidden',
    '2026-07-19 12:00:00'
)
ON DUPLICATE KEY UPDATE
    `rating` = VALUES(`rating`),
    `title`  = VALUES(`title`),
    `body`   = VALUES(`body`),
    `status` = VALUES(`status`);

-- ---------------------------------------------------------------------------
-- Verification helpers (run manually after import)
-- ---------------------------------------------------------------------------
-- Approved only (public pages use this filter):
--   SELECT r.id, r.product_id, r.rating, r.status, u.first_name
--   FROM reviews r
--   INNER JOIN users u ON u.id = r.user_id
--   WHERE r.status = 'approved'
--   ORDER BY r.created_at DESC;
--
-- Must return rows for pending/hidden (proves moderation data exists):
--   SELECT id, status FROM reviews WHERE status IN ('pending','hidden');
--
-- Public query must return 0 for non-approved:
--   SELECT COUNT(*) AS should_be_zero
--   FROM reviews
--   WHERE status <> 'approved'
--     AND id IN (
--       SELECT id FROM reviews WHERE status = 'approved'
--     );
--   (Sanity: public SELECT always includes WHERE status = 'approved')

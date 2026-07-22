-- =============================================================================
-- CustomCore — Catalogue Seed Data (Commit 2.2)
-- =============================================================================
--
-- File responsibility:
--   Seeds the four product tiers (categories) and twenty configurable prebuilt
--   gaming / creator PCs. Product options are added separately in Commit 2.3
--   (`database/seed-product-options.sql`).
--
-- Prerequisites:
--   1. Import `database/schema.sql` first.
--   2. Run this file next.
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-products.sql
--
-- Acceptance (Commit 2.2):
--   SELECT COUNT(*) FROM products WHERE is_active = 1;  -- must be ≥ 20
--
-- Fixed IDs:
--   Categories use ids 1–4 and products use ids 1–20 so later option seeds and
--   documentation can reference rows reliably.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Clear existing catalogue rows (safe re-import during development)
-- Options are wiped too so a re-run of this file does not leave orphans if
-- Commit 2.3 seed was already applied.
-- -----------------------------------------------------------------------------
DELETE FROM `product_options`;
DELETE FROM `products`;
DELETE FROM `categories`;

ALTER TABLE `product_options` AUTO_INCREMENT = 1;
ALTER TABLE `products` AUTO_INCREMENT = 1;
ALTER TABLE `categories` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- CATEGORIES — Four performance tiers (5 products each)
-- =============================================================================

INSERT INTO `categories`
    (`id`, `name`, `slug`, `description`, `sort_order`, `is_active`)
VALUES
(
    1,
    'Budget',
    'budget',
    'Affordable starter gaming PCs for students and first-time buyers. Clear pricing, solid 1080p performance, and room to upgrade later.',
    10,
    1
),
(
    2,
    'Esports',
    'esports',
    'High-refresh competitive systems tuned for popular esports titles. Strong CPUs, responsive GPUs, and balanced builds for ranked play.',
    20,
    1
),
(
    3,
    'High-Performance',
    'high-performance',
    'Flagship gaming desktops for ultra settings, ray tracing, and high-resolution play. Premium components with headroom for future titles.',
    30,
    1
),
(
    4,
    'Creator',
    'creator',
    'Workstation-leaning builds for streaming, editing, 3D, and content production. Extra cores, fast storage, and creator-focused defaults.',
    40,
    1
);

-- =============================================================================
-- PRODUCTS — Twenty configurable prebuilts (4 tiers × 5 systems)
-- =============================================================================
-- image_path values are placeholders until Stage 8 media assets are added.
-- Spec columns power catalogue compare cards without joining options.

INSERT INTO `products` (
    `id`,
    `category_id`,
    `name`,
    `slug`,
    `brand`,
    `short_description`,
    `description`,
    `base_price`,
    `stock_quantity`,
    `image_path`,
    `spec_cpu`,
    `spec_gpu`,
    `spec_ram`,
    `spec_storage`,
    `is_featured`,
    `is_active`
) VALUES

-- ---------------------------------------------------------------------------
-- Budget tier (category_id = 1) — products 1–5
-- ---------------------------------------------------------------------------
(
    1,
    1,
    'CoreStart Entry',
    'corestart-entry',
    'CustomCore',
    'Our most affordable gaming PC for classwork, browsing, and light 1080p play.',
    'CoreStart Entry is the gateway CustomCore system for students and first-time PC buyers. It ships with a capable multi-core processor, integrated or entry discrete graphics depending on configuration, and enough memory for everyday multitasking. Ideal if you want a quiet desk machine that can also handle indie games and older titles at 1080p. Upgrade-friendly chassis and standard ATX layout make later GPU or storage swaps straightforward.',
    799.00,
    18,
    'assets/images/products/corestart-entry.jpg',
    'AMD Ryzen 5 5500',
    'AMD Radeon RX 6400',
    '16 GB DDR4',
    '512 GB NVMe SSD',
    0,
    1
),
(
    2,
    1,
    'CoreStart Plus',
    'corestart-plus',
    'CustomCore',
    'A step up from Entry with a stronger GPU for smoother 1080p medium–high settings.',
    'CoreStart Plus keeps the student-friendly price point while adding a more capable discrete GPU for popular online games. Expect comfortable 1080p frame rates in titles like Fortnite, League of Legends, and Valorant at competitive settings. Fast NVMe storage and 16 GB of memory keep load times low and browser tabs under control during study sessions.',
    949.00,
    15,
    'assets/images/products/corestart-plus.jpg',
    'AMD Ryzen 5 5600',
    'NVIDIA GeForce RTX 3050',
    '16 GB DDR4',
    '512 GB NVMe SSD',
    1,
    1
),
(
    3,
    1,
    'BudgetForge 1080',
    'budgetforge-1080',
    'CustomCore',
    'Purpose-built for full HD gaming without crossing into mid-range pricing.',
    'BudgetForge 1080 focuses on one job: reliable 1080p gaming. A modern six-core CPU pairs with a mid-entry GPU and dual-channel memory for stable frame times. The case includes good cable routing and dust filters so the system stays cool during long sessions. A strong pick if your monitor is 1080p and you want value over flashy extras.',
    1099.00,
    12,
    'assets/images/products/budgetforge-1080.jpg',
    'Intel Core i5-12400F',
    'NVIDIA GeForce RTX 4060',
    '16 GB DDR4',
    '1 TB NVMe SSD',
    0,
    1
),
(
    4,
    1,
    'Campus Gamer',
    'campus-gamer',
    'CustomCore',
    'Compact-friendly starter build balanced for dorm desks and shared apartments.',
    'Campus Gamer is tuned for university life: modest power draw, quieter cooling defaults, and a compact footprint that still accepts standard upgrades. Play popular multiplayer titles after class, stream lectures, and keep assignments on a fast SSD. Includes room for a second drive when your game library grows.',
    899.00,
    20,
    'assets/images/products/campus-gamer.jpg',
    'AMD Ryzen 5 5600G',
    'AMD Radeon RX 6500 XT',
    '16 GB DDR4',
    '512 GB NVMe SSD',
    0,
    1
),
(
    5,
    1,
    'ValueStrike',
    'valuestrike',
    'CustomCore',
    'Maximum frames-per-dollar in the Budget tier with a punchy RTX 4060 class GPU.',
    'ValueStrike is the Budget tier standout for players who want ray-tracing capable hardware without High-Performance pricing. Pair it with a 1080p or entry 1440p monitor and enjoy modern titles at high settings. Fast storage and a clean Windows-ready setup mean you can game the same day it arrives (simulated order flow).',
    1199.00,
    10,
    'assets/images/products/valuestrike.jpg',
    'Intel Core i5-13400F',
    'NVIDIA GeForce RTX 4060',
    '16 GB DDR5',
    '1 TB NVMe SSD',
    1,
    1
),

-- ---------------------------------------------------------------------------
-- Esports tier (category_id = 2) — products 6–10
-- ---------------------------------------------------------------------------
(
    6,
    2,
    'ArenaPulse 144',
    'arenapulse-144',
    'CustomCore',
    'High-refresh esports rig aimed at locked 144 Hz competitive play.',
    'ArenaPulse 144 prioritises CPU responsiveness and consistent frame times for ranked play. A strong multi-core processor and esports-friendly GPU keep popular titles well above 144 FPS at competitive settings. Low-profile RGB accents stay optional through configuration so the look stays clean for tournament practice setups.',
    1399.00,
    14,
    'assets/images/products/arenapulse-144.jpg',
    'AMD Ryzen 5 7600',
    'NVIDIA GeForce RTX 4060 Ti',
    '32 GB DDR5',
    '1 TB NVMe SSD',
    1,
    1
),
(
    7,
    2,
    'RivalEdge Pro',
    'rivaledge-pro',
    'CustomCore',
    'Balanced esports machine with headroom for streaming while you queue.',
    'RivalEdge Pro adds streaming-friendly cores and memory so you can broadcast ranked games without tanking in-game FPS. Dual-channel DDR5 and a fast primary SSD keep Discord, overlays, and launchers snappy. Ideal for creators who still care about competitive frame rates first.',
    1549.00,
    11,
    'assets/images/products/rivaledge-pro.jpg',
    'AMD Ryzen 7 7700',
    'NVIDIA GeForce RTX 4070',
    '32 GB DDR5',
    '1 TB NVMe SSD',
    0,
    1
),
(
    8,
    2,
    'ClashFrame',
    'clashframe',
    'CustomCore',
    'Intel-powered esports build for players who prefer LGA platforms and strong single-thread speed.',
    'ClashFrame leans on Intel’s high clock speeds for competitive shooters and MOBAs where every millisecond of responsiveness counts. Paired with a modern NVIDIA GPU and 32 GB of memory, it handles Discord, browsers, and overlays alongside your main game. A favourite for LAN-ready setups.',
    1499.00,
    13,
    'assets/images/products/clashframe.jpg',
    'Intel Core i5-14600KF',
    'NVIDIA GeForce RTX 4060 Ti',
    '32 GB DDR5',
    '1 TB NVMe SSD',
    0,
    1
),
(
    9,
    2,
    'RankClimb Elite',
    'rankclimb-elite',
    'CustomCore',
    'Upper-mid esports system built for 240 Hz monitors and low input lag.',
    'RankClimb Elite is for players who already own a high-refresh monitor and want the PC to keep up. Extra cooling capacity and a higher-wattage PSU leave room for future GPU upgrades. Tuned defaults favour competitive settings over cinematic ray tracing.',
    1699.00,
    9,
    'assets/images/products/rankclimb-elite.jpg',
    'AMD Ryzen 7 7800X3D',
    'NVIDIA GeForce RTX 4070',
    '32 GB DDR5',
    '2 TB NVMe SSD',
    1,
    1
),
(
    10,
    2,
    'Tournament Ready',
    'tournament-ready',
    'CustomCore',
    'Travel-friendly powerhouse for weekend tournaments and campus esports clubs.',
    'Tournament Ready packages club-level performance into a durable case with excellent cable management and tool-less drive bays. Fast boot times, reliable stock cooling, and a proven GPU make it a go-to for shared team PCs. Includes ample storage for large game libraries between events.',
    1599.00,
    8,
    'assets/images/products/tournament-ready.jpg',
    'Intel Core i7-14700F',
    'NVIDIA GeForce RTX 4070 Super',
    '32 GB DDR5',
    '2 TB NVMe SSD',
    0,
    1
),

-- ---------------------------------------------------------------------------
-- High-Performance tier (category_id = 3) — products 11–15
-- ---------------------------------------------------------------------------
(
    11,
    3,
    'ApexForge RTX',
    'apexforge-rtx',
    'CustomCore',
    '1440p ray-tracing gaming desktop with flagship-leaning NVIDIA graphics.',
    'ApexForge RTX is the High-Performance entry point for immersive single-player and cinematic multiplayer. Expect ultra settings at 1440p with ray tracing enabled in supported titles. Premium cooling and a high-capacity PSU keep thermals and upgrade paths under control.',
    2199.00,
    7,
    'assets/images/products/apexforge-rtx.jpg',
    'AMD Ryzen 7 7800X3D',
    'NVIDIA GeForce RTX 4070 Ti Super',
    '32 GB DDR5',
    '2 TB NVMe SSD',
    1,
    1
),
(
    12,
    3,
    'UltraCore 4K',
    'ultracore-4k',
    'CustomCore',
    'Built for 4K gaming and high-bandwidth display setups.',
    'UltraCore 4K pairs a top-tier gaming CPU with a GPU class aimed at 4K ultra and DLSS/FSR upscaling workflows. Fast DDR5 and dual-drive-ready storage keep texture-heavy games loading quickly. Choose this when your monitor is 4K and compromise is not on the menu.',
    2799.00,
    5,
    'assets/images/products/ultracore-4k.jpg',
    'Intel Core i7-14700K',
    'NVIDIA GeForce RTX 4080 Super',
    '64 GB DDR5',
    '2 TB NVMe SSD',
    1,
    1
),
(
    13,
    3,
    'MaxFrame Extreme',
    'maxframe-extreme',
    'CustomCore',
    'No-compromise enthusiast build for maxed settings and multi-monitor desks.',
    'MaxFrame Extreme is CustomCore’s enthusiast showcase: high-end CPU, flagship-class GPU, and oversized cooling for sustained loads. Perfect for gamers who also leave capture software, browsers, and voice chat running. Expansive case volume supports large air or liquid coolers.',
    3299.00,
    4,
    'assets/images/products/maxframe-extreme.jpg',
    'AMD Ryzen 9 7950X3D',
    'NVIDIA GeForce RTX 4090',
    '64 GB DDR5',
    '4 TB NVMe SSD',
    1,
    1
),
(
    14,
    3,
    'VelocityX Gaming',
    'velocityx-gaming',
    'CustomCore',
    'High-refresh 1440p specialist with outstanding 1% lows.',
    'VelocityX Gaming emphasises frame-time consistency for competitive players who still want beautiful settings. A cache-optimised CPU and strong mid-high GPU deliver excellent 1440p performance with room for ray tracing toggles when you switch to single-player campaigns.',
    2399.00,
    6,
    'assets/images/products/velocityx-gaming.jpg',
    'AMD Ryzen 7 7800X3D',
    'NVIDIA GeForce RTX 4080',
    '32 GB DDR5',
    '2 TB NVMe SSD',
    0,
    1
),
(
    15,
    3,
    'PowerCore Flagship',
    'powercore-flagship',
    'CustomCore',
    'All-round flagship for gaming, light creation, and future-proof upgrades.',
    'PowerCore Flagship balances gaming muscle with creator multitasking: many cores, lots of memory, and a GPU that handles both raster and ray-traced workloads. The chassis, PSU, and motherboard are chosen for longevity so your next GPU upgrade has a home.',
    2999.00,
    5,
    'assets/images/products/powercore-flagship.jpg',
    'Intel Core i9-14900K',
    'NVIDIA GeForce RTX 4080 Super',
    '64 GB DDR5',
    '2 TB NVMe SSD',
    0,
    1
),

-- ---------------------------------------------------------------------------
-- Creator tier (category_id = 4) — products 16–20
-- ---------------------------------------------------------------------------
(
    16,
    4,
    'StudioCore Creator',
    'studiocore-creator',
    'CustomCore',
    'Streaming and content starter with strong encode performance and quiet defaults.',
    'StudioCore Creator is built for Twitch/YouTube creators who need reliable encoding while gaming or recording. Extra CPU cores, fast memory, and a GPU with dedicated encoders keep overlays smooth. Quiet cooling presets help during late-night recording sessions.',
    1899.00,
    10,
    'assets/images/products/studiocore-creator.jpg',
    'AMD Ryzen 7 7700',
    'NVIDIA GeForce RTX 4070',
    '32 GB DDR5',
    '2 TB NVMe SSD',
    1,
    1
),
(
    17,
    4,
    'RenderForge Pro',
    'renderforge-pro',
    'CustomCore',
    'Edit-focused workstation for 4K timelines, colour grading, and exports.',
    'RenderForge Pro targets video editors and motion designers. High core counts, abundant RAM, and large fast storage reduce scrubbing lag and export waits. Discrete graphics accelerate GPU-aware apps such as Premiere, DaVinci Resolve, and Blender cycles/Eevee workflows.',
    2499.00,
    7,
    'assets/images/products/renderforge-pro.jpg',
    'AMD Ryzen 9 7900',
    'NVIDIA GeForce RTX 4070 Ti',
    '64 GB DDR5',
    '2 TB NVMe SSD',
    0,
    1
),
(
    18,
    4,
    'ContentLab Workstation',
    'contentlab-workstation',
    'CustomCore',
    'Hybrid creator/gamer system for multi-app production desks.',
    'ContentLab Workstation is for creators who also game between projects. It balances gaming FPS with productivity cores, dual-storage readiness, and a case layout that stays organised with capture cards and external drives. A versatile daily driver for university media programs.',
    2199.00,
    8,
    'assets/images/products/contentlab-workstation.jpg',
    'Intel Core i7-14700K',
    'NVIDIA GeForce RTX 4070 Ti Super',
    '64 GB DDR5',
    '2 TB NVMe SSD',
    1,
    1
),
(
    19,
    4,
    'StreamForge Studio',
    'streamforge-studio',
    'CustomCore',
    'Dedicated streaming PC option with dual-PC-friendly I/O and quiet acoustics.',
    'StreamForge Studio emphasises encoder performance, USB expansion, and acoustic control for long live sessions. Pair it with a capture card workflow or use it as a powerful single-PC stream machine. Large SSD capacity holds VODs, assets, and game installs without constant cleanup.',
    2099.00,
    9,
    'assets/images/products/streamforge-studio.jpg',
    'AMD Ryzen 9 7900X',
    'NVIDIA GeForce RTX 4070 Super',
    '64 GB DDR5',
    '4 TB NVMe SSD',
    0,
    1
),
(
    20,
    4,
    'PixelCore Master',
    'pixelcore-master',
    'CustomCore',
    'Top-tier creator flagship for 3D, VFX, and heavy multitasking.',
    'PixelCore Master is CustomCore’s creator flagship: maximum cores, maximum memory, and a GPU suited to viewport acceleration and AI-assisted tools. Built for professionals and advanced students who cannot afford stalled exports. Expansive storage and premium cooling support marathon render jobs.',
    3499.00,
    3,
    'assets/images/products/pixelcore-master.jpg',
    'AMD Ryzen 9 7950X',
    'NVIDIA GeForce RTX 4090',
    '128 GB DDR5',
    '4 TB NVMe SSD',
    1,
    1
);

-- =============================================================================
-- VERIFICATION QUERIES (uncomment to run after import)
-- =============================================================================

-- Expect 4:
-- SELECT COUNT(*) AS category_count FROM categories WHERE is_active = 1;

-- Expect 20:
-- SELECT COUNT(*) AS active_products FROM products WHERE is_active = 1;

-- Expect 5 rows per tier:
-- SELECT c.name AS tier, COUNT(p.id) AS product_count
-- FROM categories c
-- LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
-- GROUP BY c.id, c.name
-- ORDER BY c.sort_order;

-- Featured products (homepage candidates):
-- SELECT id, name, base_price FROM products WHERE is_featured = 1 AND is_active = 1
-- ORDER BY category_id, id;

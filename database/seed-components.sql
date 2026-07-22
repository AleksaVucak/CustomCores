-- =============================================================================
-- CustomCore — Custom Builder Components Seed (Commit 2.4)
-- =============================================================================
--
-- File responsibility:
--   Seeds builder step categories and the component inventory used by the
--   custom PC builder. Attribute columns are filled consistently so Commit 2.5
--   compatibility rules (and Stage 5 checkers) can evaluate:
--     1. CPU socket = motherboard socket
--     2. RAM type = motherboard RAM type
--     3. Motherboard form factor fits case
--     4. PSU wattage ≥ estimated build draw
--     5. Case GPU clearance ≥ GPU length
--     6. Case cooler clearance / liquid support
--     7. Motherboard supports storage interface
--
-- Prerequisites:
--   1. Import `database/schema.sql`
--   (Catalogue seeds 2.2–2.3 are independent of this file.)
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-components.sql
--
-- Acceptance (Commit 2.4):
--   Every builder category has at least one active component.
--
-- Fixed IDs:
--   Categories 1–10 and components 1–N are stable for later seeds/docs.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Clear dependent rows that may reference components (safe re-import)
DELETE FROM `saved_build_items`;
DELETE FROM `components`;
DELETE FROM `component_categories`;

ALTER TABLE `saved_build_items` AUTO_INCREMENT = 1;
ALTER TABLE `components` AUTO_INCREMENT = 1;
ALTER TABLE `component_categories` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- COMPONENT CATEGORIES — Builder steps
-- =============================================================================

INSERT INTO `component_categories`
    (`id`, `name`, `slug`, `sort_order`, `is_required`)
VALUES

(1, 'CPU', 'cpu', 10, 1),
(2, 'Motherboard', 'motherboard', 20, 1),
(3, 'GPU', 'gpu', 30, 1),
(4, 'RAM', 'ram', 40, 1),
(5, 'Storage', 'storage', 50, 1),
(6, 'PSU', 'psu', 60, 1),
(7, 'Case', 'case', 70, 1),
(8, 'Cooling', 'cooling', 80, 1),
(9, 'OS', 'os', 90, 1),
(10, 'Service', 'service', 100, 0);


-- =============================================================================
-- COMPONENTS — Builder inventory
-- =============================================================================
-- NULL means the attribute does not apply to that part type.

INSERT INTO `components` (
    `id`,
    `component_category_id`,
    `name`,
    `brand`,
    `price`,
    `wattage_estimate`,
    `socket`,
    `ram_type`,
    `form_factor`,
    `gpu_length_mm`,
    `max_gpu_length_mm`,
    `cooler_height_mm`,
    `max_cooler_height_mm`,
    `cooler_type`,
    `storage_interface`,
    `supported_storage`,
    `psu_wattage`,
    `performance_gaming`,
    `performance_productivity`,
    `image_path`,
    `is_active`
) VALUES

-- ----- CPU -----
-- AMD Ryzen 5 7600
(1, 1, 'AMD Ryzen 5 7600', 'AMD', 229.00, 65, 'AM5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 72, 70, '', 1),
-- AMD Ryzen 7 7800X3D
(2, 1, 'AMD Ryzen 7 7800X3D', 'AMD', 449.00, 120, 'AM5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 95, 78, '', 1),
-- AMD Ryzen 9 7950X
(3, 1, 'AMD Ryzen 9 7950X', 'AMD', 549.00, 170, 'AM5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 88, 96, '', 1),
-- Intel Core i5-14600KF
(4, 1, 'Intel Core i5-14600KF', 'Intel', 319.00, 125, 'LGA1700', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 80, 82, '', 1),
-- Intel Core i7-14700K
(5, 1, 'Intel Core i7-14700K', 'Intel', 419.00, 125, 'LGA1700', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 86, 90, '', 1),
-- Intel Core i9-14900K
(6, 1, 'Intel Core i9-14900K', 'Intel', 589.00, 125, 'LGA1700', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 92, 95, '', 1),
-- AMD Ryzen 5 5600
(7, 1, 'AMD Ryzen 5 5600', 'AMD', 149.00, 65, 'AM4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 58, 55, '', 1),
-- ----- Motherboard -----
-- ASUS TUF GAMING B650-PLUS WIFI
(8, 2, 'ASUS TUF GAMING B650-PLUS WIFI', 'ASUS', 219.00, 40, 'AM5', 'DDR5', 'ATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- MSI MAG B650 TOMAHAWK WIFI
(9, 2, 'MSI MAG B650 TOMAHAWK WIFI', 'MSI', 249.00, 40, 'AM5', 'DDR5', 'ATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- Gigabyte B650M DS3H
(10, 2, 'Gigabyte B650M DS3H', 'Gigabyte', 149.00, 35, 'AM5', 'DDR5', 'mATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- ASRock B650I Lightning WiFi
(11, 2, 'ASRock B650I Lightning WiFi', 'ASRock', 199.00, 30, 'AM5', 'DDR5', 'ITX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe', NULL, NULL, NULL, '', 1),
-- ASUS PRIME Z790-P WIFI
(12, 2, 'ASUS PRIME Z790-P WIFI', 'ASUS', 279.00, 45, 'LGA1700', 'DDR5', 'ATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- MSI PRO B760M-A WIFI
(13, 2, 'MSI PRO B760M-A WIFI', 'MSI', 169.00, 35, 'LGA1700', 'DDR5', 'mATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- Gigabyte B550 AORUS ELITE
(14, 2, 'Gigabyte B550 AORUS ELITE', 'Gigabyte', 159.00, 40, 'AM4', 'DDR4', 'ATX', NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe,SATA', NULL, NULL, NULL, '', 1),
-- ----- GPU -----
-- NVIDIA GeForce RTX 4060
(15, 3, 'NVIDIA GeForce RTX 4060', 'NVIDIA', 299.00, 115, NULL, NULL, NULL, 244, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 70, 55, '', 1),
-- NVIDIA GeForce RTX 4060 Ti
(16, 3, 'NVIDIA GeForce RTX 4060 Ti', 'NVIDIA', 399.00, 160, NULL, NULL, NULL, 253, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 76, 58, '', 1),
-- NVIDIA GeForce RTX 4070 Super
(17, 3, 'NVIDIA GeForce RTX 4070 Super', 'NVIDIA', 599.00, 220, NULL, NULL, NULL, 305, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 85, 68, '', 1),
-- NVIDIA GeForce RTX 4080 Super
(18, 3, 'NVIDIA GeForce RTX 4080 Super', 'NVIDIA', 1049.00, 320, NULL, NULL, NULL, 310, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 94, 78, '', 1),
-- NVIDIA GeForce RTX 4090
(19, 3, 'NVIDIA GeForce RTX 4090', 'NVIDIA', 1899.00, 450, NULL, NULL, NULL, 336, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, 88, '', 1),
-- AMD Radeon RX 7600
(20, 3, 'AMD Radeon RX 7600', 'AMD', 279.00, 165, NULL, NULL, NULL, 267, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 68, 50, '', 1),
-- AMD Radeon RX 7800 XT
(21, 3, 'AMD Radeon RX 7800 XT', 'AMD', 519.00, 263, NULL, NULL, NULL, 287, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 84, 62, '', 1),
-- ----- RAM -----
-- Corsair Vengeance 16GB (2x8) DDR4-3200
(22, 4, 'Corsair Vengeance 16GB (2x8) DDR4-3200', 'Corsair', 49.00, 10, NULL, 'DDR4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 40, 40, '', 1),
-- Corsair Vengeance 32GB (2x16) DDR4-3600
(23, 4, 'Corsair Vengeance 32GB (2x16) DDR4-3600', 'Corsair', 89.00, 12, NULL, 'DDR4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 48, 48, '', 1),
-- G.Skill Flare X5 32GB (2x16) DDR5-6000
(24, 4, 'G.Skill Flare X5 32GB (2x16) DDR5-6000', 'G.Skill', 109.00, 15, NULL, 'DDR5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 70, 72, '', 1),
-- Corsair Dominator Platinum 64GB (2x32) DDR5-6000
(25, 4, 'Corsair Dominator Platinum 64GB (2x32) DDR5-6000', 'Corsair', 249.00, 18, NULL, 'DDR5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 78, 85, '', 1),
-- Kingston Fury Beast 64GB (2x32) DDR5-5600
(26, 4, 'Kingston Fury Beast 64GB (2x32) DDR5-5600', 'Kingston', 199.00, 16, NULL, 'DDR5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 74, 80, '', 1),
-- G.Skill Trident Z5 RGB 128GB (4x32) DDR5-6000
(27, 4, 'G.Skill Trident Z5 RGB 128GB (4x32) DDR5-6000', 'G.Skill', 449.00, 25, NULL, 'DDR5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 82, 95, '', 1),
-- ----- Storage -----
-- Samsung 990 PRO 1TB NVMe
(28, 5, 'Samsung 990 PRO 1TB NVMe', 'Samsung', 129.00, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe', NULL, NULL, 60, 70, '', 1),
-- Samsung 990 PRO 2TB NVMe
(29, 5, 'Samsung 990 PRO 2TB NVMe', 'Samsung', 219.00, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe', NULL, NULL, 62, 75, '', 1),
-- WD Black SN850X 4TB NVMe
(30, 5, 'WD Black SN850X 4TB NVMe', 'Western Digital', 349.00, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe', NULL, NULL, 65, 80, '', 1),
-- Crucial MX500 1TB SATA SSD
(31, 5, 'Crucial MX500 1TB SATA SSD', 'Crucial', 79.00, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SATA', NULL, NULL, 35, 40, '', 1),
-- Seagate Barracuda 2TB HDD
(32, 5, 'Seagate Barracuda 2TB HDD', 'Seagate', 59.00, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SATA', NULL, NULL, 20, 25, '', 1),
-- Kingston NV2 512GB NVMe
(33, 5, 'Kingston NV2 512GB NVMe', 'Kingston', 49.00, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NVMe', NULL, NULL, 45, 50, '', 1),
-- ----- PSU -----
-- Corsair CX550 550W 80+ Bronze
(34, 6, 'Corsair CX550 550W 80+ Bronze', 'Corsair', 69.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 550, NULL, NULL, '', 1),
-- Corsair RM750e 750W 80+ Gold
(35, 6, 'Corsair RM750e 750W 80+ Gold', 'Corsair', 119.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 750, NULL, NULL, '', 1),
-- Seasonic FOCUS GX-850 850W 80+ Gold
(36, 6, 'Seasonic FOCUS GX-850 850W 80+ Gold', 'Seasonic', 149.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 850, NULL, NULL, '', 1),
-- Corsair RM1000e 1000W 80+ Gold
(37, 6, 'Corsair RM1000e 1000W 80+ Gold', 'Corsair', 189.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1000, NULL, NULL, '', 1),
-- be quiet! Dark Power 13 1300W
(38, 6, 'be quiet! Dark Power 13 1300W', 'be quiet!', 329.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1300, NULL, NULL, '', 1),
-- EVGA 450 BR 450W 80+ Bronze
(39, 6, 'EVGA 450 BR 450W 80+ Bronze', 'EVGA', 49.00, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 450, NULL, NULL, '', 1),
-- ----- Case -----
-- Fractal Design North ATX
(40, 7, 'Fractal Design North ATX', 'Fractal Design', 149.00, 0, NULL, NULL, 'ATX', NULL, 355, NULL, 170, 'air,liquid', NULL, NULL, NULL, NULL, NULL, '', 1),
-- Lian Li Lancool 216
(41, 7, 'Lian Li Lancool 216', 'Lian Li', 109.00, 0, NULL, NULL, 'ATX', NULL, 392, NULL, 180, 'air,liquid', NULL, NULL, NULL, NULL, NULL, '', 1),
-- NZXT H5 Flow
(42, 7, 'NZXT H5 Flow', 'NZXT', 94.00, 0, NULL, NULL, 'ATX', NULL, 365, NULL, 165, 'air,liquid', NULL, NULL, NULL, NULL, NULL, '', 1),
-- Cooler Master NR200P
(43, 7, 'Cooler Master NR200P', 'Cooler Master', 109.00, 0, NULL, NULL, 'ITX', NULL, 330, NULL, 155, 'air,liquid', NULL, NULL, NULL, NULL, NULL, '', 1),
-- Fractal Design Node 202
(44, 7, 'Fractal Design Node 202', 'Fractal Design', 99.00, 0, NULL, NULL, 'ITX', NULL, 310, NULL, 56, 'air', NULL, NULL, NULL, NULL, NULL, '', 1),
-- Corsair 4000D Airflow
(45, 7, 'Corsair 4000D Airflow', 'Corsair', 104.00, 0, NULL, NULL, 'ATX', NULL, 360, NULL, 170, 'air,liquid', NULL, NULL, NULL, NULL, NULL, '', 1),
-- SilverStone SG13
(46, 7, 'SilverStone SG13', 'SilverStone', 59.00, 0, NULL, NULL, 'ITX', NULL, 266, NULL, 61, 'air', NULL, NULL, NULL, NULL, NULL, '', 1),
-- ----- Cooling -----
-- Stock AMD Wraith Stealth
(47, 8, 'Stock AMD Wraith Stealth', 'AMD', 0.00, 5, NULL, NULL, NULL, NULL, NULL, 65, NULL, 'air', NULL, NULL, NULL, NULL, 30, '', 1),
-- Cooler Master Hyper 212
(48, 8, 'Cooler Master Hyper 212', 'Cooler Master', 39.00, 5, NULL, NULL, NULL, NULL, NULL, 159, NULL, 'air', NULL, NULL, NULL, NULL, 45, '', 1),
-- Noctua NH-D15
(49, 8, 'Noctua NH-D15', 'Noctua', 109.00, 6, NULL, NULL, NULL, NULL, NULL, 165, NULL, 'air', NULL, NULL, NULL, NULL, 70, '', 1),
-- be quiet! Dark Rock Pro 4
(50, 8, 'be quiet! Dark Rock Pro 4', 'be quiet!', 89.00, 6, NULL, NULL, NULL, NULL, NULL, 163, NULL, 'air', NULL, NULL, NULL, NULL, 68, '', 1),
-- Corsair Nautilus 240 RS
(51, 8, 'Corsair Nautilus 240 RS', 'Corsair', 119.00, 10, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'liquid', NULL, NULL, NULL, NULL, 75, '', 1),
-- NZXT Kraken Elite 360
(52, 8, 'NZXT Kraken Elite 360', 'NZXT', 249.00, 12, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'liquid', NULL, NULL, NULL, NULL, 85, '', 1),
-- Thermalright Peerless Assassin 120
(53, 8, 'Thermalright Peerless Assassin 120', 'Thermalright', 35.00, 5, NULL, NULL, NULL, NULL, NULL, 155, NULL, 'air', NULL, NULL, NULL, NULL, 60, '', 1),
-- ----- OS -----
-- Windows 11 Home (OEM)
(54, 9, 'Windows 11 Home (OEM)', 'Microsoft', 139.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 50, '', 1),
-- Windows 11 Pro (OEM)
(55, 9, 'Windows 11 Pro (OEM)', 'Microsoft', 199.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 55, '', 1),
-- No operating system
(56, 9, 'No operating system', 'CustomCore', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 1),
-- ----- Service -----
-- Standard Assembly
(57, 10, 'Standard Assembly', 'CustomCore', 79.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 40, '', 1),
-- Premium Build + Cable Management
(58, 10, 'Premium Build + Cable Management', 'CustomCore', 129.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 55, '', 1),
-- Assembly + Stress Test + Setup
(59, 10, 'Assembly + Stress Test + Setup', 'CustomCore', 179.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 70, '', 1),
-- No assembly service
(60, 10, 'No assembly service', 'CustomCore', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 1);


-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

-- Expect 10 categories:
-- SELECT COUNT(*) AS category_count FROM component_categories;

-- Expect every category to have ≥ 1 active part (0 rows = pass):
-- SELECT cc.id, cc.name, COUNT(c.id) AS part_count
-- FROM component_categories cc
-- LEFT JOIN components c ON c.component_category_id = cc.id AND c.is_active = 1
-- GROUP BY cc.id, cc.name
-- HAVING COUNT(c.id) < 1;

-- Parts per category:
-- SELECT cc.name, COUNT(c.id) AS parts
-- FROM component_categories cc
-- LEFT JOIN components c ON c.component_category_id = cc.id AND c.is_active = 1
-- GROUP BY cc.id, cc.name
-- ORDER BY cc.sort_order;

-- Socket coverage (for 2.5 compatibility demos):
-- SELECT socket, COUNT(*) AS n FROM components
-- WHERE component_category_id IN (1, 2) AND socket IS NOT NULL
-- GROUP BY socket;

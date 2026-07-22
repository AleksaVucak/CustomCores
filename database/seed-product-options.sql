-- =============================================================================
-- CustomCore — Product Options Seed Data (Commit 2.3)
-- =============================================================================
--
-- File responsibility:
--   Seeds configurable options for all twenty catalogue products created in
--   Commit 2.2 (`database/seed-products.sql`). Every active product receives
--   multiple option groups (RAM, Storage, Colour, Warranty, plus OS / Cooling /
--   GPU where appropriate) so the rubric “≥ 2 options per product” rule is met
--   with comfortable headroom.
--
-- Prerequisites:
--   1. Import `database/schema.sql`
--   2. Import `database/seed-products.sql`
--   3. Import this file
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-product-options.sql
--
-- Acceptance (Commit 2.3):
--   The verification query below must return ZERO rows (no product with < 2
--   active options).
--
-- Notes:
--   - product_id values 1–20 match the fixed IDs from seed-products.sql
--   - price_delta is added to products.base_price at configure/checkout time
--   - Exactly one is_default = 1 option per option_group per product
-- =============================================================================

SET NAMES utf8mb4;

-- Safe re-import during development
DELETE FROM `product_options`;
ALTER TABLE `product_options` AUTO_INCREMENT = 1;

INSERT INTO `product_options`
    (`product_id`, `option_group`, `option_label`, `price_delta`, `is_default`, `is_active`, `sort_order`)
VALUES

-- ----- Budget (products 1–5) -----
-- Product 1: CoreStart Entry
(1, 'RAM', '16 GB DDR4', 0.00, 1, 1, 10),
(1, 'RAM', '32 GB DDR4', 90.00, 0, 1, 20),
(1, 'RAM', '64 GB DDR4', 250.00, 0, 1, 30),
(1, 'Storage', '512 GB NVMe SSD', 0.00, 1, 1, 10),
(1, 'Storage', '1 TB NVMe SSD', 80.00, 0, 1, 20),
(1, 'Storage', '2 TB NVMe SSD', 200.00, 0, 1, 30),
(1, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(1, 'Colour', 'White', 40.00, 0, 1, 20),
(1, 'Warranty', '1-Year Standard', 0.00, 1, 1, 10),
(1, 'Warranty', '2-Year Extended', 79.00, 0, 1, 20),
(1, 'Warranty', '3-Year Premium', 149.00, 0, 1, 30),
(1, 'OS', 'No OS (DIY install)', -40.00, 0, 1, 5),
(1, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(1, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 2: CoreStart Plus
(2, 'RAM', '16 GB DDR4', 0.00, 1, 1, 10),
(2, 'RAM', '32 GB DDR4', 90.00, 0, 1, 20),
(2, 'RAM', '64 GB DDR4', 250.00, 0, 1, 30),
(2, 'Storage', '512 GB NVMe SSD', 0.00, 1, 1, 10),
(2, 'Storage', '1 TB NVMe SSD', 80.00, 0, 1, 20),
(2, 'Storage', '2 TB NVMe SSD', 200.00, 0, 1, 30),
(2, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(2, 'Colour', 'White', 40.00, 0, 1, 20),
(2, 'Warranty', '1-Year Standard', 0.00, 1, 1, 10),
(2, 'Warranty', '2-Year Extended', 79.00, 0, 1, 20),
(2, 'Warranty', '3-Year Premium', 149.00, 0, 1, 30),
(2, 'OS', 'No OS (DIY install)', -40.00, 0, 1, 5),
(2, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(2, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 3: BudgetForge 1080
(3, 'RAM', '16 GB DDR4', 0.00, 1, 1, 10),
(3, 'RAM', '32 GB DDR4', 90.00, 0, 1, 20),
(3, 'RAM', '64 GB DDR4', 250.00, 0, 1, 30),
(3, 'Storage', '1 TB NVMe SSD', 0.00, 1, 1, 10),
(3, 'Storage', '2 TB NVMe SSD', 120.00, 0, 1, 20),
(3, 'Storage', '4 TB NVMe SSD', 320.00, 0, 1, 30),
(3, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(3, 'Colour', 'White', 40.00, 0, 1, 20),
(3, 'Warranty', '1-Year Standard', 0.00, 1, 1, 10),
(3, 'Warranty', '2-Year Extended', 79.00, 0, 1, 20),
(3, 'Warranty', '3-Year Premium', 149.00, 0, 1, 30),
(3, 'OS', 'No OS (DIY install)', -40.00, 0, 1, 5),
(3, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(3, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 4: Campus Gamer
(4, 'RAM', '16 GB DDR4', 0.00, 1, 1, 10),
(4, 'RAM', '32 GB DDR4', 90.00, 0, 1, 20),
(4, 'RAM', '64 GB DDR4', 250.00, 0, 1, 30),
(4, 'Storage', '512 GB NVMe SSD', 0.00, 1, 1, 10),
(4, 'Storage', '1 TB NVMe SSD', 80.00, 0, 1, 20),
(4, 'Storage', '2 TB NVMe SSD', 200.00, 0, 1, 30),
(4, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(4, 'Colour', 'White', 40.00, 0, 1, 20),
(4, 'Warranty', '1-Year Standard', 0.00, 1, 1, 10),
(4, 'Warranty', '2-Year Extended', 79.00, 0, 1, 20),
(4, 'Warranty', '3-Year Premium', 149.00, 0, 1, 30),
(4, 'OS', 'No OS (DIY install)', -40.00, 0, 1, 5),
(4, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(4, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 5: ValueStrike
(5, 'RAM', '16 GB DDR5', 0.00, 1, 1, 10),
(5, 'RAM', '32 GB DDR5', 120.00, 0, 1, 20),
(5, 'RAM', '64 GB DDR5', 320.00, 0, 1, 30),
(5, 'Storage', '1 TB NVMe SSD', 0.00, 1, 1, 10),
(5, 'Storage', '2 TB NVMe SSD', 120.00, 0, 1, 20),
(5, 'Storage', '4 TB NVMe SSD', 320.00, 0, 1, 30),
(5, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(5, 'Colour', 'White', 40.00, 0, 1, 20),
(5, 'Warranty', '1-Year Standard', 0.00, 1, 1, 10),
(5, 'Warranty', '2-Year Extended', 79.00, 0, 1, 20),
(5, 'Warranty', '3-Year Premium', 149.00, 0, 1, 30),
(5, 'OS', 'No OS (DIY install)', -40.00, 0, 1, 5),
(5, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(5, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),

-- ----- Esports (products 6–10) -----
-- Product 6: ArenaPulse 144
(6, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(6, 'RAM', '64 GB DDR5', 180.00, 0, 1, 20),
(6, 'Storage', '1 TB NVMe SSD', 0.00, 1, 1, 10),
(6, 'Storage', '2 TB NVMe SSD', 120.00, 0, 1, 20),
(6, 'Storage', '4 TB NVMe SSD', 320.00, 0, 1, 30),
(6, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(6, 'Colour', 'White', 50.00, 0, 1, 20),
(6, 'Colour', 'RGB Accent', 75.00, 0, 1, 30),
(6, 'Warranty', '2-Year Standard', 0.00, 1, 1, 10),
(6, 'Warranty', '3-Year Extended', 99.00, 0, 1, 20),
(6, 'Warranty', '3-Year + Accidental', 179.00, 0, 1, 30),
(6, 'Cooling', 'Stock Air Cooler', 0.00, 1, 1, 10),
(6, 'Cooling', 'Upgraded Dual-Tower Air', 65.00, 0, 1, 20),
(6, 'Cooling', '240 mm AIO Liquid', 130.00, 0, 1, 30),
(6, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(6, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 7: RivalEdge Pro
(7, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(7, 'RAM', '64 GB DDR5', 180.00, 0, 1, 20),
(7, 'Storage', '1 TB NVMe SSD', 0.00, 1, 1, 10),
(7, 'Storage', '2 TB NVMe SSD', 120.00, 0, 1, 20),
(7, 'Storage', '4 TB NVMe SSD', 320.00, 0, 1, 30),
(7, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(7, 'Colour', 'White', 50.00, 0, 1, 20),
(7, 'Colour', 'RGB Accent', 75.00, 0, 1, 30),
(7, 'Warranty', '2-Year Standard', 0.00, 1, 1, 10),
(7, 'Warranty', '3-Year Extended', 99.00, 0, 1, 20),
(7, 'Warranty', '3-Year + Accidental', 179.00, 0, 1, 30),
(7, 'Cooling', 'Stock Air Cooler', 0.00, 1, 1, 10),
(7, 'Cooling', 'Upgraded Dual-Tower Air', 65.00, 0, 1, 20),
(7, 'Cooling', '240 mm AIO Liquid', 130.00, 0, 1, 30),
(7, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(7, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 8: ClashFrame
(8, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(8, 'RAM', '64 GB DDR5', 180.00, 0, 1, 20),
(8, 'Storage', '1 TB NVMe SSD', 0.00, 1, 1, 10),
(8, 'Storage', '2 TB NVMe SSD', 120.00, 0, 1, 20),
(8, 'Storage', '4 TB NVMe SSD', 320.00, 0, 1, 30),
(8, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(8, 'Colour', 'White', 50.00, 0, 1, 20),
(8, 'Colour', 'RGB Accent', 75.00, 0, 1, 30),
(8, 'Warranty', '2-Year Standard', 0.00, 1, 1, 10),
(8, 'Warranty', '3-Year Extended', 99.00, 0, 1, 20),
(8, 'Warranty', '3-Year + Accidental', 179.00, 0, 1, 30),
(8, 'Cooling', 'Stock Air Cooler', 0.00, 1, 1, 10),
(8, 'Cooling', 'Upgraded Dual-Tower Air', 65.00, 0, 1, 20),
(8, 'Cooling', '240 mm AIO Liquid', 130.00, 0, 1, 30),
(8, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(8, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 9: RankClimb Elite
(9, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(9, 'RAM', '64 GB DDR5', 180.00, 0, 1, 20),
(9, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(9, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(9, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(9, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(9, 'Colour', 'White', 50.00, 0, 1, 20),
(9, 'Colour', 'RGB Accent', 75.00, 0, 1, 30),
(9, 'Warranty', '2-Year Standard', 0.00, 1, 1, 10),
(9, 'Warranty', '3-Year Extended', 99.00, 0, 1, 20),
(9, 'Warranty', '3-Year + Accidental', 179.00, 0, 1, 30),
(9, 'Cooling', 'Stock Air Cooler', 0.00, 1, 1, 10),
(9, 'Cooling', 'Upgraded Dual-Tower Air', 65.00, 0, 1, 20),
(9, 'Cooling', '240 mm AIO Liquid', 130.00, 0, 1, 30),
(9, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(9, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
-- Product 10: Tournament Ready
(10, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(10, 'RAM', '64 GB DDR5', 180.00, 0, 1, 20),
(10, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(10, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(10, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(10, 'Colour', 'Matte Black', 0.00, 1, 1, 10),
(10, 'Colour', 'White', 50.00, 0, 1, 20),
(10, 'Colour', 'RGB Accent', 75.00, 0, 1, 30),
(10, 'Warranty', '2-Year Standard', 0.00, 1, 1, 10),
(10, 'Warranty', '3-Year Extended', 99.00, 0, 1, 20),
(10, 'Warranty', '3-Year + Accidental', 179.00, 0, 1, 30),
(10, 'Cooling', 'Stock Air Cooler', 0.00, 1, 1, 10),
(10, 'Cooling', 'Upgraded Dual-Tower Air', 65.00, 0, 1, 20),
(10, 'Cooling', '240 mm AIO Liquid', 130.00, 0, 1, 30),
(10, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(10, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),

-- ----- High-Performance (products 11–15) -----
-- Product 11: ApexForge RTX
(11, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(11, 'RAM', '64 GB DDR5', 200.00, 0, 1, 20),
(11, 'RAM', '128 GB DDR5', 550.00, 0, 1, 30),
(11, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(11, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(11, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(11, 'Colour', 'Stealth Black', 0.00, 1, 1, 10),
(11, 'Colour', 'Arctic White', 60.00, 0, 1, 20),
(11, 'Colour', 'RGB Showcase', 120.00, 0, 1, 30),
(11, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(11, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(11, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(11, 'Cooling', 'High-Performance Air', 0.00, 1, 1, 10),
(11, 'Cooling', '240 mm AIO Liquid', 110.00, 0, 1, 20),
(11, 'Cooling', '360 mm AIO Liquid', 180.00, 0, 1, 30),
(11, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(11, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(11, 'GPU', 'Included GPU', 0.00, 1, 1, 10),
(11, 'GPU', 'One-tier GPU upgrade', 350.00, 0, 1, 20),
-- Product 12: UltraCore 4K
(12, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(12, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(12, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(12, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(12, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(12, 'Colour', 'Stealth Black', 0.00, 1, 1, 10),
(12, 'Colour', 'Arctic White', 60.00, 0, 1, 20),
(12, 'Colour', 'RGB Showcase', 120.00, 0, 1, 30),
(12, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(12, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(12, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(12, 'Cooling', 'High-Performance Air', 0.00, 1, 1, 10),
(12, 'Cooling', '240 mm AIO Liquid', 110.00, 0, 1, 20),
(12, 'Cooling', '360 mm AIO Liquid', 180.00, 0, 1, 30),
(12, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(12, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(12, 'GPU', 'Included GPU', 0.00, 1, 1, 10),
(12, 'GPU', 'One-tier GPU upgrade', 350.00, 0, 1, 20),
-- Product 13: MaxFrame Extreme
(13, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(13, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(13, 'Storage', '4 TB NVMe SSD', 0.00, 1, 1, 10),
(13, 'Storage', '4 TB NVMe + 4 TB HDD', 180.00, 0, 1, 20),
(13, 'Storage', '8 TB NVMe SSD', 480.00, 0, 1, 30),
(13, 'Colour', 'Stealth Black', 0.00, 1, 1, 10),
(13, 'Colour', 'Arctic White', 60.00, 0, 1, 20),
(13, 'Colour', 'RGB Showcase', 120.00, 0, 1, 30),
(13, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(13, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(13, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(13, 'Cooling', 'High-Performance Air', 0.00, 1, 1, 10),
(13, 'Cooling', '240 mm AIO Liquid', 110.00, 0, 1, 20),
(13, 'Cooling', '360 mm AIO Liquid', 180.00, 0, 1, 30),
(13, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(13, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(13, 'GPU', 'Included GPU', 0.00, 1, 1, 10),
(13, 'GPU', 'One-tier GPU upgrade', 350.00, 0, 1, 20),
-- Product 14: VelocityX Gaming
(14, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(14, 'RAM', '64 GB DDR5', 200.00, 0, 1, 20),
(14, 'RAM', '128 GB DDR5', 550.00, 0, 1, 30),
(14, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(14, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(14, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(14, 'Colour', 'Stealth Black', 0.00, 1, 1, 10),
(14, 'Colour', 'Arctic White', 60.00, 0, 1, 20),
(14, 'Colour', 'RGB Showcase', 120.00, 0, 1, 30),
(14, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(14, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(14, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(14, 'Cooling', 'High-Performance Air', 0.00, 1, 1, 10),
(14, 'Cooling', '240 mm AIO Liquid', 110.00, 0, 1, 20),
(14, 'Cooling', '360 mm AIO Liquid', 180.00, 0, 1, 30),
(14, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(14, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(14, 'GPU', 'Included GPU', 0.00, 1, 1, 10),
(14, 'GPU', 'One-tier GPU upgrade', 350.00, 0, 1, 20),
-- Product 15: PowerCore Flagship
(15, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(15, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(15, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(15, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(15, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(15, 'Colour', 'Stealth Black', 0.00, 1, 1, 10),
(15, 'Colour', 'Arctic White', 60.00, 0, 1, 20),
(15, 'Colour', 'RGB Showcase', 120.00, 0, 1, 30),
(15, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(15, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(15, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(15, 'Cooling', 'High-Performance Air', 0.00, 1, 1, 10),
(15, 'Cooling', '240 mm AIO Liquid', 110.00, 0, 1, 20),
(15, 'Cooling', '360 mm AIO Liquid', 180.00, 0, 1, 30),
(15, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(15, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(15, 'GPU', 'Included GPU', 0.00, 1, 1, 10),
(15, 'GPU', 'One-tier GPU upgrade', 350.00, 0, 1, 20),

-- ----- Creator (products 16–20) -----
-- Product 16: StudioCore Creator
(16, 'RAM', '32 GB DDR5', 0.00, 1, 1, 10),
(16, 'RAM', '64 GB DDR5', 200.00, 0, 1, 20),
(16, 'RAM', '128 GB DDR5', 520.00, 0, 1, 30),
(16, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(16, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(16, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(16, 'Colour', 'Studio Black', 0.00, 1, 1, 10),
(16, 'Colour', 'Clean White', 55.00, 0, 1, 20),
(16, 'Colour', 'Creator Silver', 70.00, 0, 1, 30),
(16, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(16, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(16, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(16, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(16, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(16, 'Cooling', 'Standard Creator Cooler', 0.00, 1, 1, 10),
(16, 'Cooling', 'Quiet-Optimized Air', 85.00, 0, 1, 20),
(16, 'Cooling', '360 mm AIO Liquid', 190.00, 0, 1, 30),
-- Product 17: RenderForge Pro
(17, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(17, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(17, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(17, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(17, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(17, 'Colour', 'Studio Black', 0.00, 1, 1, 10),
(17, 'Colour', 'Clean White', 55.00, 0, 1, 20),
(17, 'Colour', 'Creator Silver', 70.00, 0, 1, 30),
(17, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(17, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(17, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(17, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(17, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(17, 'Cooling', 'Standard Creator Cooler', 0.00, 1, 1, 10),
(17, 'Cooling', 'Quiet-Optimized Air', 85.00, 0, 1, 20),
(17, 'Cooling', '360 mm AIO Liquid', 190.00, 0, 1, 30),
-- Product 18: ContentLab Workstation
(18, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(18, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(18, 'Storage', '2 TB NVMe SSD', 0.00, 1, 1, 10),
(18, 'Storage', '4 TB NVMe SSD', 220.00, 0, 1, 20),
(18, 'Storage', '2 TB NVMe + 2 TB HDD', 150.00, 0, 1, 30),
(18, 'Colour', 'Studio Black', 0.00, 1, 1, 10),
(18, 'Colour', 'Clean White', 55.00, 0, 1, 20),
(18, 'Colour', 'Creator Silver', 70.00, 0, 1, 30),
(18, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(18, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(18, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(18, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(18, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(18, 'Cooling', 'Standard Creator Cooler', 0.00, 1, 1, 10),
(18, 'Cooling', 'Quiet-Optimized Air', 85.00, 0, 1, 20),
(18, 'Cooling', '360 mm AIO Liquid', 190.00, 0, 1, 30),
-- Product 19: StreamForge Studio
(19, 'RAM', '64 GB DDR5', 0.00, 1, 1, 10),
(19, 'RAM', '128 GB DDR5', 380.00, 0, 1, 20),
(19, 'Storage', '4 TB NVMe SSD', 0.00, 1, 1, 10),
(19, 'Storage', '4 TB NVMe + 4 TB HDD', 180.00, 0, 1, 20),
(19, 'Storage', '8 TB NVMe SSD', 480.00, 0, 1, 30),
(19, 'Colour', 'Studio Black', 0.00, 1, 1, 10),
(19, 'Colour', 'Clean White', 55.00, 0, 1, 20),
(19, 'Colour', 'Creator Silver', 70.00, 0, 1, 30),
(19, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(19, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(19, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(19, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(19, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(19, 'Cooling', 'Standard Creator Cooler', 0.00, 1, 1, 10),
(19, 'Cooling', 'Quiet-Optimized Air', 85.00, 0, 1, 20),
(19, 'Cooling', '360 mm AIO Liquid', 190.00, 0, 1, 30),
-- Product 20: PixelCore Master
(20, 'RAM', '128 GB DDR5', 0.00, 1, 1, 10),
(20, 'RAM', '192 GB DDR5', 450.00, 0, 1, 20),
(20, 'Storage', '4 TB NVMe SSD', 0.00, 1, 1, 10),
(20, 'Storage', '4 TB NVMe + 4 TB HDD', 180.00, 0, 1, 20),
(20, 'Storage', '8 TB NVMe SSD', 480.00, 0, 1, 30),
(20, 'Colour', 'Studio Black', 0.00, 1, 1, 10),
(20, 'Colour', 'Clean White', 55.00, 0, 1, 20),
(20, 'Colour', 'Creator Silver', 70.00, 0, 1, 30),
(20, 'Warranty', '3-Year Standard', 0.00, 1, 1, 10),
(20, 'Warranty', '3-Year Premium On-Site', 149.00, 0, 1, 20),
(20, 'Warranty', '5-Year Ultimate', 299.00, 0, 1, 30),
(20, 'OS', 'Windows 11 Home', 0.00, 1, 1, 10),
(20, 'OS', 'Windows 11 Pro', 99.00, 0, 1, 20),
(20, 'Cooling', 'Standard Creator Cooler', 0.00, 1, 1, 10),
(20, 'Cooling', 'Quiet-Optimized Air', 85.00, 0, 1, 20),
(20, 'Cooling', '360 mm AIO Liquid', 190.00, 0, 1, 30);

-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

-- Expect 0 rows (every active product has ≥ 2 active options):
-- SELECT p.id, p.name, COUNT(po.id) AS option_count
-- FROM products p
-- LEFT JOIN product_options po
--   ON po.product_id = p.id AND po.is_active = 1
-- WHERE p.is_active = 1
-- GROUP BY p.id, p.name
-- HAVING COUNT(po.id) < 2;

-- Option counts per product (expect ≥ 2 each, typically 12–18):
-- SELECT p.id, p.name, COUNT(po.id) AS option_count
-- FROM products p
-- INNER JOIN product_options po ON po.product_id = p.id AND po.is_active = 1
-- WHERE p.is_active = 1
-- GROUP BY p.id, p.name
-- ORDER BY p.id;

-- Option groups per product:
-- SELECT p.id, p.name, po.option_group, COUNT(*) AS choices
-- FROM products p
-- INNER JOIN product_options po ON po.product_id = p.id AND po.is_active = 1
-- WHERE p.is_active = 1
-- GROUP BY p.id, p.name, po.option_group
-- ORDER BY p.id, po.option_group;

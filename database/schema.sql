-- =============================================================================
-- CustomCore — Complete MySQL Database Schema
-- =============================================================================
--
-- File responsibility:
--   Creates all tables required by the CustomCore application. Implements the
--   design documented in docs/database-design.md (Commit 0.6).
--
-- Engine:   InnoDB (transactional, foreign-key support)
-- Charset:  utf8mb4 (full Unicode including emoji)
-- Collation: utf8mb4_unicode_ci
--
-- Import:
--   mysql -u your_username -p your_database_name < database/schema.sql
--
-- Security:
--   - passwords column stores bcrypt hashes only (password_hash / password_verify)
--   - orders.payment_method stores a label only — never card numbers
--   - No real customer data should exist in seed or export files committed to Git
--
-- Tables (21):
--   1.  users
--   2.  categories
--   3.  products
--   4.  product_options
--   5.  component_categories
--   6.  components
--   7.  compatibility_rules
--   8.  saved_builds
--   9.  saved_build_items
--   10. wishlists
--   11. wishlist_items
--   12. carts
--   13. cart_items
--   14. orders
--   15. order_items
--   16. reviews
--   17. consultation_requests
--   18. consultation_attachments
--   19. contact_messages
--   20. themes
--   21. site_settings
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. USERS — Authentication, profiles, and role-based access
-- =============================================================================
-- Roles: customer (default) or admin.
-- is_active: admin can disable accounts; disabled users cannot log in.

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL COMMENT 'bcrypt via password_hash()',
    `first_name`    VARCHAR(100)    NOT NULL DEFAULT '',
    `last_name`     VARCHAR(100)    NOT NULL DEFAULT '',
    `phone`         VARCHAR(30)     DEFAULT NULL,
    `address_line1` VARCHAR(255)    NOT NULL DEFAULT '',
    `address_line2` VARCHAR(255)    NOT NULL DEFAULT '',
    `city`          VARCHAR(100)    NOT NULL DEFAULT '',
    `province`      VARCHAR(100)    NOT NULL DEFAULT '',
    `postal_code`   VARCHAR(20)     NOT NULL DEFAULT '',
    `role`          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 2. CATEGORIES — Product tier groupings
-- =============================================================================
-- Four tiers: Budget, Esports, High-Performance, Creator.

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)  NOT NULL,
    `slug`        VARCHAR(100)  NOT NULL,
    `description` TEXT          NOT NULL,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 3. PRODUCTS — Prebuilt gaming PC catalogue (≥ 20 records)
-- =============================================================================
-- Each product belongs to one category. base_price is before options.
-- spec_* columns provide quick-compare data without querying option tables.

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED    NOT NULL,
    `name`              VARCHAR(200)    NOT NULL,
    `slug`              VARCHAR(200)    NOT NULL,
    `brand`             VARCHAR(100)    NOT NULL DEFAULT 'CustomCore',
    `short_description` VARCHAR(500)    NOT NULL DEFAULT '',
    `description`       TEXT            NOT NULL,
    `base_price`        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `stock_quantity`    INT             NOT NULL DEFAULT 0,
    `image_path`        VARCHAR(500)    NOT NULL DEFAULT '',
    `spec_cpu`          VARCHAR(150)    NOT NULL DEFAULT '' COMMENT 'CPU label for compare cards',
    `spec_gpu`          VARCHAR(150)    NOT NULL DEFAULT '' COMMENT 'GPU label for compare cards',
    `spec_ram`          VARCHAR(100)    NOT NULL DEFAULT '' COMMENT 'Default RAM for compare cards',
    `spec_storage`      VARCHAR(100)    NOT NULL DEFAULT '' COMMENT 'Default storage for compare cards',
    `is_featured`       TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_active` (`is_active`),
    INDEX `idx_products_price` (`base_price`),
    INDEX `idx_products_brand` (`brand`),
    INDEX `idx_products_featured` (`is_featured`),
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 4. PRODUCT_OPTIONS — Configurable choices per product (≥ 2 per product)
-- =============================================================================
-- Groups: RAM, Storage, Colour, Warranty, OS, Cooling, GPU upgrade, etc.
-- price_delta is added to or subtracted from the product base_price.

DROP TABLE IF EXISTS `product_options`;
CREATE TABLE `product_options` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED    NOT NULL,
    `option_group` VARCHAR(50)     NOT NULL COMMENT 'e.g. RAM, Storage, Colour, Warranty',
    `option_label` VARCHAR(150)    NOT NULL COMMENT 'e.g. 32 GB, 2 TB SSD, White',
    `price_delta`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Added to base_price',
    `is_default`   TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order`   INT             NOT NULL DEFAULT 0,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_po_product` (`product_id`),
    INDEX `idx_po_group` (`option_group`),
    CONSTRAINT `fk_po_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 5. COMPONENT_CATEGORIES — Builder step categories
-- =============================================================================
-- CPU, Motherboard, GPU, RAM, Storage, PSU, Case, Cooling, OS, Service.

DROP TABLE IF EXISTS `component_categories`;
CREATE TABLE `component_categories` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)  NOT NULL,
    `slug`        VARCHAR(100)  NOT NULL,
    `sort_order`  INT           NOT NULL DEFAULT 0 COMMENT 'Builder step order',
    `is_required` TINYINT(1)    NOT NULL DEFAULT 1 COMMENT 'Builder requires a selection',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cc_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 6. COMPONENTS — Individual parts for the custom builder
-- =============================================================================
-- Attribute columns power the simplified compatibility checker (Stage 5).
-- Not every column applies to every category; NULL means not applicable.

DROP TABLE IF EXISTS `components`;
CREATE TABLE `components` (
    `id`                      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `component_category_id`   INT UNSIGNED    NOT NULL,
    `name`                    VARCHAR(200)    NOT NULL,
    `brand`                   VARCHAR(100)    NOT NULL DEFAULT '',
    `price`                   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `wattage_estimate`        INT             DEFAULT NULL COMMENT 'TDP/draw estimate for PSU checks',
    `socket`                  VARCHAR(50)     DEFAULT NULL COMMENT 'CPU/motherboard socket (e.g. AM5, LGA1700)',
    `ram_type`                VARCHAR(20)     DEFAULT NULL COMMENT 'DDR4 or DDR5',
    `form_factor`             VARCHAR(30)     DEFAULT NULL COMMENT 'ATX, mATX, ITX (motherboard/case)',
    `gpu_length_mm`           INT             DEFAULT NULL COMMENT 'GPU card length in mm',
    `max_gpu_length_mm`       INT             DEFAULT NULL COMMENT 'Case max GPU clearance in mm',
    `cooler_height_mm`        INT             DEFAULT NULL COMMENT 'Cooler height in mm',
    `max_cooler_height_mm`    INT             DEFAULT NULL COMMENT 'Case max cooler clearance in mm',
    `cooler_type`             VARCHAR(20)     DEFAULT NULL COMMENT 'air or liquid',
    `storage_interface`       VARCHAR(30)     DEFAULT NULL COMMENT 'NVMe, SATA, etc.',
    `supported_storage`       VARCHAR(100)    DEFAULT NULL COMMENT 'Motherboard supported interfaces (CSV)',
    `psu_wattage`             INT             DEFAULT NULL COMMENT 'PSU rated wattage',
    `performance_gaming`      TINYINT UNSIGNED DEFAULT NULL COMMENT '1-100 gaming score for charts',
    `performance_productivity` TINYINT UNSIGNED DEFAULT NULL COMMENT '1-100 productivity score',
    `image_path`              VARCHAR(500)    NOT NULL DEFAULT '',
    `is_active`               TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_comp_category` (`component_category_id`),
    INDEX `idx_comp_active` (`is_active`),
    CONSTRAINT `fk_comp_category`
        FOREIGN KEY (`component_category_id`) REFERENCES `component_categories` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 7. COMPATIBILITY_RULES — Simplified rule metadata
-- =============================================================================
-- Each row describes one compatibility check the application performs.
-- Application logic (Stage 5) compares component attribute columns using these
-- rules as guides; this table is not a full commercial parts graph.

DROP TABLE IF EXISTS `compatibility_rules`;
CREATE TABLE `compatibility_rules` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `rule_code`   VARCHAR(50)   NOT NULL COMMENT 'Unique programmatic key',
    `name`        VARCHAR(150)  NOT NULL COMMENT 'Human-readable rule name',
    `description` TEXT          NOT NULL COMMENT 'Explanation template for warnings/errors',
    `severity`    ENUM('error','warning') NOT NULL DEFAULT 'error' COMMENT 'incompatible vs. warning',
    `config`      JSON          DEFAULT NULL COMMENT 'Optional thresholds or allowed pairs',
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cr_code` (`rule_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 8. SAVED_BUILDS — Customer saved custom PC configurations
-- =============================================================================

DROP TABLE IF EXISTS `saved_builds`;
CREATE TABLE `saved_builds` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`               INT UNSIGNED    NOT NULL,
    `name`                  VARCHAR(200)    NOT NULL DEFAULT 'My Build',
    `total_price`           DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Server-calculated total',
    `compatibility_status`  ENUM('compatible','warning','incompatible') NOT NULL DEFAULT 'compatible',
    `notes`                 TEXT            DEFAULT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sb_user` (`user_id`),
    CONSTRAINT `fk_sb_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 9. SAVED_BUILD_ITEMS — Component lines within a saved build
-- =============================================================================
-- One component per component_category per build is the intended usage.

DROP TABLE IF EXISTS `saved_build_items`;
CREATE TABLE `saved_build_items` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `saved_build_id`  INT UNSIGNED    NOT NULL,
    `component_id`    INT UNSIGNED    NOT NULL,
    `unit_price`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Price snapshot at save time',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sbi_build_component` (`saved_build_id`, `component_id`),
    INDEX `idx_sbi_component` (`component_id`),
    CONSTRAINT `fk_sbi_build`
        FOREIGN KEY (`saved_build_id`) REFERENCES `saved_builds` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_sbi_component`
        FOREIGN KEY (`component_id`) REFERENCES `components` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 10. WISHLISTS — One wishlist per customer
-- =============================================================================

DROP TABLE IF EXISTS `wishlists`;
CREATE TABLE `wishlists` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wishlists_user` (`user_id`),
    CONSTRAINT `fk_wishlists_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 11. WISHLIST_ITEMS — Products on a customer's wishlist
-- =============================================================================

DROP TABLE IF EXISTS `wishlist_items`;
CREATE TABLE `wishlist_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wishlist_id` INT UNSIGNED NOT NULL,
    `product_id`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wi_wishlist_product` (`wishlist_id`, `product_id`),
    INDEX `idx_wi_product` (`product_id`),
    CONSTRAINT `fk_wi_wishlist`
        FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_wi_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 12. CARTS — One persistent cart per customer account
-- =============================================================================

DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_carts_user` (`user_id`),
    CONSTRAINT `fk_carts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 13. CART_ITEMS — Lines in the shopping cart
-- =============================================================================
-- item_type distinguishes prebuilt products from custom builds.
-- Exactly one of product_id or saved_build_id should be set per row.

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `cart_id`         INT UNSIGNED    NOT NULL,
    `item_type`       ENUM('product','saved_build') NOT NULL DEFAULT 'product',
    `product_id`      INT UNSIGNED    DEFAULT NULL,
    `saved_build_id`  INT UNSIGNED    DEFAULT NULL,
    `quantity`        INT UNSIGNED    NOT NULL DEFAULT 1,
    `unit_price`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Server-trusted price',
    `options_json`    TEXT            DEFAULT NULL COMMENT 'Selected option IDs/labels snapshot',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ci_cart` (`cart_id`),
    INDEX `idx_ci_product` (`product_id`),
    INDEX `idx_ci_build` (`saved_build_id`),
    CONSTRAINT `fk_ci_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_ci_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_ci_build`
        FOREIGN KEY (`saved_build_id`) REFERENCES `saved_builds` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 14. ORDERS — Simulated checkout (no real payment credentials stored)
-- =============================================================================
-- payment_method stores a label like 'pay_on_pickup' or 'simulated_credit'.
-- Shipping snapshot columns freeze the address at order time.

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED    NOT NULL,
    `order_number`    VARCHAR(30)     NOT NULL COMMENT 'Public confirmation code',
    `status`          ENUM('pending','processing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    `subtotal`        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `shipping_name`   VARCHAR(200)    NOT NULL DEFAULT '',
    `shipping_phone`  VARCHAR(30)     NOT NULL DEFAULT '',
    `shipping_addr1`  VARCHAR(255)    NOT NULL DEFAULT '',
    `shipping_addr2`  VARCHAR(255)    NOT NULL DEFAULT '',
    `shipping_city`   VARCHAR(100)    NOT NULL DEFAULT '',
    `shipping_prov`   VARCHAR(100)    NOT NULL DEFAULT '',
    `shipping_postal` VARCHAR(20)     NOT NULL DEFAULT '',
    `payment_method`  VARCHAR(50)     NOT NULL DEFAULT 'pay_on_pickup' COMMENT 'Label only — never card numbers',
    `admin_notes`     TEXT            DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_number` (`order_number`),
    INDEX `idx_orders_user` (`user_id`),
    INDEX `idx_orders_status` (`status`),
    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 15. ORDER_ITEMS — Frozen line items within an order
-- =============================================================================
-- All display values are snapshots so the order history remains accurate even
-- if the original product or build is later edited or removed.

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `order_id`            INT UNSIGNED    NOT NULL,
    `item_type`           ENUM('product','saved_build') NOT NULL DEFAULT 'product',
    `product_id`          INT UNSIGNED    DEFAULT NULL COMMENT 'Reference if still available',
    `saved_build_id`      INT UNSIGNED    DEFAULT NULL COMMENT 'Reference if still available',
    `item_name`           VARCHAR(250)    NOT NULL COMMENT 'Frozen display name',
    `quantity`            INT UNSIGNED    NOT NULL DEFAULT 1,
    `unit_price`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `line_total`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `options_json`        TEXT            DEFAULT NULL COMMENT 'Frozen product-option detail',
    `build_snapshot_json` TEXT            DEFAULT NULL COMMENT 'Frozen custom-build component list',
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_oi_order` (`order_id`),
    INDEX `idx_oi_product` (`product_id`),
    CONSTRAINT `fk_oi_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_oi_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_oi_build`
        FOREIGN KEY (`saved_build_id`) REFERENCES `saved_builds` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 16. REVIEWS — Product ratings with moderation workflow
-- =============================================================================
-- Public pages show approved reviews only.

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED  NOT NULL,
    `user_id`     INT UNSIGNED  NOT NULL,
    `rating`      TINYINT UNSIGNED NOT NULL COMMENT '1–5 stars',
    `title`       VARCHAR(200)  NOT NULL DEFAULT '',
    `body`        TEXT          NOT NULL,
    `status`      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_reviews_product` (`product_id`),
    INDEX `idx_reviews_user` (`user_id`),
    INDEX `idx_reviews_status` (`status`),
    CONSTRAINT `fk_reviews_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 17. CONSULTATION_REQUESTS — PC advice requests from customers
-- =============================================================================

DROP TABLE IF EXISTS `consultation_requests`;
CREATE TABLE `consultation_requests` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED    NOT NULL,
    `budget`            VARCHAR(100)    NOT NULL DEFAULT '',
    `games`             TEXT            NOT NULL COMMENT 'Games the customer plays',
    `software`          TEXT            NOT NULL COMMENT 'Other software requirements',
    `performance_goals` TEXT            NOT NULL COMMENT 'Desired performance expectations',
    `notes`             TEXT            DEFAULT NULL,
    `status`            ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open',
    `admin_response`    TEXT            DEFAULT NULL,
    `responded_at`      DATETIME        DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cr_user` (`user_id`),
    INDEX `idx_cr_status` (`status`),
    CONSTRAINT `fk_cr_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 18. CONSULTATION_ATTACHMENTS — Safe file uploads for consultations
-- =============================================================================
-- stored_filename uses a generated safe name; original_filename is display only.

DROP TABLE IF EXISTS `consultation_attachments`;
CREATE TABLE `consultation_attachments` (
    `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `consultation_request_id`  INT UNSIGNED  NOT NULL,
    `original_filename`        VARCHAR(255)  NOT NULL COMMENT 'User-visible name only',
    `stored_filename`          VARCHAR(255)  NOT NULL COMMENT 'Generated safe name on disk',
    `mime_type`                VARCHAR(100)  NOT NULL DEFAULT '',
    `file_size`                INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Size in bytes',
    `created_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ca_request` (`consultation_request_id`),
    CONSTRAINT `fk_ca_request`
        FOREIGN KEY (`consultation_request_id`) REFERENCES `consultation_requests` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 19. CONTACT_MESSAGES — General contact / support form
-- =============================================================================
-- user_id is optional (guest visitors can submit too).

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  DEFAULT NULL,
    `name`       VARCHAR(200)  NOT NULL,
    `email`      VARCHAR(255)  NOT NULL,
    `subject`    VARCHAR(300)  NOT NULL DEFAULT '',
    `message`    TEXT          NOT NULL,
    `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cm_user` (`user_id`),
    INDEX `idx_cm_read` (`is_read`),
    CONSTRAINT `fk_cm_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 20. THEMES — Three switchable site templates
-- =============================================================================
-- css_file stores the path relative to the project root.

DROP TABLE IF EXISTS `themes`;
CREATE TABLE `themes` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(100)  NOT NULL,
    `slug`              VARCHAR(100)  NOT NULL,
    `css_file`          VARCHAR(300)  NOT NULL COMMENT 'e.g. assets/themes/rgb-gaming.css',
    `is_active_default` TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Fallback if setting is missing',
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_themes_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- 21. SITE_SETTINGS — Key-value application settings
-- =============================================================================
-- Primary use: active_theme_id. Extensible for future settings.

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100)  NOT NULL,
    `setting_value` VARCHAR(500)  NOT NULL DEFAULT '',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ss_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VERIFICATION QUERIES (run after import to confirm structure)
-- =============================================================================

-- Count tables — should return 21
-- SELECT COUNT(*) AS table_count
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';

-- List all tables
-- SHOW TABLES;

-- Confirm foreign keys exist
-- SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
-- ORDER BY TABLE_NAME;

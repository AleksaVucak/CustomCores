-- =============================================================================
-- CustomCore — Themes and Site Settings Seed (Commit 2.6)
-- =============================================================================
--
-- File responsibility:
--   Seeds the three switchable site themes and the default site_settings row
--   that selects the active theme. CSS theme files themselves are created in
--   Stage 10; this commit only stores the database records the theme switcher
--   and shared header will read later.
--
-- Prerequisites:
--   1. Import `database/schema.sql`
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-themes.sql
--
-- Acceptance (Commit 2.6):
--   - Exactly 3 theme rows exist
--   - One theme is marked is_active_default = 1 (fallback)
--   - site_settings.active_theme_id points at a valid themes.id
--   - Active theme is readable with a simple JOIN query
--
-- Theme filenames match the rubric checklist (#3a):
--   assets/themes/rgb-gaming.css
--   assets/themes/minimal-pro.css
--   assets/themes/cyber-grid.css
--
-- Default theme slug matches config/app.php → default_theme = 'rgb-gaming'
-- =============================================================================

SET NAMES utf8mb4;

-- Safe re-import during development
DELETE FROM `site_settings`;
DELETE FROM `themes`;

ALTER TABLE `site_settings` AUTO_INCREMENT = 1;
ALTER TABLE `themes` AUTO_INCREMENT = 1;

-- =============================================================================
-- THEMES — Three distinct site-wide CSS templates
-- =============================================================================
-- is_active_default marks the fallback if site_settings is missing/corrupt.
-- Only one row should have is_active_default = 1.

INSERT INTO `themes`
    (`id`, `name`, `slug`, `css_file`, `is_active_default`)
VALUES
(
    1,
    'RGB Gaming',
    'rgb-gaming',
    'assets/themes/rgb-gaming.css',
    1
),
(
    2,
    'Minimal Professional',
    'minimal-pro',
    'assets/themes/minimal-pro.css',
    0
),
(
    3,
    'Cyber Grid',
    'cyber-grid',
    'assets/themes/cyber-grid.css',
    0
);

-- =============================================================================
-- SITE_SETTINGS — Active theme and extensible key-value settings
-- =============================================================================
-- active_theme_id stores the themes.id of the currently selected template.
-- Administrators change this value from admin/themes.php in Stage 10.

INSERT INTO `site_settings`
    (`id`, `setting_key`, `setting_value`)
VALUES
(
    1,
    'active_theme_id',
    '1'
),
(
    2,
    'site_tagline',
    'Custom gaming PC store and guided PC builder'
),
(
    3,
    'maintenance_mode',
    '0'
);

-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

-- Expect 3 themes:
-- SELECT COUNT(*) AS theme_count FROM themes;

-- Expect exactly one default fallback theme:
-- SELECT COUNT(*) AS default_count FROM themes WHERE is_active_default = 1;

-- Active theme readable from MySQL (JOIN — expect one row: RGB Gaming):
-- SELECT t.id, t.name, t.slug, t.css_file
-- FROM site_settings s
-- INNER JOIN themes t ON t.id = CAST(s.setting_value AS UNSIGNED)
-- WHERE s.setting_key = 'active_theme_id';

-- All settings:
-- SELECT setting_key, setting_value FROM site_settings ORDER BY id;

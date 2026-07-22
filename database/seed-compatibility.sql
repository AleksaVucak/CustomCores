-- =============================================================================
-- CustomCore — Compatibility Rules Seed (Commit 2.5)
-- =============================================================================
--
-- File responsibility:
--   Seeds the seven simplified compatibility rules that the PC builder checker
--   uses in Stage 5. Each row describes one check, its severity, and optional
--   configuration thresholds. Application logic (PHP + JS) reads these rows,
--   then compares component attribute columns to produce compatible / warning /
--   incompatible results.
--
-- Prerequisites:
--   1. Import `database/schema.sql`
--   2. Import `database/seed-components.sql` (attribute columns must exist)
--
-- Import:
--   mysql -u your_username -p your_database_name < database/seed-compatibility.sql
--
-- Acceptance (Commit 2.5):
--   - 7 active rules matching docs/database-design.md section 6
--   - Compatible + incompatible cases are queryable from seeded components
--
-- Rule reference (docs/database-design.md §6):
--   1. socket_match       — CPU socket = motherboard socket
--   2. ram_type_match      — RAM type = motherboard RAM type
--   3. case_motherboard    — Motherboard form factor fits case
--   4. psu_wattage         — PSU wattage ≥ estimated build draw
--   5. gpu_clearance       — Case max GPU length ≥ GPU length
--   6. cooler_fit          — Case cooler clearance + type support
--   7. storage_interface   — Motherboard supports chosen storage interface
-- =============================================================================

SET NAMES utf8mb4;

-- Safe re-import
DELETE FROM `compatibility_rules`;
ALTER TABLE `compatibility_rules` AUTO_INCREMENT = 1;

INSERT INTO `compatibility_rules`
    (`id`, `rule_code`, `name`, `description`, `severity`, `config`, `is_active`)
VALUES

-- -----------------------------------------------------------------------------
-- Rule 1: CPU socket must match motherboard socket
-- -----------------------------------------------------------------------------
-- Compares: components.socket (CPU, cat 1) vs components.socket (Motherboard, cat 2)
-- Example pass:  Ryzen 7 7800X3D (AM5) + B650 TOMAHAWK (AM5)
-- Example fail:  Ryzen 5 5600 (AM4) + Z790-P WIFI (LGA1700)
(
    1,
    'socket_match',
    'CPU / Motherboard Socket',
    'The CPU socket must match the motherboard socket. An AM5 processor cannot be installed in an LGA1700 or AM4 board, and vice versa.',
    'error',
    JSON_OBJECT(
        'compare', 'equal',
        'source_category', 'cpu',
        'source_column', 'socket',
        'target_category', 'motherboard',
        'target_column', 'socket'
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 2: RAM type must match motherboard RAM type
-- -----------------------------------------------------------------------------
-- Compares: components.ram_type (RAM, cat 4) vs components.ram_type (Motherboard, cat 2)
-- Example pass:  DDR5 RAM + DDR5 motherboard
-- Example fail:  DDR4 RAM + DDR5 motherboard
(
    2,
    'ram_type_match',
    'RAM / Motherboard Memory Type',
    'The RAM generation must match the motherboard memory type. DDR5 memory will not fit in a DDR4 slot, and DDR4 will not fit in a DDR5 slot.',
    'error',
    JSON_OBJECT(
        'compare', 'equal',
        'source_category', 'ram',
        'source_column', 'ram_type',
        'target_category', 'motherboard',
        'target_column', 'ram_type'
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 3: Motherboard form factor must fit inside the case
-- -----------------------------------------------------------------------------
-- Compares: components.form_factor (Motherboard, cat 2) fits within
--           components.form_factor (Case, cat 7)
-- Hierarchy: ATX case accepts ATX/mATX/ITX; mATX case accepts mATX/ITX;
--            ITX case accepts ITX only.
-- Example pass:  ITX motherboard + ATX case
-- Example fail:  ATX motherboard + ITX case (Cooler Master NR200P)
(
    3,
    'case_motherboard',
    'Case / Motherboard Form Factor',
    'The motherboard must physically fit the case. An ATX board cannot be installed in an ITX case. ATX cases accept ATX, mATX, and ITX boards; mATX cases accept mATX and ITX; ITX cases accept only ITX boards.',
    'error',
    JSON_OBJECT(
        'compare', 'form_factor_fits',
        'source_category', 'motherboard',
        'source_column', 'form_factor',
        'target_category', 'case',
        'target_column', 'form_factor',
        'hierarchy', JSON_ARRAY('ITX', 'mATX', 'ATX')
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 4: PSU wattage must be sufficient for the build
-- -----------------------------------------------------------------------------
-- Compares: components.psu_wattage (PSU, cat 6) vs SUM of
--           components.wattage_estimate across all selected parts.
-- A 20% headroom margin is recommended; below headroom is a warning, below
-- total draw is an error.
-- Example pass:  850 W PSU with 600 W total draw
-- Example fail:  450 W PSU with 700 W total draw
(
    4,
    'psu_wattage',
    'PSU Wattage Capacity',
    'The power supply must provide enough wattage for all components. We recommend at least 20%% headroom above the estimated total draw. A PSU below the minimum is incompatible; one with less than 20%% headroom triggers a warning.',
    'warning',
    JSON_OBJECT(
        'compare', 'psu_sufficient',
        'source_category', 'psu',
        'source_column', 'psu_wattage',
        'headroom_percent', 20,
        'severity_below_total', 'error',
        'severity_below_headroom', 'warning'
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 5: Case must have enough GPU clearance
-- -----------------------------------------------------------------------------
-- Compares: components.max_gpu_length_mm (Case, cat 7) ≥
--           components.gpu_length_mm (GPU, cat 3)
-- Example pass:  Lian Li Lancool 216 (392 mm) + RTX 4090 (336 mm)
-- Example fail:  SilverStone SG13 (266 mm) + RTX 4090 (336 mm)
(
    5,
    'gpu_clearance',
    'Case GPU Length Clearance',
    'The GPU card must fit inside the case. The case maximum GPU length must be greater than or equal to the GPU card length. Long cards like the RTX 4090 (336 mm) will not fit in compact ITX cases with limited clearance.',
    'error',
    JSON_OBJECT(
        'compare', 'gte',
        'source_category', 'case',
        'source_column', 'max_gpu_length_mm',
        'target_category', 'gpu',
        'target_column', 'gpu_length_mm'
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 6: Case must support the cooler type and height
-- -----------------------------------------------------------------------------
-- Two sub-checks:
--   a) Air coolers: Case.max_cooler_height_mm ≥ Cooler.cooler_height_mm
--   b) Liquid coolers: Case.cooler_type must include 'liquid'
-- Example pass:  Noctua NH-D15 (165 mm air) in Lian Li Lancool 216 (180 mm, air+liquid)
-- Example fail:  Noctua NH-D15 (165 mm air) in Node 202 (56 mm, air only)
-- Example fail:  Liquid AIO in Fractal Node 202 (air only, no liquid support)
(
    6,
    'cooler_fit',
    'Case / Cooler Compatibility',
    'The CPU cooler must fit inside the case. Air coolers must not exceed the case maximum cooler height. Liquid (AIO) coolers require a case that supports liquid cooling radiator mounts. Compact cases may only support low-profile air coolers.',
    'error',
    JSON_OBJECT(
        'compare', 'cooler_fits',
        'source_category', 'cooling',
        'target_category', 'case',
        'height_column_cooler', 'cooler_height_mm',
        'height_column_case', 'max_cooler_height_mm',
        'type_column_cooler', 'cooler_type',
        'type_column_case', 'cooler_type'
    ),
    1
),

-- -----------------------------------------------------------------------------
-- Rule 7: Motherboard must support the storage interface
-- -----------------------------------------------------------------------------
-- Compares: components.storage_interface (Storage, cat 5) is contained in
--           components.supported_storage CSV (Motherboard, cat 2)
-- Example pass:  NVMe SSD + any motherboard (all support NVMe)
-- Example fail:  SATA SSD/HDD + ASRock B650I (supported_storage = 'NVMe' only)
(
    7,
    'storage_interface',
    'Motherboard / Storage Interface',
    'The motherboard must support the chosen storage interface. Some compact ITX motherboards only have NVMe M.2 slots and do not support SATA drives. Check the motherboard supported storage list before selecting a SATA SSD or HDD.',
    'warning',
    JSON_OBJECT(
        'compare', 'csv_contains',
        'source_category', 'motherboard',
        'source_column', 'supported_storage',
        'target_category', 'storage',
        'target_column', 'storage_interface'
    ),
    1
);

-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

-- Expect 7 active rules:
-- SELECT COUNT(*) AS rule_count FROM compatibility_rules WHERE is_active = 1;

-- List all rules with severity:
-- SELECT id, rule_code, name, severity FROM compatibility_rules ORDER BY id;

-- Demo: find incompatible CPU-motherboard socket pairs:
-- SELECT cpu.name AS cpu_name, cpu.socket AS cpu_socket,
--        mb.name AS mb_name, mb.socket AS mb_socket,
--        CASE WHEN cpu.socket = mb.socket THEN 'compatible' ELSE 'incompatible' END AS result
-- FROM components cpu
-- CROSS JOIN components mb
-- WHERE cpu.component_category_id = 1
--   AND mb.component_category_id = 2
--   AND cpu.socket <> mb.socket
-- LIMIT 10;

-- Demo: find GPU + case clearance failures:
-- SELECT gpu.name AS gpu_name, gpu.gpu_length_mm,
--        cs.name AS case_name, cs.max_gpu_length_mm,
--        CASE WHEN cs.max_gpu_length_mm >= gpu.gpu_length_mm
--             THEN 'fits' ELSE 'too long' END AS result
-- FROM components gpu
-- CROSS JOIN components cs
-- WHERE gpu.component_category_id = 3
--   AND cs.component_category_id = 7
--   AND cs.max_gpu_length_mm < gpu.gpu_length_mm;

-- Demo: find SATA storage vs NVMe-only motherboard:
-- SELECT st.name AS storage_name, st.storage_interface,
--        mb.name AS mb_name, mb.supported_storage,
--        CASE WHEN FIND_IN_SET(st.storage_interface, mb.supported_storage)
--             THEN 'supported' ELSE 'unsupported' END AS result
-- FROM components st
-- CROSS JOIN components mb
-- WHERE st.component_category_id = 5
--   AND mb.component_category_id = 2
--   AND NOT FIND_IN_SET(st.storage_interface, mb.supported_storage);

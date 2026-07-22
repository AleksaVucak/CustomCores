<?php
/**
 * CustomCore — Application configuration (non-secret).
 *
 * File responsibility:
 *   Central place for site-wide settings that are safe to store in Git.
 *   Database credentials do NOT belong here — use config/database.php instead.
 *
 * Usage:
 *   $app = require __DIR__ . '/app.php';
 */

declare(strict_types=1);

return [
    // Public site name shown in titles and branding
    'name' => 'CustomCore',

    // Short tagline for metadata and marketing copy
    'tagline' => 'Custom gaming PC store and guided PC builder',

    /**
     * Environment label.
     * Use "local" while developing and "production" on the university host.
     */
    'environment' => 'local',

    /**
     * Debug mode.
     * When true, development-only messages may be shown.
     * Must be false on the live university server so credentials and stack
     * traces are never exposed to visitors.
     */
    'debug' => false,

    /**
     * Base URL of the site with no trailing slash, if needed for absolute links.
     * Example production value: https://myweb.cs.uwindsor.ca/~yourid/customcore
     * Leave empty to use relative URLs (preferred for simple shared hosting).
     */
    'base_url' => '',

    // Default timezone for PHP date/time functions
    'timezone' => 'America/Toronto',

    // Custom session cookie name (helps avoid collisions on shared hosts)
    'session_name' => 'CUSTOMCORESESSID',

    // Default theme slug used if the database setting is missing (Stage 10)
    'default_theme' => 'rgb-gaming',

    // Upload limits referenced by validation (Stage 7+) — size in bytes
    'upload_max_bytes' => 2 * 1024 * 1024,

    // Allowed consultation attachment extensions (Stage 7+)
    'upload_allowed_extensions' => ['pdf', 'txt', 'png', 'jpg', 'jpeg', 'webp'],

    // Relative paths from the project root
    'paths' => [
        'uploads_consultation' => 'uploads/consultation',
        'uploads_products' => 'uploads/products',
        'themes' => 'assets/themes',
        'images' => 'assets/images',
        'media' => 'assets/media',
    ],
];

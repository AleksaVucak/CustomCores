<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Application configuration (non-secret; safe to commit).
// Central place for site-wide settings that are NOT credentials.
// Database username and password live only in config/database.php (gitignored).
//
// Usage:
//   $app = require __DIR__ . '/app.php';
//
// Local development: keep environment = 'local'.
// Production (university host): set environment = 'production' and debug = false
// (see config/app.production.example.php for a full production copy to compare).

declare(strict_types=1);

return [
    // Public site name shown in titles and branding
    'name' => 'CustomCore',

    // Short tagline for metadata and marketing copy
    'tagline' => 'Custom gaming PC store and guided PC builder',

    /**
     * Environment label shown in admin tooling awareness only.
     * Allowed values in practice: 'local' | 'production'
     * Use 'production' on myweb.cs.uwindsor.ca after you upload.
     */
    'environment' => 'local',

    /**
     * Debug mode.
     * When true, some helpers may show more detail to help development.
     * MUST be false on any public host so stack traces and database clues
     * never reach visitors. Default below is already production-safe.
     */
    'debug' => false,

    /**
     * Base URL of the site with no trailing slash.
     * Leave empty to use depth-safe relative URLs (recommended on shared hosting).
     * Set only when absolute canonical / sitemap URLs are required, for example:
     *   https://myweb.cs.uwindsor.ca/~yourUWinID/customcore
     * After changing it on a live host, regenerate the static sitemap snapshot:
     *   php sitemap.php --write
     */
    'base_url' => '',

    // Default timezone for PHP date/time functions
    'timezone' => 'America/Toronto',

    // Custom session cookie name (avoids collisions on shared multi-app hosts)
    'session_name' => 'CUSTOMCORESESSID',

    /**
     * Session security timeouts, in seconds. Set a value to 0 to disable that limit.
     * idle: log out after this long with no request activity
     * absolute: hard cap on total session life even with activity
     * regenerate: rotate the session id this often (limits stolen-cookie replay)
     */
    'session_idle_timeout' => 1800,          // 30 minutes
    'session_absolute_timeout' => 43200,     // 12 hours
    'session_regenerate_interval' => 900,    // 15 minutes

    // Default theme slug if the database setting is missing (must match a CSS file in assets/themes/)
    'default_theme' => 'rgb-gaming',

    // Maximum upload size in bytes for product images and consultation attachments
    'upload_max_bytes' => 2 * 1024 * 1024,

    // Allowed consultation attachment extensions (product images use a separate image allow-list)
    'upload_allowed_extensions' => ['pdf', 'txt', 'png', 'jpg', 'jpeg', 'webp'],

    /**
     * Project-relative paths (no leading slash). Change only if you deliberately
     * relocate storage on disk. Paths stay under the project tree so shared hosts
     * without special mount points still work. Never put absolute system paths here.
     */
    'paths' => [
        'uploads_consultation' => 'uploads/consultation',
        'uploads_products' => 'uploads/products',
        'themes' => 'assets/themes',
        'images' => 'assets/images',
        'media' => 'assets/media',
    ],

    /**
     * Store / service location shown on store-locations.php.
     *
     * IMPORTANT: This is a FICTIONAL location created for the academic project.
     * The address, phone, and email do not represent a real business. Update the
     * fields below to change the interactive map and the always-visible text
     * fallback without editing page logic.
     */
    'store_location' => [
        'name' => 'CustomCore Campus Service Desk',
        'street' => '1000 Innovation Drive',
        'city' => 'Windsor',
        'region' => 'Ontario',
        'postal_code' => 'N9C 4E6',
        'country' => 'Canada',
        'phone_display' => '519-555-0148',
        'phone_href' => '+15195550148',
        'email' => 'support@customcore.example',
        'latitude' => 42.3049,
        'longitude' => -83.0662,
        'map_zoom' => 14,
        'image' => 'assets/images/map/storefront-exterior.jpg',
        'image_alt' => 'Modest computer service storefront with charcoal, white, and teal details.',
        'hours' => [
            'Monday to Friday' => '10:00 a.m. to 7:00 p.m.',
            'Saturday' => '11:00 a.m. to 5:00 p.m.',
            'Sunday' => 'Closed',
        ],
    ],
];

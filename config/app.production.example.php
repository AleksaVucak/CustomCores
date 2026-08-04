<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Production application configuration TEMPLATE (safe to commit).
// Non-secret settings only. This file is NOT loaded by the web app automatically.
//
// How to use on the university host:
//   Option A (recommended): edit config/app.php on the server and set the flags
//   below (especially environment and debug). Keep this file as a reference.
//
//   Option B: copy this file over config/app.php on the host if you have no
//   local customizations you need to preserve:
//     cp config/app.production.example.php config/app.php
//
// Never put database passwords in this file. Secrets belong only in
// config/database.php (copied from database.example.php, gitignored).

declare(strict_types=1);

return [
    'name' => 'CustomCore',

    'tagline' => 'Custom gaming PC store and guided PC builder',

    // Production marker for the live university host
    'environment' => 'production',

    // Always false in production (no stack traces or SQL clues for visitors)
    'debug' => false,

    /**
     * Prefer empty base_url so relative links work in a ~user/customcore subfolder.
     * Uncomment and edit only if absolute SEO URLs are required:
     * 'base_url' => 'https://myweb.cs.uwindsor.ca/~yourUWinID/customcore',
     */
    'base_url' => '',

    'timezone' => 'America/Toronto',

    'session_name' => 'CUSTOMCORESESSID',

    'session_idle_timeout' => 1800,
    'session_absolute_timeout' => 43200,
    'session_regenerate_interval' => 900,

    'default_theme' => 'rgb-gaming',

    'upload_max_bytes' => 2 * 1024 * 1024,

    'upload_allowed_extensions' => ['pdf', 'txt', 'png', 'jpg', 'jpeg', 'webp'],

    // Keep storage inside the project so shared hosting needs no special mounts
    'paths' => [
        'uploads_consultation' => 'uploads/consultation',
        'uploads_products' => 'uploads/products',
        'themes' => 'assets/themes',
        'images' => 'assets/images',
        'media' => 'assets/media',
    ],

    // Fictional academic store location (same structure as config/app.php)
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

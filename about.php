<?php
/**
 * CustomCore — About page (business case).
 *
 * File responsibility:
 *   Explains what CustomCore is and why it exists. Satisfies the rubric business
 *   description requirement with a clear public paragraph.
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About CustomCore — Business Case';
$pageDescription = 'Learn about CustomCore, the custom gaming PC store and guided PC-building platform.';
$pageKeywords = 'CustomCore, about, business case, gaming PC store';
$currentPage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

<article class="content-section">
    <h1>About CustomCore</h1>

    <p class="context-help">
        <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
    </p>

    <h2>Business case</h2>
    <p>
        CustomCore is an online gaming PC store and custom computer-building platform
        for customers who want a reliable system without guessing about part compatibility.
        The catalogue focuses on at least twenty configurable prebuilt gaming and creator
        desktops, organized into clear performance tiers, while a guided custom PC builder
        lets experienced users choose processors, motherboards, graphics cards, memory,
        storage, power supplies, cases, cooling, operating systems, and assembly services.
        Live price totals, simplified compatibility feedback, performance estimates,
        product comparison, reviews, wishlists, saved builds, consultation requests, and
        a simulated checkout with order history turn the site into a complete
        commercial-style application rather than a set of disconnected assignment pages.
        Administrators manage products and options, customer accounts, orders, reviews,
        consultation responses, switchable site themes, reports, multimedia content, and
        a monitoring dashboard that reports whether major services are online, in a
        warning state, or offline.
    </p>

    <h2>Who it is for</h2>
    <ul>
        <li>Casual PC users and first-time gaming PC buyers</li>
        <li>University students looking for clear budget options</li>
        <li>Competitive gamers, streamers, and content creators</li>
        <li>Experienced builders who want guided compatibility checks</li>
    </ul>

    <h2>What you can do next</h2>
    <p>
        The interactive catalogue and builder are assembled in upcoming stages.
        For now you can return to the
        <a href="<?php echo customcore_e(customcore_url('index.php')); ?>">homepage</a>
        or review the planned Help topics once the Help wiki is published.
    </p>
</article>

<?php
require_once __DIR__ . '/includes/footer.php';

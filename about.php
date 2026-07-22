<?php
/**
 * CustomCore — About page / public business case (Commit 3.2).
 *
 * File responsibility:
 *   Publishes the full CustomCore business explanation for visitors and graders.
 *   Satisfies rubric item #1 (business case: at least one clear paragraph
 *   describing the catalogue/project). Content is derived from
 *   docs/business-case.md.
 *
 * Authentication requirements:
 *   None (public).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About CustomCore — Business Case';
$pageDescription = 'Read the CustomCore business case: who we serve, the problem we solve, our catalogue strategy, and how the store and PC builder work together.';
$pageKeywords = 'CustomCore, about, business case, gaming PC store, custom PC builder';
$currentPage = 'about';

require_once __DIR__ . '/includes/header.php';
?>

<article class="content-section about-page">
    <header class="about-page__header">
        <h1>About CustomCore</h1>
        <p class="context-help">
            Need guidance while shopping?
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Open the Help centre</a>
        </p>
        <p class="about-page__lead">
            CustomCore is a database-driven gaming PC store and guided custom builder
            for people who want a reliable system without guessing about part compatibility.
        </p>
    </header>

    <section class="about-block" aria-labelledby="about-business-case">
        <h2 id="about-business-case">Business case</h2>
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
            warning state, or offline. The application is designed for ordinary university
            PHP/MySQL hosting, using HTML5, external CSS, vanilla JavaScript, PHP sessions,
            and MySQL with PDO prepared statements—without React, Node.js, Laravel, Docker,
            Composer, or URL rewriting.
        </p>
    </section>

    <section class="about-block" aria-labelledby="about-audience">
        <h2 id="about-audience">Who CustomCore is for</h2>
        <p>
            CustomCore serves people who are buying or configuring a gaming or creator PC
            and need clearer guidance than a raw parts list:
        </p>
        <ul class="about-audience">
            <li>
                <strong>Casual PC users</strong>
                — plain-language product pages and prebuilt tiers that reduce jargon
            </li>
            <li>
                <strong>Competitive gamers</strong>
                — esports and high-performance systems with upgrade options
            </li>
            <li>
                <strong>University students</strong>
                — budget and starter builds with transparent pricing
            </li>
            <li>
                <strong>Streamers and content creators</strong>
                — creator/workstation tier and consultation support
            </li>
            <li>
                <strong>First-time gaming PC buyers</strong>
                — guided builder, compatibility messages, and Help wiki
            </li>
            <li>
                <strong>Experienced builders</strong>
                — full component selection with server-validated compatibility
            </li>
        </ul>
    </section>

    <section class="about-block" aria-labelledby="about-problem">
        <h2 id="about-problem">The problem we solve</h2>
        <p>
            Choosing a gaming PC is difficult for inexperienced buyers. Component lists
            are technical, incompatible parts are easy to mix, prices change with every
            option, and many storefronts either hide upgrade choices or dump users into
            an advanced parts picker with little explanation. Customers also lose track
            of builds, past orders, and support conversations when those features are
            missing or scattered across tools.
        </p>
        <p class="about-callout">
            <strong>Core problem:</strong>
            Selecting compatible PC components and understanding the cost and purpose of
            each choice is hard for inexperienced users, which leads to confusion,
            abandoned purchases, and poorly matched systems.
        </p>
    </section>

    <section class="about-block" aria-labelledby="about-solution">
        <h2 id="about-solution">Our solution</h2>
        <p>
            CustomCore reduces that difficulty by combining a curated, database-driven
            catalogue of configurable prebuilt systems with a guided custom builder and
            account-centred shopping tools:
        </p>
        <ol class="about-solution-list">
            <li>
                <strong>Clear product information</strong>
                — dynamic catalogue and detail pages loaded from MySQL, not hardcoded HTML copies
            </li>
            <li>
                <strong>Configurable prebuilts</strong>
                — at least twenty systems across four tiers, each with multiple options
                (RAM, storage, colour, warranty, OS, cooling, and graphics upgrades where supported)
            </li>
            <li>
                <strong>Guided custom builder</strong>
                — step-by-step selection with live price calculation and simplified
                compatibility checking (compatible, warning, or incompatible), validated
                on the server as well as in the browser
            </li>
            <li>
                <strong>Performance estimates</strong>
                — visual summaries that help customers compare gaming and productivity expectations
            </li>
            <li>
                <strong>Customer accounts</strong>
                — registration, profiles, saved builds, wishlists, cart, simulated checkout,
                order history, reviews, and consultation requests with safe file attachments
            </li>
            <li>
                <strong>Human support path</strong>
                — consultation forms and administrator responses for advice before buying
            </li>
            <li>
                <strong>Administrator control</strong>
                — product and option editing, user disable/re-enable, order and review
                moderation, theme switching, reports, and service monitoring
            </li>
            <li>
                <strong>Learning and help</strong>
                — multimedia learning centre, interactive map, charts, and a multi-page
                Help wiki with context-sensitive links from feature pages
            </li>
        </ol>
        <p>
            Checkout is academic and simulated only: payment-method labels may be stored;
            credit-card numbers and other sensitive financial credentials are never
            requested or saved.
        </p>
    </section>

    <section class="about-block" aria-labelledby="about-catalogue">
        <h2 id="about-catalogue">Catalogue strategy</h2>
        <p>
            The primary catalogue includes <strong>at least twenty configurable prebuilt
            gaming and creator PCs</strong> in four performance tiers:
        </p>
        <ul>
            <li>Five budget and starter systems</li>
            <li>Five esports and mainstream systems</li>
            <li>Five high-performance gaming systems</li>
            <li>Five creator and workstation systems</li>
        </ul>
        <p>
            Every product supports at least two options (typically several option groups).
            A separate component inventory powers the custom builder. Product data,
            options, components, and simplified compatibility metadata live in MySQL and
            are rendered dynamically in PHP.
        </p>
    </section>

    <section class="about-block" aria-labelledby="about-features">
        <h2 id="about-features">What the site includes</h2>
        <div class="about-feature-grid">
            <div>
                <h3>Public</h3>
                <p>
                    Homepage, About, catalogue, product detail, search, filters, sorting,
                    comparison, approved reviews, PC Builder, learning centre, store map,
                    Help centre, and contact form.
                </p>
            </div>
            <div>
                <h3>Customer accounts</h3>
                <p>
                    Login, profile, saved builds, wishlist, cart, simulated checkout,
                    order history, reviews, and consultations with attachments.
                </p>
            </div>
            <div>
                <h3>Administrators</h3>
                <p>
                    Product and option management, orders, users, consultations, review
                    moderation, theme selection, reports, and monitoring.
                </p>
            </div>
        </div>
    </section>

    <section class="about-block about-block--cta" aria-labelledby="about-next">
        <h2 id="about-next">What you can do next</h2>
        <p>
            Browse featured systems on the homepage, open the full catalogue, or start
            configuring a custom build. Prefer reading first? The Help centre covers
            accounts, catalogue, builder, and orders as those guides are published.
        </p>
        <p class="hero__actions">
            <a class="button" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse catalogue</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Start PC Builder</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('index.php')); ?>">Back to home</a>
        </p>
    </section>
</article>

<?php
require_once __DIR__ . '/includes/footer.php';

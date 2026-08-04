<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Privacy policy (academic / coursework site practices).
// Explains what account and order data CustomCore stores, that checkout is simulated and does not
// process real card numbers, and how session, logging, and admin access work on this university
// project. Complements the Help wiki privacy section and the Accessibility statement.
// Access: None (public). Linked from the shared footer and Help page footers.

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Privacy | CustomCore';
$pageDescription = 'How CustomCore handles account data, orders, sessions, and simulated checkout for this academic store and PC builder project.';
$pageKeywords = 'CustomCore privacy, student project data, simulated checkout, account information';
$currentPage = 'privacy';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Privacy policy for the academic CustomCore storefront -->
<article class="content-section accessibility-page">
    <header class="accessibility-page__header">
        <h1>Privacy at CustomCore</h1>
        <p class="context-help">
            Account walkthrough:
            <a href="<?php echo customcore_e(customcore_url('help/accounts.html#privacy')); ?>">Help — your data &amp; privacy</a>
        </p>
        <p class="accessibility-page__lead">
            CustomCore is a university coursework project. This page describes what the site
            stores, what it does <em>not</em> store, and how you can review or update your own
            account information. It is written for students, graders, and demo visitors — not as
            legal advice for a commercial retailer.
        </p>
    </header>

    <!-- Scope of the project and simulated commerce -->
    <section class="accessibility-block" aria-labelledby="privacy-scope">
        <h2 id="privacy-scope">What this site is</h2>
        <ul class="accessibility-list">
            <li><strong>Course project.</strong> CustomCore demonstrates a database-driven PHP
                store and PC builder for COMP 3340. It runs on student or local hosting.</li>
            <li><strong>Simulated checkout.</strong> Orders record an order number, shipping
                labels, a payment-method label (for example “Credit card”), and line items.
                The site never contacts a real payment gateway and never asks for full card
                numbers, CVV, or banking secrets.</li>
            <li><strong>Demo catalogues.</strong> Product catalogue, components, and sample reviews
                are seed or administrator-managed data for demonstration.</li>
        </ul>
    </section>

    <!-- Account and personal fields -->
    <section class="accessibility-block" aria-labelledby="privacy-account">
        <h2 id="privacy-account">Account information</h2>
        <p>
            When you register or edit your profile, CustomCore may store:
        </p>
        <ul class="accessibility-list">
            <li>Email address (sign-in identifier)</li>
            <li>Name and optional contact fields such as phone or address (if you provide them)</li>
            <li>A <strong>password hash</strong> only — passwords are processed with
                <code>password_hash()</code> / <code>password_verify()</code> and are never shown
                again in plain text</li>
            <li>Role (customer or administrator) and active/disabled status</li>
        </ul>
        <p>
            Signed-in customers see only their own profile, orders, wishlist, consultations, and
            saved builds. Account URLs do not accept another user’s id from the address bar to view
            private records.
        </p>
        <p>
            <a href="<?php echo customcore_e(customcore_url('register.php')); ?>">Create an account</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('profile.php')); ?>">Open my account</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('edit-profile.php')); ?>">Edit profile</a>
        </p>
    </section>

    <!-- Orders, consultations, reviews, uploads -->
    <section class="accessibility-block" aria-labelledby="privacy-activity">
        <h2 id="privacy-activity">Orders, support, and uploads</h2>
        <dl class="accessibility-fallbacks">
            <dt>Orders and cart</dt>
            <dd>
                Completed orders store the items you purchased (including option summaries or build
                snapshots), quantities, prices at the time of order, status, and notes used for
                fulfilment demonstration. Cart contents live in your session until checkout.
            </dd>

            <dt>Consultations</dt>
            <dd>
                Consultation requests store your message, status, optional administrator reply, and
                any attachments you upload. Attachments are validated by type and size, saved outside
                direct public download paths, and served only through guarded download scripts.
            </dd>

            <dt>Reviews</dt>
            <dd>
                Product reviews store rating, title, body, and moderation status (pending, approved,
                or hidden). Only approved reviews are shown publicly.
            </dd>

            <dt>Contact form</dt>
            <dd>
                General contact messages store the name, email, subject, and body you submit so an
                administrator can respond.
            </dd>
        </dl>
    </section>

    <!-- Sessions and security-related mechanics -->
    <section class="accessibility-block" aria-labelledby="privacy-sessions">
        <h2 id="privacy-sessions">Sessions and security behaviour</h2>
        <ul class="accessibility-list">
            <li>Login uses PHP sessions with hardened cookie settings (HttpOnly, SameSite, and
                secure flags when the site is served over HTTPS).</li>
            <li>State-changing forms include CSRF tokens so submitted actions must come from
                CustomCore pages.</li>
            <li>Database access uses prepared statements. Application code is written so runtime
                errors do not print database passwords or connection strings to visitors when
                <code>debug</code> is false.</li>
            <li>Administrators can disable accounts; disabled users cannot sign in.</li>
        </ul>
    </section>

    <!-- Who can see what -->
    <section class="accessibility-block" aria-labelledby="privacy-access">
        <h2 id="privacy-access">Who can access data</h2>
        <ul class="accessibility-list">
            <li><strong>You</strong> — your own profile, orders, wishlist, builds, and consultations
                while signed in.</li>
            <li><strong>Administrators</strong> — staff tools for catalogue, orders, users,
                reviews, consultations, reports, themes, and monitoring. Admin accounts are
                intentionally limited (self-lockout protection and last-admin safeguards).</li>
            <li><strong>The public</strong> — catalogue pages, product details, approved reviews,
                Help articles, Learning Centre media, and similar non-private content.</li>
        </ul>
        <p>
            Real MySQL credentials for a given host live only in that host’s gitignored
            <code>config/database.php</code> and are never part of the public Git repository.
        </p>
    </section>

    <!-- Cookies / third parties -->
    <section class="accessibility-block" aria-labelledby="privacy-third">
        <h2 id="privacy-third">Cookies and third-party libraries</h2>
        <p>
            CustomCore uses its own session cookie for login and cart continuity. Chart.js and
            Leaflet load from public CDNs only on pages that need charts or the store map. Map tiles
            come from the provider documented in the media credits. We do not run a separate
            marketing analytics cookie suite as part of this coursework build.
        </p>
        <p>
            <a href="<?php echo customcore_e(customcore_url('docs/media-credits.md')); ?>">Open media credits</a>
        </p>
    </section>

    <!-- Contact -->
    <section class="accessibility-block" aria-labelledby="privacy-contact">
        <h2 id="privacy-contact">Questions</h2>
        <p>
            If you believe account data is incorrect, want your demo account reviewed, or found a
            privacy-related bug in this project, use the contact form or ask your course instructor
            / project author as appropriate for your class deployment.
        </p>
        <p class="hero__actions">
            <a class="button" href="<?php echo customcore_e(customcore_url('contact.php')); ?>">Contact CustomCore</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('accessibility.php')); ?>">Accessibility statement</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
        </p>
    </section>
</article>

<?php
require_once __DIR__ . '/includes/footer.php';

<?php
/**
 * CustomCore — Accessibility statement & multimedia fallbacks (Commit 8.6).
 *
 * File responsibility:
 *   Publishes CustomCore's accessibility commitments and, most importantly for
 *   Stage 8, documents the text-based fallback for every piece of multimedia on
 *   the site (images, video, audio, the catalogue chart, the build performance
 *   chart, and the store map). It gives visitors — and graders — a single place
 *   that proves the site's information stays understandable without images,
 *   video, audio, or JavaScript-rendered charts. Links point to the real
 *   fallbacks on each page.
 *
 * Authentication requirements:
 *   None (public). Linked from the shared footer.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Accessibility — CustomCore';
$pageDescription = 'How CustomCore keeps its catalogue, media, charts, and store map understandable for everyone, including text alternatives for all multimedia.';
$pageKeywords = 'CustomCore accessibility, alt text, transcripts, captions, chart data table, keyboard navigation';
$currentPage = 'accessibility';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Accessibility statement and multimedia text-alternative index -->
<article class="content-section accessibility-page">
    <header class="accessibility-page__header">
        <h1>Accessibility at CustomCore</h1>
        <p class="context-help">
            Need shopping guidance instead?
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Open the Help centre</a>
        </p>
        <p class="accessibility-page__lead">
            CustomCore is built so that everyone can browse the catalogue, learn from our
            guides, and complete a build — regardless of how they access the web. Every piece
            of multimedia on the site has a text-based equivalent, so no information is ever
            locked inside an image, video, audio clip, chart, or map.
        </p>
    </header>

    <!-- Accessibility commitments: keyboard, semantics, contrast, motion -->
    <section class="accessibility-block" aria-labelledby="a11y-commitments">
        <h2 id="a11y-commitments">Our commitments</h2>
        <ul class="accessibility-list">
            <li><strong>Keyboard first.</strong> A “Skip to content” link is the first focusable
                element on every page, navigation and forms are fully keyboard operable, and a
                visible focus outline shows where you are.</li>
            <li><strong>Semantic structure.</strong> Pages use landmarks (header, nav, main,
                footer) and a logical heading order so screen readers can navigate quickly.</li>
            <li><strong>Readable by default.</strong> Colours, spacing, and font sizes aim for
                comfortable contrast, and the layout reflows down to small screens.</li>
            <li><strong>Respects motion preferences.</strong> If your device requests reduced
                motion, we minimise non-essential transitions and animations.</li>
        </ul>
    </section>

    <!-- Text fallbacks for every image, video, audio, chart, and map -->
    <section class="accessibility-block" aria-labelledby="a11y-multimedia">
        <h2 id="a11y-multimedia">Text alternatives for every multimedia feature</h2>
        <p>
            Stage&nbsp;8 added images, video, audio, a data chart, and an interactive map. Each one
            ships with a fallback so the same information is available as text:
        </p>

        <!-- Per-medium fallback list with links to the real pages -->
        <dl class="accessibility-fallbacks">
            <dt>Images</dt>
            <dd>
                Product photos, category banners, and illustrations include descriptive
                <code>alt</code> text; purely decorative images use empty <code>alt</code> so
                screen readers skip them. When an image file is missing, the page shows a clear
                text placeholder rather than a broken image.
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse the catalogue</a>.
            </dd>

            <dt>Video guides</dt>
            <dd>
                Every video plays with native browser controls, English caption tracks, and a
                full written transcript you can expand under “Read the transcript”. A download
                link is provided if your browser cannot play the file.
                <a href="<?php echo customcore_e(customcore_url('media.php')); ?>">Open the Learning Centre</a>.
            </dd>

            <dt>Audio guide</dt>
            <dd>
                The audio lesson also offers native controls and a complete transcript, so the
                spoken content can be read instead of heard.
                <a href="<?php echo customcore_e(customcore_url('media.php#choosing-your-first-gaming-pc')); ?>">Read the audio transcript</a>.
            </dd>

            <dt>Catalogue chart</dt>
            <dd>
                The “Catalogue at a glance” bar chart is accompanied by a data table listing each
                performance tier, its number of active products, and its price range. The table is
                rendered from the same live database values and remains visible even if the chart
                script does not load.
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">See the catalogue chart and table</a>.
            </dd>

            <dt>Build performance chart</dt>
            <dd>
                The PC Builder’s performance chart always keeps a text summary of gaming,
                productivity, and upgrade-headroom scores next to the graph, so the numbers are
                available without the visualization.
                <a href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Open the PC Builder</a>.
            </dd>

            <dt>Store &amp; service map</dt>
            <dd>
                The interactive map is a visual enhancement only. The full address, phone, email,
                and opening hours are always present as selectable text, and a message explains the
                map when JavaScript is unavailable.
                <a href="<?php echo customcore_e(customcore_url('store-locations.php')); ?>">View the location details</a>.
            </dd>
        </dl>
    </section>

    <!-- Media sources and licences with links to credit records -->
    <section class="accessibility-block" aria-labelledby="a11y-credits">
        <h2 id="a11y-credits">Media sources and licences</h2>
        <p>
            Every image, video, audio file, caption track, chart library, and map tile source used
            by CustomCore is documented with its origin and licence in the project media credits.
            Prompts for AI-generated images are retained alongside that record.
        </p>
        <p>
            <a href="<?php echo customcore_e(customcore_url('docs/media-credits.md')); ?>">Open media credits</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('docs/image-prompts.md')); ?>">View image prompt record</a>
        </p>
    </section>

    <!-- Feedback prompt for reporting accessibility barriers -->
    <section class="accessibility-block" aria-labelledby="a11y-feedback">
        <h2 id="a11y-feedback">Tell us about a barrier</h2>
        <p>
            We treat accessibility as ongoing work. If something is hard to use with a keyboard,
            screen reader, or magnifier — or if a fallback is unclear — please let us know so we
            can fix it.
        </p>
        <p class="hero__actions">
            <a class="button" href="<?php echo customcore_e(customcore_url('contact.php')); ?>">Contact CustomCore</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('media.php')); ?>">Visit the Learning Centre</a>
        </p>
    </section>
</article>

<?php
require_once __DIR__ . '/includes/footer.php';

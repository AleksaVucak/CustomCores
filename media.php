<?php
/**
 * CustomCore — Multimedia Learning Centre (Commit 8.2).
 *
 * File responsibility:
 *   Public page that plays the educational video and audio guides with native
 *   HTML5 controls, posters, captions tracks, and readable transcripts so all
 *   three Learning Centre media items are playable in the browser.
 *
 * Authentication requirements:
 *   None (public).
 *
 * Data sources:
 *   includes/media.php catalogue pointing at assets/media/ files on disk.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/media.php';

$pageTitle = 'Learning Centre — CustomCore';
$pageDescription = 'Learn how to use the CustomCore PC Builder, understand component compatibility, and choose the right gaming PC tier.';
$pageKeywords = 'CustomCore learning centre, PC builder tutorial, PC compatibility basics, first gaming PC, gaming PC guide';
$currentPage = 'media';

$mediaItems = customcore_media_items();

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section learning-centre" aria-labelledby="learning-heading">
    <header class="learning-centre__header">
        <p class="learning-centre__eyebrow">CustomCore Learning Centre</p>
        <h1 id="learning-heading">Build with more confidence</h1>
        <p class="learning-centre__intro">
            These short lessons explain the tools and decisions behind a CustomCore build.
            They are designed for students, first-time buyers, gamers, and creators who want
            practical guidance without unnecessary technical jargon.
        </p>
        <p class="context-help">
            Prefer reading?
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Open the Help centre</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">PC Builder guide</a>
        </p>
    </header>

    <?php if ($mediaItems === []) : ?>
        <p class="empty-state" role="status">
            Educational media files are not available on this server yet.
            Check that the video and audio files are present under
            <code>assets/media/</code>.
        </p>
    <?php else : ?>
        <div class="media-grid">
            <?php foreach ($mediaItems as $item) : ?>
                <?php
                $isVideo = $item['type'] === 'video';
                $typeLabel = $isVideo ? 'Video' : 'Audio';
                $playerId = 'media-player-' . $item['id'];
                ?>
                <article class="media-card" id="<?php echo customcore_e($item['id']); ?>">
                    <div class="media-card__player">
                        <?php if ($isVideo) : ?>
                            <video
                                id="<?php echo customcore_e($playerId); ?>"
                                class="media-card__video"
                                controls
                                preload="metadata"
                                <?php if ($item['poster_url'] !== null) : ?>
                                    poster="<?php echo customcore_e($item['poster_url']); ?>"
                                <?php endif; ?>
                            >
                                <source
                                    src="<?php echo customcore_e($item['src_url']); ?>"
                                    type="<?php echo customcore_e($item['mime']); ?>"
                                >
                                <?php if ($item['captions_url'] !== null) : ?>
                                    <track
                                        kind="captions"
                                        src="<?php echo customcore_e($item['captions_url']); ?>"
                                        srclang="en"
                                        label="English"
                                        default
                                    >
                                <?php endif; ?>
                                Your browser does not support HTML5 video.
                                <a href="<?php echo customcore_e($item['src_url']); ?>">Download the video</a>
                                instead.
                            </video>
                        <?php else : ?>
                            <?php if ($item['poster_url'] !== null) : ?>
                                <img
                                    class="media-card__poster"
                                    src="<?php echo customcore_e($item['poster_url']); ?>"
                                    alt="Poster for <?php echo customcore_e($item['title']); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    width="960"
                                    height="540"
                                >
                            <?php endif; ?>
                            <audio
                                id="<?php echo customcore_e($playerId); ?>"
                                class="media-card__audio"
                                controls
                                preload="metadata"
                            >
                                <source
                                    src="<?php echo customcore_e($item['src_url']); ?>"
                                    type="<?php echo customcore_e($item['mime']); ?>"
                                >
                                Your browser does not support HTML5 audio.
                                <a href="<?php echo customcore_e($item['src_url']); ?>">Download the audio</a>
                                instead.
                            </audio>
                        <?php endif; ?>
                    </div>

                    <div class="media-card__content">
                        <p class="media-card__meta">
                            <?php echo customcore_e($typeLabel); ?>
                            ·
                            <?php echo customcore_e($item['duration_label']); ?>
                        </p>
                        <h2 class="media-card__title"><?php echo customcore_e($item['title']); ?></h2>
                        <p class="media-card__description">
                            <?php echo customcore_e($item['description']); ?>
                        </p>

                        <?php if ($item['learn'] !== []) : ?>
                            <h3 class="media-card__subheading">What you'll learn</h3>
                            <ul class="media-card__learn">
                                <?php foreach ($item['learn'] as $point) : ?>
                                    <li><?php echo customcore_e($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($item['transcript'] !== []) : ?>
                            <details class="media-card__transcript">
                                <summary>Read the transcript</summary>
                                <div class="media-card__transcript-body">
                                    <?php foreach ($item['transcript'] as $paragraph) : ?>
                                        <p><?php echo customcore_e($paragraph); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="learning-centre__help" aria-labelledby="shopping-help-heading">
        <h2 id="shopping-help-heading">How these lessons help you shop CustomCore</h2>
        <p>
            The Learning Centre connects directly to the catalogue and PC Builder. Use the
            first lesson while creating a build, review the compatibility lesson when a warning
            appears, and use the tier guide before comparing complete systems. The goal is to
            help you make a clear, budget-aware decision rather than simply choosing the most
            expensive configuration.
        </p>
        <p class="hero__actions">
            <a class="button" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Start PC Builder</a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse catalogue</a>
            <a class="button button--ghost" href="<?php echo customcore_e(customcore_url('help/support.html')); ?>">Support guide</a>
        </p>
    </section>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

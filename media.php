<?php
/**
 * CustomCore — Multimedia Learning Centre (Commit 8.3).
 *
 * File responsibility:
 *   Public showcase for the educational media. Presents an organized, responsive
 *   directory of lessons that jump to full players, each playing with native
 *   HTML5 controls, posters, caption tracks, learning outcomes, and readable
 *   transcripts. Media catalogue and playable items come from includes/media.php
 *   (Commit 8.2); this page focuses on presentation and context.
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

$videoCount = 0;
$audioCount = 0;
foreach ($mediaItems as $mediaItem) {
    if ($mediaItem['type'] === 'video') {
        $videoCount++;
    } else {
        $audioCount++;
    }
}

/**
 * Human-readable summary of the lesson mix (e.g. "2 videos and 1 audio guide").
 */
$mediaSummaryParts = [];
if ($videoCount > 0) {
    $mediaSummaryParts[] = $videoCount . ' ' . ($videoCount === 1 ? 'video' : 'videos');
}
if ($audioCount > 0) {
    $mediaSummaryParts[] = $audioCount . ' ' . ($audioCount === 1 ? 'audio guide' : 'audio guides');
}
$mediaSummary = '';
if ($mediaSummaryParts !== []) {
    $mediaSummary = count($mediaSummaryParts) === 2
        ? $mediaSummaryParts[0] . ' and ' . $mediaSummaryParts[1]
        : $mediaSummaryParts[0];
}

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
        <nav class="media-directory" aria-labelledby="media-directory-heading">
            <div class="media-directory__intro">
                <h2 id="media-directory-heading">Lessons in this centre</h2>
                <?php if ($mediaSummary !== '') : ?>
                    <p class="media-directory__summary">
                        <?php echo customcore_e(count($mediaItems) . ' short ' . (count($mediaItems) === 1 ? 'lesson' : 'lessons')); ?>
                        — <?php echo customcore_e($mediaSummary); ?>. Select a lesson to jump to its player.
                    </p>
                <?php endif; ?>
            </div>

            <ol class="media-directory__list">
                <?php foreach ($mediaItems as $index => $item) : ?>
                    <?php $isVideo = $item['type'] === 'video'; ?>
                    <li class="media-directory__item">
                        <a class="media-directory__card" href="#<?php echo customcore_e($item['id']); ?>">
                            <span class="media-directory__thumb">
                                <?php if ($item['poster_url'] !== null) : ?>
                                    <img
                                        src="<?php echo customcore_e($item['poster_url']); ?>"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        width="480"
                                        height="270"
                                    >
                                <?php endif; ?>
                                <span class="media-directory__badge media-directory__badge--<?php echo $isVideo ? 'video' : 'audio'; ?>">
                                    <?php echo $isVideo ? 'Video' : 'Audio'; ?>
                                </span>
                            </span>
                            <span class="media-directory__text">
                                <span class="media-directory__step">Lesson <?php echo customcore_e((string) ($index + 1)); ?></span>
                                <span class="media-directory__name"><?php echo customcore_e($item['title']); ?></span>
                                <span class="media-directory__duration"><?php echo customcore_e($item['duration_label']); ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="media-grid" aria-label="Learning Centre lessons">
            <?php foreach ($mediaItems as $index => $item) : ?>
                <?php
                $isVideo = $item['type'] === 'video';
                $typeLabel = $isVideo ? 'Video' : 'Audio';
                $playerId = 'media-player-' . $item['id'];
                $titleId = 'media-title-' . $item['id'];
                ?>
                <article
                    class="media-card"
                    id="<?php echo customcore_e($item['id']); ?>"
                    aria-labelledby="<?php echo customcore_e($titleId); ?>"
                    tabindex="-1"
                >
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
                                    alt="<?php echo customcore_e($item['poster_alt']); ?>"
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
                            <span class="media-card__type media-card__type--<?php echo $isVideo ? 'video' : 'audio'; ?>">
                                <?php echo customcore_e($typeLabel); ?>
                            </span>
                            <span class="media-card__duration"><?php echo customcore_e($item['duration_label']); ?></span>
                        </p>
                        <h3 class="media-card__title" id="<?php echo customcore_e($titleId); ?>">
                            <?php echo customcore_e($item['title']); ?>
                        </h3>
                        <p class="media-card__description">
                            <?php echo customcore_e($item['description']); ?>
                        </p>

                        <?php if ($item['learn'] !== []) : ?>
                            <h4 class="media-card__subheading">What you'll learn</h4>
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

    <section class="learning-centre__credits" aria-labelledby="media-credits-heading">
        <h2 id="media-credits-heading">Media credits</h2>
        <p>
            These lessons are original CustomCore academic productions with English captions and
            full transcripts. Catalogue imagery is AI-generated for this university project and is
            free of third-party product photography. Complete source, licence, and prompt records
            are published in the project documentation.
        </p>
        <p>
            <a href="<?php echo customcore_e(customcore_url('docs/media-credits.md')); ?>">Open media credits</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('accessibility.php')); ?>">Accessibility statement</a>
        </p>
    </section>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

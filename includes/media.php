<?php
/**
 * CustomCore — Educational media catalogue helpers (Commit 8.2).
 *
 * File responsibility:
 *   Describes the Learning Centre video/audio items stored under assets/media/
 *   so pages can render native HTML5 players without hard-coding paths in views.
 *
 * Usage:
 *   require_once __DIR__ . '/media.php';
 *   $items = customcore_media_items();
 */

declare(strict_types=1);

/**
 * Declared Learning Centre media catalogue (before on-disk availability filtering).
 *
 * The source-of-truth list of lessons the site expects to ship. It is used by
 * customcore_media_items() (which drops any item whose primary media file is
 * missing so the Learning Centre never advertises a broken player) and by the
 * Stage 13 monitoring health checks (which compare this declared list against
 * what actually resolves on disk to detect missing media). Editing lessons is
 * documented in docs/content-update-guide.md.
 *
 * @return list<array<string, mixed>>
 */
function customcore_media_catalogue(): array
{
    return [
        [
            'id' => 'how-to-use-pc-builder',
            'type' => 'video',
            'title' => 'How to Use the CustomCore PC Builder',
            'description' => 'A quick walkthrough of choosing parts, watching the live total, and understanding compatibility messages. It also shows how signed-in users can save a build for later.',
            'duration_label' => 'About 1 minute 7 seconds',
            'mime' => 'video/mp4',
            'src' => 'assets/media/how-to-use-pc-builder.mp4',
            'poster' => 'assets/images/media/pc-builder-poster.jpg',
            'poster_alt' => 'Open PC case and neatly arranged components for the PC Builder tutorial.',
            'captions' => 'assets/media/captions/how-to-use-pc-builder.vtt',
            'learn' => [
                'Move through the component categories in a sensible order',
                'Read the live price summary',
                'Understand Compatible, Warning, and Incompatible messages',
                'Save a completed build to your account',
            ],
            'transcript' => [
                'Welcome to the CustomCore PC Builder. Start by choosing a processor, then select a motherboard that uses the same socket. Continue through memory, graphics, storage, power supply, case, and cooling.',
                'As you make selections, the price panel updates automatically. This total is an estimate based on the current component prices and selected services.',
                'Watch the compatibility panel while you build. A green Compatible message means the current choices work together. A Warning means the build can continue, but you should review the note. An Incompatible message identifies a combination that must be changed, such as the wrong processor socket or an undersized power supply.',
                'Before finishing, review the build summary. Confirm each component, the estimated total, and any remaining warnings. If you are signed in, give the build a name and save it to your account. You can return later to edit it, add it to your cart, or use it when requesting a consultation.',
            ],
        ],
        [
            'id' => 'compatibility-basics',
            'type' => 'video',
            'title' => 'PC Compatibility Basics for Beginners',
            'description' => 'A beginner-friendly explanation of the compatibility checks used by CustomCore. The lesson focuses on sockets, memory type, power, case space, cooling, and storage support.',
            'duration_label' => 'About 1 minute 15 seconds',
            'mime' => 'video/mp4',
            'src' => 'assets/media/compatibility-basics.mp4',
            'poster' => 'assets/images/media/compatibility-basics-poster.jpg',
            'poster_alt' => 'CPU, motherboard, memory, power supply, and graphics card arranged for a compatibility lesson.',
            'captions' => 'assets/media/captions/compatibility-basics.vtt',
            'learn' => [
                'Match a processor socket to the motherboard',
                'Match the motherboard\'s supported memory type',
                'Estimate power-supply and case-clearance requirements',
                'Recognize why cooling and storage support matter',
            ],
            'transcript' => [
                'PC compatibility means checking that your selected parts can connect, fit, and receive enough power.',
                'First, the processor socket must match the motherboard socket. A processor designed for one socket cannot be installed in a different socket. Second, the memory type must match the motherboard. For example, a motherboard designed for one generation of memory cannot use a different generation.',
                'Third, the power supply needs enough wattage for the processor, graphics card, and the rest of the system. A small safety margin is useful, but a larger number is not automatically better.',
                'Physical space matters too. The motherboard must fit the case, the graphics card must fit within the case’s supported length, and the cooler must fit without blocking other components. Finally, check storage support. The motherboard needs the correct connector for the selected drive.',
                'CustomCore simplifies these checks into Compatible, Warning, and Incompatible messages. These messages are educational estimates, so always review the final specifications before purchasing real hardware.',
            ],
        ],
        [
            'id' => 'choosing-your-first-gaming-pc',
            'type' => 'audio',
            'title' => 'Choosing Your First Gaming PC',
            'description' => 'An audio guide to the four CustomCore performance tiers. It helps shoppers choose based on the games, coursework, creative applications, and monitor they actually use.',
            'duration_label' => 'About 1 minute 28 seconds',
            'mime' => 'audio/mpeg',
            'src' => 'assets/media/choosing-your-first-gaming-pc.mp3',
            'poster' => 'assets/images/media/first-gaming-pc-poster.jpg',
            'poster_alt' => 'Four desktop PCs representing the Budget, Esports, High-Performance, and Creator tiers.',
            'captions' => 'assets/media/captions/choosing-your-first-gaming-pc.vtt',
            'learn' => [
                'Understand the purpose of each CustomCore tier',
                'Match a system to games, monitor resolution, and schoolwork',
                'Avoid paying for performance that will not be used',
                'Know when a Creator system is better than a gaming-first system',
            ],
            'transcript' => [
                'Choosing your first gaming PC is easier when you begin with what you actually plan to do.',
                'The Budget tier is designed for students and first-time buyers who want everyday schoolwork and comfortable entry-level gaming. It is a practical choice for lighter games and standard 1080p monitors.',
                'The Esports tier focuses on fast, competitive games. These systems prioritize smooth frame rates and responsive performance, making them a good match for high-refresh 1080p monitors.',
                'The High-Performance tier is for demanding games, higher resolutions, and stronger visual settings. Choose this tier when you use a 1440p or 4K display, play graphically intensive titles, or want more room for future upgrades.',
                'The Creator tier balances gaming with editing, streaming, 3D work, programming, and other production tasks. These systems often emphasize more memory, storage, and multi-core performance instead of gaming performance alone.',
                'Before choosing, list your main games and applications, your monitor resolution, and your maximum budget. Then leave room for a good monitor, keyboard, and backup storage. The best system is not always the most expensive one. It is the system that fits your real workload without unnecessary upgrades.',
            ],
        ],
    ];
}

/**
 * Catalogue of the educational Learning Centre media items, filtered to those
 * whose primary media file exists on disk so the site never advertises a broken
 * player.
 *
 * @return list<array{
 *   id:string,
 *   type:string,
 *   title:string,
 *   description:string,
 *   duration_label:string,
 *   mime:string,
 *   src:string,
 *   src_url:string,
 *   poster:?string,
 *   poster_url:?string,
 *   poster_alt:string,
 *   captions:?string,
 *   captions_url:?string,
 *   learn:list<string>,
 *   transcript:list<string>
 * }>
 */
function customcore_media_items(): array
{
    $items = [];

    foreach (customcore_media_catalogue() as $item) {
        $srcUrl = customcore_media_url($item['src']);
        if ($srcUrl === null) {
            continue;
        }

        $posterPath = isset($item['poster']) ? (string) $item['poster'] : null;
        $captionsPath = isset($item['captions']) ? (string) $item['captions'] : null;

        $items[] = [
            'id' => (string) $item['id'],
            'type' => (string) $item['type'],
            'title' => (string) $item['title'],
            'description' => (string) $item['description'],
            'duration_label' => (string) $item['duration_label'],
            'mime' => (string) $item['mime'],
            'src' => (string) $item['src'],
            'src_url' => $srcUrl,
            'poster' => $posterPath,
            'poster_url' => customcore_image_url($posterPath),
            'poster_alt' => isset($item['poster_alt']) ? (string) $item['poster_alt'] : (string) $item['title'],
            'captions' => $captionsPath,
            'captions_url' => customcore_media_url($captionsPath),
            'learn' => array_values(array_map('strval', $item['learn'])),
            'transcript' => array_values(array_map('strval', $item['transcript'])),
        ];
    }

    return $items;
}

/**
 * Fetch a single Learning Centre media item by id.
 *
 * @return ?array{
 *   id:string,
 *   type:string,
 *   title:string,
 *   description:string,
 *   duration_label:string,
 *   mime:string,
 *   src:string,
 *   src_url:string,
 *   poster:?string,
 *   poster_url:?string,
 *   poster_alt:string,
 *   captions:?string,
 *   captions_url:?string,
 *   learn:list<string>,
 *   transcript:list<string>
 * }
 */
function customcore_media_item(string $id): ?array
{
    foreach (customcore_media_items() as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }

    return null;
}

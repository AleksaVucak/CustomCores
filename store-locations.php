<?php
/**
 * CustomCore — Store & service location (Commit 8.4).
 *
 * File responsibility:
 *   Public page showing the fictional CustomCore Campus Service Desk with an
 *   interactive Leaflet/OpenStreetMap map AND an always-visible text address,
 *   hours, and contact fallback. The address stays fully usable if JavaScript,
 *   Leaflet, or the map tiles fail to load.
 *
 * Authentication requirements:
 *   None (public).
 *
 * Data source:
 *   config/app.php -> 'store_location' (fictional academic-project data).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$app = customcore_app_config();
$location = is_array($app['store_location'] ?? null) ? $app['store_location'] : [];

$locName = (string) ($location['name'] ?? 'CustomCore Service Desk');
$locStreet = (string) ($location['street'] ?? '');
$locCity = (string) ($location['city'] ?? '');
$locRegion = (string) ($location['region'] ?? '');
$locPostal = (string) ($location['postal_code'] ?? '');
$locPhoneDisplay = (string) ($location['phone_display'] ?? '');
$locPhoneHref = (string) ($location['phone_href'] ?? '');
$locEmail = (string) ($location['email'] ?? '');
$locLat = isset($location['latitude']) ? (float) $location['latitude'] : null;
$locLng = isset($location['longitude']) ? (float) $location['longitude'] : null;
$locZoom = isset($location['map_zoom']) ? (int) $location['map_zoom'] : 14;
$locHours = is_array($location['hours'] ?? null) ? $location['hours'] : [];
$locImageUrl = customcore_image_url($location['image'] ?? null);
$locImageAlt = (string) ($location['image_alt'] ?? $locName);

$cityRegionPostal = trim(implode(', ', array_filter([
    $locCity,
    trim($locRegion . ' ' . $locPostal),
])));

$hasMap = $locLat !== null && $locLng !== null;

$pageTitle = 'Store & Service Location — CustomCore';
$pageDescription = 'View the fictional CustomCore Campus Service Desk location, hours, order pickup, and consultation details.';
$pageKeywords = 'CustomCore location, Windsor PC consultation, simulated PC pickup, academic project location';
$currentPage = 'locations';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section location-page" aria-labelledby="location-heading">
    <header class="location-page__header">
        <p class="location-page__eyebrow">Fictional coursework location</p>
        <h1 id="location-heading"><?php echo customcore_e($locName); ?></h1>
        <p class="location-page__intro">
            The <?php echo customcore_e($locName); ?> supports scheduled order pickup, beginner PC
            consultations, and basic build-planning appointments for this academic demonstration.
            Visitors can review saved builds, ask about compatibility warnings, or arrange pickup
            for a simulated order.
        </p>
        <p class="context-help">
            Planning a visit?
            <a href="<?php echo customcore_e(customcore_url('consultation.php')); ?>">Request a consultation</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/support.html')); ?>">Support guide</a>
        </p>
    </header>

    <div class="location-layout">
        <div class="location-details">
            <h2 id="location-details-heading">Location details</h2>

            <address class="location-fallback">
                <strong><?php echo customcore_e($locName); ?></strong><br>
                <?php if ($locStreet !== '') : ?>
                    <?php echo customcore_e($locStreet); ?><br>
                <?php endif; ?>
                <?php if ($cityRegionPostal !== '') : ?>
                    <?php echo customcore_e($cityRegionPostal); ?><br>
                <?php endif; ?>
                <?php if ($locPhoneDisplay !== '') : ?>
                    Phone:
                    <?php if ($locPhoneHref !== '') : ?>
                        <a href="tel:<?php echo customcore_e($locPhoneHref); ?>"><?php echo customcore_e($locPhoneDisplay); ?></a>
                    <?php else : ?>
                        <?php echo customcore_e($locPhoneDisplay); ?>
                    <?php endif; ?>
                    <br>
                <?php endif; ?>
                <?php if ($locEmail !== '') : ?>
                    Email:
                    <a href="mailto:<?php echo customcore_e($locEmail); ?>"><?php echo customcore_e($locEmail); ?></a>
                <?php endif; ?>
            </address>

            <?php if ($locHours !== []) : ?>
                <h3>Hours</h3>
                <dl class="location-hours">
                    <?php foreach ($locHours as $days => $time) : ?>
                        <div class="location-hours__row">
                            <dt><?php echo customcore_e((string) $days); ?></dt>
                            <dd><?php echo customcore_e((string) $time); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <p class="location-note">
                Appointments are recommended so the correct demonstration equipment is available.
                This address and all contact details are fictional and created only for coursework.
            </p>
        </div>

        <div class="location-map-column">
            <?php if ($locImageUrl !== null) : ?>
                <img
                    class="location-photo"
                    src="<?php echo customcore_e($locImageUrl); ?>"
                    alt="<?php echo customcore_e($locImageAlt); ?>"
                    loading="lazy"
                    decoding="async"
                    width="960"
                    height="720"
                >
            <?php endif; ?>

            <?php if ($hasMap) : ?>
                <div
                    id="customcore-map"
                    class="location-map"
                    role="region"
                    aria-label="Map showing the fictional <?php echo customcore_e($locName); ?> near <?php echo customcore_e($locCity !== '' ? $locCity : 'the service desk'); ?>"
                    data-lat="<?php echo customcore_e((string) $locLat); ?>"
                    data-lng="<?php echo customcore_e((string) $locLng); ?>"
                    data-zoom="<?php echo customcore_e((string) $locZoom); ?>"
                    data-name="<?php echo customcore_e($locName); ?>"
                    data-street="<?php echo customcore_e($locStreet); ?>"
                    data-locality="<?php echo customcore_e($cityRegionPostal); ?>"
                    data-phone="<?php echo customcore_e($locPhoneDisplay); ?>"
                    data-phone-href="<?php echo customcore_e($locPhoneHref); ?>"
                    data-email="<?php echo customcore_e($locEmail); ?>"
                >
                    <noscript>
                        <p class="location-map__noscript">
                            The interactive map needs JavaScript. The full address, hours, and
                            contact details are listed beside this map.
                        </p>
                    </noscript>
                </div>
                <p class="location-map__note">
                    The map is supplementary. The complete location details remain available in
                    text beside the map if JavaScript or the map tiles are unavailable. Map data
                    &copy; OpenStreetMap contributors.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';

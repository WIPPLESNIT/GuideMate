<?php
/**
 * @var array<int,array<string,mixed>> $bookings
 * @var int $focusId
 * @var string $mapillaryToken
 * @var string $mapboxToken
 */
$mapillaryToken = $mapillaryToken ?? '';
$mapboxToken = $mapboxToken ?? '';
$today = date('Y-m-d');
$mapPins = [];
foreach ($bookings as $b) {
    $address = trim((string) ($b['address'] ?? ''));
    $googleQuery = $b['listing_title'];
    if ($address !== '') {
        $googleQuery .= ', ' . $address;
    } else {
        $googleQuery .= ', ' . ($b['area'] ?? 'Cebu') . ', Philippines';
    }
    $mapPins[] = [
        'id' => (int) $b['id'],
        'title' => $b['listing_title'],
        'slug' => $b['listing_slug'],
        'area' => $b['area'],
        'address' => $address,
        'googleQuery' => $googleQuery,
        'date' => $b['booking_date'],
        'dateLabel' => date('M j, Y', strtotime($b['booking_date'])),
        'lat' => (float) $b['latitude'],
        'lng' => (float) $b['longitude'],
        'upcoming' => $b['booking_date'] >= $today,
        'approximate' => !empty($b['approximate']),
    ];
}
$mapPinsJson = json_encode($mapPins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
?>
<section class="page-head page-head-compact">
    <div class="container trip-map-head">
        <div>
            <h1>Trip map</h1>
            <p class="mb-0">Mapbox satellite + Mapillary street photos — pinned to your guide’s address.</p>
        </div>
        <a href="<?= e(url('/bookings')) ?>" class="btn btn-ghost btn-sm">← My bookings</a>
    </div>
</section>

<section class="section section-trip-map">
    <div class="container">
        <?php if ($bookings === []): ?>
            <div class="empty-state">
                <div class="big">🗺️</div>
                <h3>No locations to show yet</h3>
                <p>When you have a paid, confirmed booking with a map location, it will appear here.</p>
                <a href="<?= e(url('/listings')) ?>" class="btn btn-primary mt-2">Browse listings</a>
            </div>
        <?php else: ?>
            <?php if ($mapboxToken === ''): ?>
                <div class="panel trip-map-token-hint mb-2">
                    <p class="mb-0 hint">Add <strong>MAPBOX_ACCESS_TOKEN</strong> in <code>.env</code> for the enhanced map.</p>
                </div>
            <?php endif; ?>
            <div class="trip-map-layout">
                <aside class="trip-map-sidebar panel">
                    <div class="panel-head">
                        <h3>Your bookings</h3>
                        <span class="hint" id="locStatus">Locating…</span>
                    </div>
                    <div class="panel-body trip-map-list" id="tripMapList">
                        <?php foreach ($bookings as $b):
                            $isUpcoming = $b['booking_date'] >= $today;
                            $isFocus = $focusId === (int) $b['id'];
                        ?>
                            <button type="button"
                                    class="trip-map-item<?= $isFocus ? ' is-active' : '' ?><?= $isUpcoming ? ' is-upcoming' : '' ?>"
                                    data-booking-id="<?= (int) $b['id'] ?>">
                                <strong><?= e($b['listing_title']) ?></strong>
                                <span class="hint"><?= e(date('M j, Y', strtotime($b['booking_date']))) ?> · <?= e($b['area']) ?></span>
                                <?php if (!empty($b['address'])): ?>
                                    <span class="hint trip-map-address"><?= e($b['address']) ?></span>
                                <?php elseif (!empty($b['approximate'])): ?>
                                    <span class="hint trip-map-address">Approximate area pin (from <?= e($b['area']) ?>)</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="trip-map-actions panel-body" id="tripMapActions" hidden>
                        <p class="hint mb-1" id="tripMapSelectedLabel"></p>
                        <p class="hint mb-1" id="tripMapAddressLabel"></p>
                        <button type="button" class="btn btn-primary btn-sm trip-street-view-btn" id="tripStreetViewBtn">Street View</button>
                    </div>
                </aside>

                <div class="trip-map-wrap panel">
                    <div class="trip-map-toolbar">
                        <div class="map-layer-toggle" role="group" aria-label="Map style">
                            <button type="button" class="map-layer-btn is-active" data-layer="satellite">Satellite</button>
                            <button type="button" class="map-layer-btn" data-layer="streets">Streets</button>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" id="tripMapFitRoute" title="Show my location and booking">Show route</button>
                    </div>
                    <div class="trip-map-split" id="tripMapSplit">
                        <div class="trip-map-stage">
                            <div id="tripMap" class="trip-map-canvas"
                                 data-focus="<?= (int) $focusId ?>"
                                 data-pins="<?= e($mapPinsJson) ?>"
                                 data-mapbox-token="<?= e($mapboxToken) ?>"
                                 data-mapillary-token="<?= e($mapillaryToken) ?>"></div>
                        </div>
                        <div class="trip-street-panel" id="tripStreetPanel" hidden>
                            <div class="trip-street-head">
                                <strong>Street View</strong>
                                <button type="button" class="btn btn-ghost btn-sm" id="tripStreetClose">Close</button>
                            </div>
                            <div class="trip-street-iframe-wrap">
                                <div id="tripMapillaryViewer" class="trip-mapillary-viewer" hidden></div>
                                <iframe id="tripStreetFrame" class="trip-street-frame" title="Street View" loading="lazy" allowfullscreen hidden></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($bookings !== []): ?>
<link href="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.css" rel="stylesheet">
<link href="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css" rel="stylesheet">
<script src="https://api.mapbox.com/mapbox-gl-js/v3.9.4/mapbox-gl.js"></script>
<script src="https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.js"></script>
<script src="<?= e(asset('js/trip-map.js')) ?>"></script>
<?php endif; ?>

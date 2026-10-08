<?php
/**
 * @var string $heading @var string $sub
 * @var array<int,array<string,mixed>> $listings
 * @var array<int,array<string,mixed>> $mapListings
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,mixed> $filters
 * @var ?string $activeCategory
 * @var array<int,int> $favIds
 */
?>
<section class="page-head">
    <div class="container">
        <h1><?= e($heading) ?></h1>
        <p><?= e($sub) ?></p>
    </div>
</section>

<section class="section">
    <div class="container browse-layout">
        <aside class="filters">
            <h3>Filter &amp; sort</h3>
            <form method="get" action="<?= e(url($activeCategory ? '/' . $activeCategory : '/listings')) ?>" id="browseFilterForm" novalidate>
                <div class="filter-group">
                    <label for="filter_q">Keyword</label>
                    <input class="input" id="filter_q" type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search...">
                </div>
                <div class="filter-group">
                    <label for="filter_area">Area</label>
                    <input class="input" id="filter_area" type="text" name="area" value="<?= e($filters['area']) ?>" placeholder="e.g. Oslob">
                </div>
                <?php if ($activeCategory === null): ?>
                <div class="filter-group">
                    <label for="filter_category">Category</label>
                    <select class="input" id="filter_category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c['slug']) ?>" <?= $filters['category'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label for="filter_rating">Minimum rating</label>
                    <select class="input" id="filter_rating" name="min_rating">
                        <option value="any" <?= in_array($filters['min_rating'], ['', 'any'], true) ? 'selected' : '' ?>>Any rating</option>
                        <option value="4.0" <?= in_array($filters['min_rating'], ['4', '4.0'], true) ? 'selected' : '' ?>>4.0+</option>
                        <option value="4.5" <?= in_array($filters['min_rating'], ['4.5'], true) ? 'selected' : '' ?>>4.5+</option>
                        <option value="5.0" <?= in_array($filters['min_rating'], ['5', '5.0'], true) ? 'selected' : '' ?>>5.0</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter_sort">Sort by</label>
                    <select class="input" id="filter_sort" name="sort">
                        <option value="recommended" <?= $filters['sort'] === 'recommended' ? 'selected' : '' ?>>Recommended</option>
                        <option value="rating" <?= in_array($filters['sort'], ['rating', ''], true) ? 'selected' : '' ?>>Top rated</option>
                        <option value="price_low" <?= $filters['sort'] === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
                        <option value="price_high" <?= $filters['sort'] === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
            </form>
        </aside>

        <div>
            <?php if (!empty($mapListings)): ?>
            <div class="panel" style="margin-bottom:1.5rem;">
                <div class="panel-head"><h3>Your bookings — map view</h3></div>
                <div class="panel-body" style="padding:0;">
                    <div id="browseMap" style="height:320px;border-radius:0 0 12px 12px;"></div>
                </div>
                <p class="hint" style="padding:.75rem 1rem;margin:0;border-top:1px solid var(--line);">Only locations you have already booked and paid for appear here.</p>
            </div>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
            (function () {
                var pins = <?= json_encode(array_values(array_map(static fn($l) => [
                    'title' => $l['title'],
                    'slug' => $l['slug'],
                    'lat' => (float) $l['latitude'],
                    'lng' => (float) $l['longitude'],
                    'price' => $l['price'],
                ], $mapListings ?? [])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
                if (!pins.length || !window.L) return;
                var map = L.map('browseMap', { attributionControl: false, maxZoom: 18 }).setView([10.3157, 123.8854], 9);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '' }).addTo(map);
                pins.forEach(function (p) {
                    L.marker([p.lat, p.lng]).addTo(map).bindPopup('<strong>' + p.title + '</strong><br><a href="<?= e(url('/listing/')) ?>' + p.slug + '">View listing</a>');
                });
            })();
            </script>
            <?php endif; ?>
            <div class="result-bar">
                <span class="count"><?= count($listings) ?> result<?= count($listings) === 1 ? '' : 's' ?> in Cebu</span>
            </div>
            <?php if ($listings === []): ?>
                <div class="empty-state">
                    <div class="big">🔍</div>
                    <h3>No listings found</h3>
                    <p>Try adjusting your filters or search for something else.</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($listings as $l): ?>
                        <?= \App\Core\View::partial('partials/listing-card', ['l' => $l, 'favIds' => $favIds]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

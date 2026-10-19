<?php
/**
 * @var string $heading @var string $sub
 * @var array<int,array<string,mixed>> $vehicles
 * @var array<string,mixed> $filters
 * @var array<int,string> $types
 * @var string $activeCategory
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
            <form method="get" action="<?= e(url('/rentals')) ?>" id="rentalFilterForm" novalidate>
                <div class="filter-group">
                    <label for="filter_q">Keyword</label>
                    <input class="input" id="filter_q" type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Make, model, features...">
                </div>

                <div class="filter-group">
                    <label for="filter_type">Vehicle Type</label>
                    <select class="input" id="filter_type" name="type">
                        <option value="">All Vehicle Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter_location">Pickup Location</label>
                    <input class="input" id="filter_location" type="text" name="location" value="<?= e($filters['location']) ?>" placeholder="e.g. IT Park, Mactan, Oslob">
                </div>

                <div class="filter-group">
                    <label for="filter_sort">Sort by</label>
                    <select class="input" id="filter_sort" name="sort">
                        <option value="newest" <?= in_array($filters['sort'], ['newest', ''], true) ? 'selected' : '' ?>>Newest listings</option>
                        <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="year_desc" <?= $filters['sort'] === 'year_desc' ? 'selected' : '' ?>>Model Year: Newest first</option>
                    </select>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
                <?php if (!empty($filters['q']) || !empty($filters['type']) || !empty($filters['location']) || !empty($filters['sort'])): ?>
                    <a href="<?= e(url('/rentals')) ?>" class="btn btn-ghost btn-block" style="margin-top:0.5rem;text-align:center;">Reset filters</a>
                <?php endif; ?>
            </form>
        </aside>

        <div>
            <div class="result-bar">
                <span class="count"><?= count($vehicles) ?> available vehicle<?= count($vehicles) === 1 ? '' : 's' ?> in Cebu</span>
            </div>

            <?php if ($vehicles === []): ?>
                <div class="empty-state">
                    <div class="big">🚗</div>
                    <h3>No vehicles available</h3>
                    <p>There are no approved vehicles matching your search criteria right now. Check back soon or try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($vehicles as $v):
                        $specs = \App\Models\Vehicle::parseSpecs($v['specs'] ?? null);
                    ?>
                        <article class="listing-card">
                            <div class="listing-media">
                                <a href="<?= e(url('/rentals/' . $v['slug'])) ?>">
                                    <img src="<?= e(img_src($v['image'] ?? null, 'vehicle-' . $v['id'])) ?>" alt="<?= e($v['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='https://picsum.photos/seed/vehicle<?= (int) $v['id'] ?>/1200/800';">
                                </a>
                                <div class="listing-badges">
                                    <span class="listing-cat"><?= e($v['type']) ?></span>
                                    <span class="listing-guide-badge">Verified Partner</span>
                                </div>
                            </div>
                            <div class="listing-body">
                                <span class="listing-area">📍 <?= e($v['location']) ?></span>
                                <h3 class="listing-title">
                                    <a href="<?= e(url('/rentals/' . $v['slug'])) ?>"><?= e($v['name']) ?></a>
                                </h3>
                                <div style="font-size:0.82rem;color:var(--text-muted);margin:0.25rem 0 0.5rem 0;">
                                    Partner: <strong><?= e($v['provider_name']) ?></strong>
                                </div>
                                <?php if ($specs !== []): ?>
                                    <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.75rem;">
                                        <?php foreach (array_slice($specs, 0, 3) as $s): ?>
                                            <span style="font-size:0.75rem;background:#f1f5f9;color:#475569;padding:0.15rem 0.45rem;border-radius:4px;">
                                                <?= e($s) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="listing-foot" style="margin-top:auto;">
                                    <span class="price">
                                        <?= money((float) $v['price_per_day']) ?> <span>per day</span>
                                    </span>
                                    <a href="<?= e(url('/rentals/' . $v['slug'])) ?>" class="btn btn-primary btn-sm">Rent Unit</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
/**
 * Admin Unified Listing Review (Guides, Hotels, Vehicles)
 *
 * @var string $currentTab ('guides'|'hotels'|'vehicles')
 * @var int $pendingGuideListings
 * @var int $pendingHotels
 * @var int $pendingVehicles
 */

$currentTab = $currentTab ?? ($_GET['tab'] ?? 'guides');
if (!in_array($currentTab, ['guides', 'hotels', 'vehicles'], true)) {
    $currentTab = 'guides';
}

$pendingGuideListings = $pendingGuideListings ?? \App\Models\Listing::pendingGuideListingsCount();
$pendingHotels = $pendingHotels ?? \App\Models\Listing::pendingHotelsCount();
$pendingVehicles = $pendingVehicles ?? \App\Models\Vehicle::pendingCount();
?>

<!-- Category Filter Pill Bar matching Reference Image 3 -->
<div class="review-tabs-container" style="margin-bottom:1.5rem;">
    <div class="review-tabs-bar" role="tablist" aria-label="Listing review categories" style="display:inline-flex;align-items:center;background:#0d1e24;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:6px;gap:6px;box-shadow:0 2px 10px rgba(0,0,0,0.2);">
        <!-- Guide Tab -->
        <a href="<?= e(url('/admin/listing-review?tab=guides')) ?>"
           class="review-tab-pill <?= $currentTab === 'guides' ? 'is-active' : '' ?>"
           role="tab"
           id="tab-btn-guides"
           aria-selected="<?= $currentTab === 'guides' ? 'true' : 'false' ?>"
           aria-controls="panel-guides"
           style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:8px;text-decoration:none;font-size:0.9rem;font-weight:700;transition:all 0.2s ease;<?= $currentTab === 'guides' ? 'background:#00875a;color:#ffffff;box-shadow:0 2px 8px rgba(0,135,90,0.4);' : 'background:transparent;color:rgba(255,255,255,0.7);' ?>">
            <span>Guide</span>
            <span class="tab-badge" style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:9999px;font-size:0.75rem;font-weight:800;<?= $currentTab === 'guides' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.65);' ?>">
                <?= (int) $pendingGuideListings ?>
            </span>
        </a>

        <!-- Hotels Tab -->
        <a href="<?= e(url('/admin/listing-review?tab=hotels')) ?>"
           class="review-tab-pill <?= $currentTab === 'hotels' ? 'is-active' : '' ?>"
           role="tab"
           id="tab-btn-hotels"
           aria-selected="<?= $currentTab === 'hotels' ? 'true' : 'false' ?>"
           aria-controls="panel-hotels"
           style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:8px;text-decoration:none;font-size:0.9rem;font-weight:700;transition:all 0.2s ease;<?= $currentTab === 'hotels' ? 'background:#00875a;color:#ffffff;box-shadow:0 2px 8px rgba(0,135,90,0.4);' : 'background:transparent;color:rgba(255,255,255,0.7);' ?>">
            <span>Hotels</span>
            <span class="tab-badge" style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:9999px;font-size:0.75rem;font-weight:800;<?= $currentTab === 'hotels' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.65);' ?>">
                <?= (int) $pendingHotels ?>
            </span>
        </a>

        <!-- Vehicles Tab -->
        <a href="<?= e(url('/admin/listing-review?tab=vehicles')) ?>"
           class="review-tab-pill <?= $currentTab === 'vehicles' ? 'is-active' : '' ?>"
           role="tab"
           id="tab-btn-vehicles"
           aria-selected="<?= $currentTab === 'vehicles' ? 'true' : 'false' ?>"
           aria-controls="panel-vehicles"
           style="display:inline-flex;align-items:center;gap:8px;padding:8px 20px;border-radius:8px;text-decoration:none;font-size:0.9rem;font-weight:700;transition:all 0.2s ease;<?= $currentTab === 'vehicles' ? 'background:#00875a;color:#ffffff;box-shadow:0 2px 8px rgba(0,135,90,0.4);' : 'background:transparent;color:rgba(255,255,255,0.7);' ?>">
            <span>Vehicles</span>
            <span class="tab-badge" style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:9999px;font-size:0.75rem;font-weight:800;<?= $currentTab === 'vehicles' ? 'background:rgba(255,255,255,0.25);color:#ffffff;' : 'background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.65);' ?>">
                <?= (int) $pendingVehicles ?>
            </span>
        </a>
    </div>
</div>

<div class="review-tab-panels">
    <?php if ($currentTab === 'hotels'): ?>
        <div id="panel-hotels" role="tabpanel" aria-labelledby="tab-btn-hotels">
            <?= \App\Core\View::partial('admin/hotels', get_defined_vars()) ?>
        </div>
    <?php elseif ($currentTab === 'vehicles'): ?>
        <div id="panel-vehicles" role="tabpanel" aria-labelledby="tab-btn-vehicles">
            <?= \App\Core\View::partial('admin/vehicles', get_defined_vars()) ?>
        </div>
    <?php else: ?>
        <div id="panel-guides" role="tabpanel" aria-labelledby="tab-btn-guides">
            <?= \App\Core\View::partial('admin/guide-listings', get_defined_vars()) ?>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 * Public Rental Vehicle Detail Page
 * Redesigned to match the Hotel and Things to Do detail page design system.
 *
 * @var array<string,mixed> $vehicle
 * @var array<int,array<string,mixed>> $gallery
 * @var array<int,string> $specs
 * @var array<int,array<string,mixed>> $reviews
 * @var array{rating:float,reviews_count:int,breakdown?:array<int,int>} $reviewStats
 * @var bool $isPreview
 * @var bool $isOwner
 */

$user = \App\Core\Auth::user();
$reviews = $reviews ?? [];
$reviewStats = $reviewStats ?? ['rating' => 0.0, 'reviews_count' => 0];
$avgRating = (float) ($reviewStats['rating'] ?? 5.0);
$reviewCount = (int) ($reviewStats['reviews_count'] ?? 0);
$isAvailable = (int) ($vehicle['is_available'] ?? 1) === 1 && ($vehicle['status'] ?? '') === 'approved';

$mainPhoto = !empty($vehicle['image']) ? $vehicle['image'] : null;
$galleryPaths = [];
foreach ($gallery ?? [] as $g) {
    if (!empty($g['image_path'])) {
        $galleryPaths[] = (string) $g['image_path'];
    }
}
$sidePhoto1 = $galleryPaths[0] ?? $mainPhoto;
$sidePhoto2 = $galleryPaths[1] ?? $galleryPaths[0] ?? $mainPhoto;
?>
<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= e(url('/')) ?>">Home</a> ›
        <a href="<?= e(url('/rentals')) ?>">Rentals</a> ›
        <span><?= e($vehicle['name']) ?></span>
    </div>

    <?php if ($isPreview): ?>
        <div class="card-soft" style="background:#fdf0d5;border-color:#f0d9a0;margin-top:1.25rem;">
            <strong>Preview mode —</strong> this vehicle rental is <em><?= e(\App\Models\Vehicle::statusLabel((string) $vehicle['status'])) ?></em> and not yet visible to the public.
            <?php if ($isOwner): ?>
                <a href="<?= e(url('/dashboard/vehicles/' . (int) $vehicle['id'] . '/edit')) ?>" style="margin-left:0.5rem;font-weight:600;color:var(--teal-700);">Edit listing details →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Media Hero (1 large on left, 2 stacked on right matching Things to Do & Hotels) -->
    <?php if (!empty($mainPhoto)): ?>
        <div class="detail-hero">
            <div class="main-img">
                <img src="<?= e(img_src($mainPhoto, 'vehicle-' . $vehicle['id'])) ?>" alt="<?= e($vehicle['name']) ?>" onerror="this.onerror=null;this.src='https://picsum.photos/seed/vehicle<?= (int) $vehicle['id'] ?>/1200/800';">
            </div>
            <div class="side">
                <img src="<?= e(img_src($sidePhoto1, 'gallery-' . $vehicle['id'] . '-0')) ?>" alt="<?= e($vehicle['name']) ?> photo 2" onerror="this.onerror=null;this.src='https://picsum.photos/seed/vehicle<?= (int) $vehicle['id'] ?>1/800/600';">
                <img src="<?= e(img_src($sidePhoto2, 'gallery-' . $vehicle['id'] . '-1')) ?>" alt="<?= e($vehicle['name']) ?> photo 3" onerror="this.onerror=null;this.src='https://picsum.photos/seed/vehicle<?= (int) $vehicle['id'] ?>2/800/600';">
            </div>
        </div>
    <?php else: ?>
        <div class="detail-hero" style="display:flex;align-items:center;justify-content:center;background:#0f172a;min-height:260px;border-radius:var(--radius-lg);color:#94a3b8;flex-direction:column;gap:8px;">
            <span style="font-size:3rem;">🛵</span>
            <span style="font-size:1.1rem;font-weight:600;color:#cbd5e1;">No photo uploaded</span>
        </div>
    <?php endif; ?>

    <?php if (count($galleryPaths) > 2): ?>
        <div style="display:flex;gap:0.6rem;margin-top:0.75rem;overflow-x:auto;padding-bottom:4px;">
            <?php foreach ($galleryPaths as $idx => $gPath): ?>
                <a href="<?= e(url($gPath)) ?>" target="_blank" rel="noopener" style="flex-shrink:0;">
                    <img src="<?= e(url($gPath)) ?>" alt="Photo <?= $idx + 1 ?>" style="width:104px;height:72px;object-fit:cover;border-radius:8px;border:1.5px solid var(--line);">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Two-column Layout matching Hotel and Things to Do screens -->
    <div class="detail-layout">
        <!-- Left: Vehicle Information, Overview, Specs, Inclusions, and Reviews -->
        <div>
            <span class="chip"><?= e($vehicle['type']) ?></span>
            <h1 class="detail-title mt-2"><?= e($vehicle['name']) ?></h1>

            <div class="detail-meta">
                <span class="rating">
                    <?= e(stars($avgRating > 0 ? $avgRating : 5.0)) ?>
                    <strong><?= number_format($avgRating > 0 ? $avgRating : 5.0, 1) ?></strong>
                    <span class="count">(<?= $reviewCount ?> <?= $reviewCount === 1 ? 'review' : 'reviews' ?>)</span>
                </span>
                <span>📍 <?= e($vehicle['location']) ?></span>
                <span>⏱️ <?= (int) $vehicle['year'] ?> Model · <?= e($vehicle['make']) ?></span>
                <span class="chip" style="background:#E0F2FE;color:#0369A1;font-weight:600;font-size:0.85rem;">🛡️ Verified Partner</span>
            </div>

            <!-- Host Presence & Address -->
            <div class="chips">
                <?php $isHostOnline = \App\Models\User::isOnline((int) $vehicle['user_id']); ?>
                <span class="chip chip-host">
                    <span class="presence-dot <?= $isHostOnline ? 'online' : 'offline' ?>"></span>
                    Hosted by <?= e($vehicle['provider_name']) ?> · <span class="presence-text"><?= $isHostOnline ? 'Online' : 'Offline' ?></span>
                </span>
                <?php if (!empty($vehicle['address'])): ?>
                    <span class="chip">🗺️ <?= e($vehicle['address']) ?></span>
                <?php endif; ?>
            </div>

            <!-- About this rental -->
            <h2 class="mt-3">About this rental</h2>
            <div class="prose">
                <?php if (!empty(trim((string) $vehicle['description']))): ?>
                    <p><?= nl2br(e($vehicle['description'])) ?></p>
                <?php else: ?>
                    <p>Rent this <?= e($vehicle['name']) ?> for your travels in <?= e($vehicle['location']) ?>, Cebu. Well-maintained and inspected for optimal road safety and comfortable exploration.</p>
                <?php endif; ?>
            </div>

            <!-- Inclusions and Specifications Box -->
            <div class="inclusion-box mt-3">
                <div class="inclusion-col">
                    <h3>What's included</h3>
                    <ul class="inclusion-list">
                        <?php if (!empty($specs)): ?>
                            <?php foreach ($specs as $s): ?>
                                <li><?= e($s) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>Clean, inspected, and road-ready unit</li>
                            <li>Standard protective safety helmets</li>
                            <li>Third-party liability protection</li>
                            <li>24/7 Cebu roadside customer assistance</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="inclusion-col">
                    <h3>Available Add-ons &amp; Services</h3>
                    <ul class="inclusion-list inclusion-list-muted">
                        <li><strong>Vehicle:</strong> <?= e($vehicle['make']) ?> <?= e($vehicle['model']) ?> (<?= (int) $vehicle['year'] ?>)</li>
                        <li><strong>Category:</strong> <?= e($vehicle['type']) ?></li>
                        <li><strong>License:</strong> Valid Philippine or International Driver's License</li>
                        <li><strong>Security:</strong> Government ID or passport held during rental</li>
                        <li><strong>Fuel Policy:</strong> Same-to-same fuel level on return</li>
                    </ul>
                </div>
            </div>
            <p class="hint mt-2">
                All rental rates are agreed directly with verified Cebu rental operators. Inspect the vehicle condition during handover and always observe safe driving habits. <a href="<?= e(url('/policy')) ?>">Read our policy</a>
            </p>

            <!-- Tourist Reviews Section -->
            <h2 class="mt-4" id="reviews">Tourist Reviews</h2>
            <?php if ($reviewCount > 0): ?>
                <div class="review-summary">
                    <div class="text-center">
                        <div class="big-score"><?= number_format($avgRating, 1) ?></div>
                        <div class="rating"><?= e(stars($avgRating)) ?></div>
                        <div class="hint"><?= $reviewCount ?> <?= $reviewCount === 1 ? 'verified review' : 'verified reviews' ?></div>
                    </div>
                    <div>
                        <?php
                        $breakdown = $reviewStats['breakdown'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                        foreach ([5, 4, 3, 2, 1] as $star):
                            $count = (int) ($breakdown[$star] ?? 0);
                            $pct = $reviewCount > 0 ? round(($count / $reviewCount) * 100) : 0;
                        ?>
                            <div class="bar-row">
                                <span><?= $star ?> star</span>
                                <span class="bar"><i style="width:<?= $pct ?>%"></i></span>
                                <span><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="review-list mt-3">
                <?php if (empty($reviews)): ?>
                    <p class="hint">No tourist reviews yet. Be the first tourist to rent this unit and share your experience!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $r):
                        $rAvatar = $r['reviewer_avatar'] ?? null;
                        $rName = (string) ($r['reviewer_name'] ?? 'Tourist');
                        $rDate = !empty($r['created_at']) ? date('M j, Y', strtotime((string) $r['created_at'])) : '';
                        $rAgo = !empty($r['created_at']) ? time_ago((string) $r['created_at']) : '';
                        $rText = (string) ($r['feedback'] ?? $r['comment'] ?? '');
                    ?>
                        <div class="review-card">
                            <div class="review-head">
                                <img src="<?= e(img_src($rAvatar, 'avatar' . (int) ($r['reviewer_id'] ?? 0))) ?>" alt="<?= e($rName) ?>" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($rName) ?>&background=e2e8f0&color=475569';">
                                <div class="meta">
                                    <strong><?= e($rName) ?></strong><br>
                                    <small>
                                        <?php if ($rDate !== ''): ?>
                                            <?= e($rDate) ?> (<?= e($rAgo) ?>)
                                        <?php else: ?>
                                            Verified Tourist
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="rating" style="margin-left:auto;"><?= e(stars((float) ($r['rating'] ?? 5))) ?></span>
                            </div>
                            <?php if (!empty($r['title'])): ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
                            <p class="mb-0"><?= nl2br(e($rText)) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Sticky Booking Box matching Things to Do & Hotels -->
        <aside>
            <div class="booking-box" id="bookingBox">
                <div class="price">
                    <span><?= money((float) $vehicle['price_per_day']) ?></span>
                    <span class="price-unit">/ per day</span>
                </div>

                <div class="guide-mini">
                    <img src="<?= e(img_src($vehicle['provider_avatar'] ?? null, 'avatar' . (int) $vehicle['user_id'])) ?>" alt="<?= e($vehicle['provider_name']) ?>" class="guide-mini-avatar" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($vehicle['provider_name']) ?>&background=059669&color=fff';">
                    <div class="guide-mini-meta">
                        <span class="guide-mini-label">Your host</span>
                        <strong class="guide-mini-name"><?= e($vehicle['provider_name']) ?></strong>
                    </div>
                </div>

                <div style="margin-bottom:0.75rem;">
                    <?php if ($isAvailable): ?>
                        <span class="badge" style="background:#DCFCE7;color:#166534;border:1px solid #86EFAC;font-weight:600;font-size:0.8rem;padding:3px 8px;border-radius:6px;display:inline-block;">🟢 Available for Rent</span>
                    <?php else: ?>
                        <span class="badge" style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-weight:600;font-size:0.8rem;padding:3px 8px;border-radius:6px;display:inline-block;">🟡 Currently Inactive / Reserved</span>
                    <?php endif; ?>
                </div>

                <p class="hint" style="margin: 0 0 1.15rem; font-size: 0.9rem; color: var(--ink-600); line-height: 1.45;">
                    Inquire with the host for availability.
                </p>

                <?php if ($isOwner): ?>
                    <p class="hint">This is your vehicle listing.</p>
                    <a href="<?= e(url('/dashboard/vehicles/' . (int) $vehicle['id'] . '/edit')) ?>" class="btn btn-ghost btn-block">Edit listing</a>
                <?php else: ?>
                    <?php if (\App\Core\Auth::check()): ?>
                        <form method="post" action="<?= e(url('/messages')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="receiver_id" value="<?= (int) $vehicle['user_id'] ?>">
                            <input type="hidden" name="body" value="Hi! I am interested in renting your <?= e($vehicle['name']) ?> (<?= money((float) $vehicle['price_per_day']) ?>/day). Is this available?">
                            <button type="submit" class="btn btn-primary btn-block btn-lg" style="display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                                <span>💬</span>
                                <span>Message the host</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/login?redirect=' . rawurlencode('/rentals/' . ($vehicle['slug'] ?? $vehicle['id'])))) ?>" style="display:flex;align-items:center;justify-content:center;gap:0.5rem;">
                            <span>💬</span>
                            <span>Message the host</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<div style="height:3rem;"></div>

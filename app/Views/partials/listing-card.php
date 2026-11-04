<?php
/**
 * Reusable listing card.
 * @var array<string,mixed> $l
 * @var array<int,int> $favIds
 */
$favIds = $favIds ?? [];
$isFav = in_array((int) $l['id'], $favIds, true);
$rating = (float) ($l['avg_rating'] ?? 0);
$reviewCount = (int) ($l['review_count'] ?? 0);
$displayPrice = \App\Models\Listing::effectivePrice($l);
$badge = guide_badge((int) ($l['user_id'] ?? 0));
$isHotel = (($l['category_slug'] ?? '') === 'hotels') || (strtolower((string) ($l['category_name'] ?? '')) === 'hotels') || ((int) ($l['category_id'] ?? 0) === 3);
?>
<article class="listing-card">
    <div class="listing-media">
        <a href="<?= e(url('/listing/' . $l['slug'])) ?>">
            <img src="<?= e(img_src($l['cover_image'] ?? null, 'listing' . $l['id'])) ?>" alt="<?= e($l['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='https://picsum.photos/seed/listing<?= (int) $l['id'] ?>/1200/800';">
        </a>
        <div class="listing-badges">
            <span class="listing-cat"><?= e($l['category_name'] ?? 'Listing') ?></span>
            <?php if ($badge !== ''): ?><span class="listing-guide-badge"><?= e($badge) ?></span><?php endif; ?>
        </div>
        <button class="fav-btn <?= $isFav ? 'is-fav' : '' ?>"
                data-id="<?= (int) $l['id'] ?>"
                data-url="<?= e(url('/favorites/toggle')) ?>"
                data-token="<?= e(\App\Core\Auth::csrfToken()) ?>"
                aria-label="Save"><?= $isFav ? '♥' : '♡' ?></button>
    </div>
    <div class="listing-body">
        <span class="listing-area">📍 <?= e($l['area']) ?></span>
        <h3 class="listing-title"><a href="<?= e(url('/listing/' . $l['slug'])) ?>"><?= e($l['title']) ?></a></h3>
        <p class="listing-summary"><?= e($l['summary'] ?? '') ?></p>
        <div class="listing-foot">
            <span class="rating">
                <span class="stars-inline"><?= $rating > 0 ? e(stars($rating)) : '☆☆☆☆☆' ?></span>
                <?php if ($reviewCount > 0): ?>
                    <?= number_format($rating, 1) ?> <span class="count">(<?= $reviewCount ?>)</span>
                <?php else: ?>
                    <span class="count">New</span>
                <?php endif; ?>
            </span>
            <?php if ($isHotel): ?>
                <?php if ($displayPrice > 0): ?>
                    <span class="price">
                        <span class="price-prefix" style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-muted,#64748B);line-height:1.2;">Lowest price room</span>
                        <?= money($displayPrice) ?> <span class="price-unit">/ per night</span>
                    </span>
                <?php else: ?>
                    <span class="price" style="font-size:0.95rem;color:var(--text-muted,#64748B);font-weight:600;">Price unavailable</span>
                <?php endif; ?>
            <?php elseif (($l['category_slug'] ?? '') === 'packages'): ?>
                <span class="price"><?= money($displayPrice) ?> <span class="price-unit">Package Price</span></span>
            <?php else: ?>
                <span class="price"><?= money($displayPrice) ?> <span><?= e($l['price_unit']) ?></span></span>
            <?php endif; ?>
        </div>
    </div>
</article>

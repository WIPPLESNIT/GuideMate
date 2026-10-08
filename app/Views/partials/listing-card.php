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
            <span class="price"><?= money($displayPrice) ?> <span><?= e($l['price_unit']) ?></span></span>
        </div>
    </div>
</article>

<?php
/**
 * @var array<string,mixed> $user
 * @var array<int,array<string,mixed>> $reviews
 * @var array{avg:float,count:int} $summary
 */
$summary = $summary ?? ['avg' => 0.0, 'count' => 0];
?>
<div class="container">
    <div class="dash-layout">
        <?= \App\Core\View::partial('partials/dash-nav') ?>
        <div class="dash-main">
            <div class="section-head" style="margin-bottom:1.25rem;">
                <div>
                    <h1 class="mb-0"><?= e(__('d_reviews_title', 'Tourist Reviews')) ?></h1>
                    <p class="hint mb-0"><?= e(__('d_reviews_subtitle', 'Comments and ratings left by tourists on your listings.')) ?></p>
                </div>
            </div>

            <?php if ((int) $summary['count'] > 0): ?>
                <div class="review-summary" style="margin-bottom:1.5rem;">
                    <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                        <div class="text-center" style="min-width:110px;">
                            <div class="big-score" style="font-size:2.8rem;line-height:1;font-weight:800;color:var(--text, #0f172a);"><?= number_format((float) $summary['avg'], 1) ?></div>
                            <div class="rating" style="margin-top:0.25rem;color:var(--gold, #f59e0b);"><?= e(stars((float) $summary['avg'])) ?></div>
                            <div class="hint" style="margin-top:0.15rem;font-size:0.85rem;"><?= (int) $summary['count'] ?> <?= (int) $summary['count'] === 1 ? 'review' : 'reviews' ?></div>
                        </div>
                        <div style="border-left:1px solid var(--line, #e2e8f0);padding-left:1.5rem;flex:1 1 200px;">
                            <h4 style="margin:0 0 0.25rem 0;font-size:1rem;color:var(--text, #0f172a);">Partner Quality Rating</h4>
                            <p class="hint mb-0" style="font-size:0.85rem;line-height:1.45;">Overall score calculated from verified tourist feedback on your vehicles and completed bookings.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($reviews === []): ?>
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state" style="padding:3rem 1rem;text-align:center;">
                            <div style="font-size:2.5rem;margin-bottom:0.5rem;">⭐</div>
                            <h3 style="font-size:1.15rem;margin:0 0 0.35rem 0;"><?= e(__('d_reviews_empty', 'No tourist reviews yet.')) ?></h3>
                            <p class="hint mb-0" style="font-size:0.9rem;">Reviews and ratings from completed bookings will appear here.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="reviews-grid">
                    <?php foreach ($reviews as $r):
                        $reviewerName = (string) ($r['reviewer_name'] ?? $r['user_name'] ?? 'Tourist');
                        $reviewerAvatar = $r['reviewer_avatar'] ?? $r['user_avatar'] ?? null;
                        $reviewerId = (int) ($r['reviewer_id'] ?? $r['user_id'] ?? 0);
                        $targetTitle = (string) ($r['vehicle_name'] ?? $r['listing_title'] ?? '');
                        $targetUrl = !empty($r['vehicle_slug'])
                            ? url('/rentals/' . $r['vehicle_slug'])
                            : (!empty($r['listing_slug']) ? url('/listing/' . $r['listing_slug']) : '');
                        $feedbackText = (string) ($r['feedback'] ?? $r['comment'] ?? '');
                        $ratingVal = (float) ($r['rating'] ?? 5.0);
                        $pickupDate = !empty($r['pickup_date']) ? date('M j, Y', strtotime((string) $r['pickup_date'])) : '';
                        $isVehicleReview = !empty($r['vehicle_name']) || !empty($r['vehicle_slug']);
                    ?>
                        <div class="review-card">
                            <div class="review-card-head">
                                <div class="review-card-author">
                                    <img src="<?= e(img_src($reviewerAvatar, 'avatar' . $reviewerId)) ?>" alt="" class="review-card-avatar">
                                    <div class="review-card-author-info">
                                        <strong class="review-card-name" title="<?= e($reviewerName) ?>"><?= e($reviewerName) ?></strong>
                                        <span class="review-card-time"><?= e(time_ago((string) $r['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="review-card-rating">
                                    <span class="review-card-stars"><?= e(stars($ratingVal)) ?></span>
                                    <span class="review-card-score"><?= number_format($ratingVal, 1) ?></span>
                                </div>
                            </div>

                            <?php if ($targetTitle !== ''): ?>
                                <div class="review-card-target">
                                    <span class="target-icon"><?= $isVehicleReview ? '🚗' : '📍' ?></span>
                                    <?php if ($targetUrl !== ''): ?>
                                        <a href="<?= e($targetUrl) ?>" target="_blank" rel="noopener" class="target-link"><?= e($targetTitle) ?></a>
                                    <?php else: ?>
                                        <span class="target-text"><?= e($targetTitle) ?></span>
                                    <?php endif; ?>
                                    <?php if ($pickupDate !== ''): ?>
                                        <span class="target-date">· Picked up <?= e($pickupDate) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($r['title'])): ?>
                                <div class="review-card-title"><?= e($r['title']) ?></div>
                            <?php endif; ?>

                            <div class="review-card-comment">
                                <?= nl2br(e($feedbackText)) ?>
                            </div>

                            <?php if (!empty($r['images'])): ?>
                                <div class="review-card-photos">
                                    <?php foreach ($r['images'] as $img): ?>
                                        <a href="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" target="_blank" rel="noopener" class="review-card-photo-link">
                                            <img src="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" alt="Photo from tourist" class="review-card-photo">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

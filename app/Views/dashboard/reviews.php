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
        <div>
            <div class="section-head" style="margin-bottom:1rem;">
                <div>
                    <h1 class="mb-0"><?= e(__('d_reviews_title', 'Tourist reviews')) ?></h1>
                    <p class="hint mb-0"><?= e(__('d_reviews_subtitle', 'Comments and ratings left by tourists on your listings.')) ?></p>
                </div>
            </div>

            <?php if ((int) $summary['count'] > 0): ?>
                <div class="review-summary" style="margin-bottom:1.2rem;">
                    <div class="text-center">
                        <div class="big-score"><?= number_format((float) $summary['avg'], 1) ?></div>
                        <div class="rating"><?= e(stars((float) $summary['avg'])) ?></div>
                        <div class="hint"><?= (int) $summary['count'] ?> <?= (int) $summary['count'] === 1 ? 'review' : 'reviews' ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($reviews === []): ?>
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state" style="padding:2.5rem 1rem;">
                            <p><?= e(__('d_reviews_empty', 'No tourist reviews yet.')) ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review">
                        <div class="review-head">
                            <img src="<?= e(img_src($r['user_avatar'] ?? null, 'avatar' . $r['user_id'])) ?>" alt="">
                            <div class="meta">
                                <strong><?= e($r['user_name']) ?></strong><br>
                                <small>
                                    <?= e(time_ago((string) $r['created_at'])) ?>
                                    · <a href="<?= e(url('/listing/' . $r['listing_slug'])) ?>"><?= e($r['listing_title']) ?></a>
                                </small>
                            </div>
                            <span class="rating" style="margin-left:auto;"><?= e(stars((float) $r['rating'])) ?></span>
                        </div>
                        <?php if (!empty($r['title'])): ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
                        <p class="mb-0"><?= nl2br(e((string) $r['comment'])) ?></p>
                        <?php if (!empty($r['images'])): ?>
                            <div class="review-photos" style="display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap;">
                                <?php foreach ($r['images'] as $img): ?>
                                    <a href="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" target="_blank" rel="noopener">
                                        <img src="<?= e(url('/' . ltrim((string) $img['file_path'], '/'))) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

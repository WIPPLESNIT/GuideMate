<?php
/**
 * @var string $heading
 * @var string $sub
 * @var array<int, array<string, mixed>> $guides
 */
$guides = $guides ?? [];
$user = auth_user();
?>
<section class="page-head">
    <div class="container">
        <h1><?= e($heading) ?></h1>
        <p><?= e($sub) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($guides === []): ?>
            <div class="empty-state">
                <div class="big">🧑‍🏫</div>
                <h3>No tour guides yet</h3>
                <p>Approved local guides will appear here, ready to message — same as in the app.</p>
            </div>
        <?php else: ?>
            <div class="result-bar">
                <span class="count"><?= count($guides) ?> guide<?= count($guides) === 1 ? '' : 's' ?> in Cebu</span>
            </div>
            <div class="guide-dir">
                <?php foreach ($guides as $g):
                    $available = !empty($g['available']);
                    $rating = (float) ($g['rating'] ?? 0);
                    $reviews = (int) ($g['review_count'] ?? 0);
                    $tours = (int) ($g['completed_tours'] ?? 0);
                    $loginUrl = url('/login?redirect=' . rawurlencode('/tour-guides'));
                    ?>
                    <article class="guide-dir-card">
                        <div class="guide-dir-avatar">
                            <img src="<?= e(img_src($g['avatar'] ?? null, 'avatar' . $g['id'])) ?>" alt="">
                            <?php if (!empty($g['online'])): ?><span class="guide-dir-online" title="Online"></span><?php endif; ?>
                        </div>
                        <div class="guide-dir-info">
                            <h3><?= e((string) $g['name']) ?></h3>
                            <span class="guide-dir-avail <?= $available ? 'is-free' : 'is-busy' ?>">
                                <i></i><?= $available ? 'Available' : 'On a tour' ?>
                            </span>
                            <p class="guide-dir-meta">
                                ⭐ <?= $rating > 0 ? number_format($rating, 1) : 'New' ?><?= $reviews > 0 ? ' (' . $reviews . ')' : '' ?>
                                · <?= $tours ?> tour<?= $tours === 1 ? '' : 's' ?>
                            </p>
                            <?php if (!empty($g['bio'])): ?>
                                <p class="listing-summary mb-0"><?= e(mb_strimwidth((string) $g['bio'], 0, 140, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($user): ?>
                            <form method="post" action="<?= e(url('/guides/' . (int) $g['id'] . '/contact')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary" type="submit">💬 Message</button>
                            </form>
                        <?php else: ?>
                            <a class="btn btn-primary" href="<?= e($loginUrl) ?>">💬 Message</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

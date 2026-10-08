<?php
/** @var string $areaName @var string $tagline @var array<int,array<string,mixed>> $listings @var array<int,int> $favIds */
?>
<section class="page-head">
    <div class="container">
        <div class="eyebrow">Explore Cebu</div>
        <h1><?= e($areaName) ?></h1>
        <p><?= e($tagline) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($listings === []): ?>
            <div class="empty-state">
                <h3>No listings in this area yet</h3>
                <p>Check back soon or <a href="<?= e(url('/listings')) ?>">browse all of Cebu</a>.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($listings as $l): ?>
                    <?= \App\Core\View::partial('partials/listing-card', ['l' => $l, 'favIds' => $favIds]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

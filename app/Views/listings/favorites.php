<?php
/** @var array<int,array<string,mixed>> $listings @var array<int,int> $favIds */
?>
<section class="page-head">
    <div class="container"><h1>Saved listings</h1><p>Your shortlist of Cebu experiences.</p></div>
</section>
<section class="section">
    <div class="container">
        <?php if ($listings === []): ?>
            <div class="empty-state">
                <div class="big">♡</div>
                <h3>Nothing saved yet</h3>
                <p>Tap the heart on any listing to save it here for later.</p>
                <a href="<?= e(url('/listings')) ?>" class="btn btn-primary mt-2">Explore Cebu</a>
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

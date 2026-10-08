<?php
/**
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $featured
 * @var array<int,array<string,mixed>> $recommended
 * @var string $recommendedTitle
 * @var array<int,array<string,mixed>> $recentReviews
 * @var array<int,int> $favIds
 */
$icons = ['things-to-do' => '🧭', 'tour-guides' => '🧑', 'hotels' => '🏨'];
$heroVideo = $heroVideo ?? [];
$heroPoster = (string) ($heroVideo['poster'] ?? '');
$hero4k = (string) ($heroVideo['video_4k'] ?? '');
$heroHd = (string) ($heroVideo['video_hd'] ?? $hero4k);
?>
<style>
.hero .search-bar {
    color: var(--ink-900, #0e1a13);
    align-items: center;
}
.hero .search-bar input {
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.45rem 0.7rem;
    background: #ffffff;
    margin-top: 0.25rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.hero .search-bar input:focus {
    border-color: var(--coral-500, #1faa4e);
    box-shadow: 0 0 0 3px rgba(31, 170, 78, 0.18);
    outline: none;
}
.hero .search-bar input::placeholder {
    color: var(--ink-500, #5a6b60);
    opacity: 1;
    font-weight: 400;
}
.hero .search-bar input::-webkit-input-placeholder {
    color: var(--ink-500, #5a6b60);
    opacity: 1;
    font-weight: 400;
}
.hero .search-bar input::-moz-placeholder {
    color: var(--ink-500, #5a6b60);
    opacity: 1;
    font-weight: 400;
}
.hero .search-bar input:-ms-input-placeholder {
    color: var(--ink-500, #5a6b60);
    opacity: 1;
    font-weight: 400;
}
</style>
<section class="hero">
    <div class="hero-media" aria-hidden="true">
        <?php if ($hero4k !== '' || $heroHd !== ''): ?>
            <?php $heroSrc = $heroHd !== '' ? $heroHd : $hero4k; ?>
            <video class="hero-video" id="heroVideo"
                   autoplay muted loop playsinline webkit-playsinline
                   preload="auto"
                   disablepictureinpicture
                   disableremoteplayback
                   <?= $heroPoster !== '' ? 'poster="' . e($heroPoster) . '"' : '' ?>>
                <source src="<?= e($heroSrc) ?>" type="video/mp4">
            </video>
        <?php endif; ?>
        <div class="hero-overlay"></div>
    </div>
    <div class="container hero-content">
        <span class="hero-eyebrow">🌴 Discover the Queen City of the South</span>
        <h1>Explore the best of <span class="accent">Cebu</span>, one mate at a time.</h1>
        <p class="hero-sub">Find top-rated tours, trusted local guides and comfy stays — all across Cebu, Philippines.</p>

        <form class="search-bar" action="<?= e(url('/things-to-do')) ?>" method="get">
            <div class="field">
                <div style="flex:1;">
                    <label>What are you looking for?</label>
                    <input type="text" name="q" placeholder="e.g. canyoneering">
                </div>
            </div>
            <div class="field">
                <div style="flex:1;">
                    <label>Area in Cebu</label>
                    <input type="text" name="area" placeholder="e.g. Moalboal">
                </div>
            </div>
            <button class="btn btn-primary btn-lg" type="submit">Search</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Browse by category</div>
                <h2>What's your kind of trip?</h2>
            </div>
        </div>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
                <a class="cat-card" href="<?= e(url('/' . $cat['slug'])) ?>">
                    <div class="cat-ico"><?= $icons[$cat['slug']] ?? '📌' ?></div>
                    <h3><?= e($cat['name']) ?></h3>
                    <p class="hint mb-0"><?= e($cat['description']) ?></p>
                    <div class="count mt-1"><?= (int) $cat['listing_count'] ?> listings</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($recommended)): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">For you</div>
                <h2><?= e($recommendedTitle ?? 'Recommended for you') ?></h2>
            </div>
            <a href="<?= e(url('/things-to-do')) ?>" class="btn btn-ghost">Explore all</a>
        </div>
        <div class="card-grid">
            <?php foreach ($recommended as $l): ?>
                <?= \App\Core\View::partial('partials/listing-card', ['l' => $l, 'favIds' => $favIds]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="background:var(--sand-200);">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Handpicked for you</div>
                <h2>Featured experiences in Cebu</h2>
            </div>
            <a href="<?= e(url('/listings')) ?>" class="btn btn-ghost">View all</a>
        </div>
        <?php if ($featured === []): ?>
            <div class="empty-state"><div class="big">🗺️</div><p>No featured listings yet. Run the seeder to load Cebu sample data.</p></div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($featured as $l): ?>
                    <?= \App\Core\View::partial('partials/listing-card', ['l' => $l, 'favIds' => $favIds]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($recentReviews !== []): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <div class="eyebrow">Loved by travelers</div>
                <h2>What people are saying</h2>
            </div>
        </div>
        <div class="card-grid">
            <?php foreach ($recentReviews as $r): ?>
                <div class="card-soft">
                    <div class="rating" style="font-size:1.1rem;"><?= e(stars((float) $r['rating'])) ?></div>
                    <h3 style="margin:.6rem 0 .3rem;font-size:1.05rem;"><?= e($r['title'] ?: 'Great experience') ?></h3>
                    <p class="listing-summary">"<?= e(mb_strimwidth($r['comment'], 0, 140, '…')) ?>"</p>
                    <p class="hint mb-0">— <?= e($r['user_name']) ?> on
                        <a href="<?= e(url('/listing/' . $r['listing_slug'])) ?>"><?= e($r['listing_title']) ?></a></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="cta-band">
            <h2>Are you a Cebu local guide?</h2>
            <p>List your tours and services on GuideMate, reach travelers from around the world, and grow your business.</p>
            <a href="<?= e(url('/register?role=guide')) ?>" class="btn btn-dark btn-lg">Become a guide</a>
        </div>
    </div>
</section>

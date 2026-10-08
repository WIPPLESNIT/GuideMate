<?php
/** @var string $content */
$pageTitle = $title ?? 'Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e("$pageTitle · GuideMate") ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/logo-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<?php foreach (['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info'] as $key => $cls): ?>
    <?php if ($msg = flash($key)): ?>
        <div class="flash <?= $cls ?>"><div class="container"><?= e($msg) ?><button class="flash-close">&times;</button></div></div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="auth-wrap">
    <aside class="auth-aside">
        <div class="auth-aside-inner">
            <div class="auth-aside-top">
                <a href="<?= e(url('/')) ?>" class="brand brand-light a-rise" style="animation-delay:.05s;">
                    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
                </a>
                <span class="auth-eyebrow a-rise" style="animation-delay:.15s;">🌴 Explore Cebu with GuideMate</span>
                <h2 class="a-rise" style="animation-delay:.25s;">Your local companion for discovering Cebu.</h2>
                <p class="a-rise" style="opacity:.9;animation-delay:.32s;">Tours, guides, stays and food — booked and reviewed by real travelers.</p>
                <ul class="auth-features">
                    <li class="auth-feature a-rise" style="animation-delay:.4s;"><span class="tick">✓</span> Verified local Cebuano guides</li>
                    <li class="auth-feature a-rise" style="animation-delay:.48s;"><span class="tick">✓</span> Secure booking &amp; checkout</li>
                    <li class="auth-feature a-rise" style="animation-delay:.56s;"><span class="tick">✓</span> Real traveler reviews &amp; ratings</li>
                </ul>
            </div>

            <div class="auth-aside-bottom a-rise" style="animation-delay:.64s;">
                <p class="auth-spots-label">Popular right now in Cebu</p>
                <div class="auth-chips">
                    <span class="auth-chip">🌊 Kawasan Falls</span>
                    <span class="auth-chip">🐋 Oslob Whale Sharks</span>
                    <span class="auth-chip">🏝️ Bantayan Island</span>
                    <span class="auth-chip">⛪ Magellan&rsquo;s Cross</span>
                    <span class="auth-chip">🏛️ Temple of Leah</span>
                    <span class="auth-chip">🥘 Lechon food trips</span>
                </div>
                <div class="auth-trust">
                    <span class="auth-stars">★★★★★</span> Loved by travelers exploring the Queen City of the South.
                </div>
            </div>
        </div>
    </aside>
    <div class="auth-main">
        <div class="auth-card">
            <?= $content ?>
        </div>
    </div>
</div>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>

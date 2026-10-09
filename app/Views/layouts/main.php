<?php
/** @var string $content */
$user = auth_user();
$pageTitle = $title ?? null;
$isGuide = ($user['role'] ?? null) === 'guide';
// Any service provider (guide, hotel partner, rental partner) works from their
// own dashboard, so the tourist-facing category links are hidden for them.
$isProvider = $user !== null && \App\Models\User::isProviderRole((string) ($user['role'] ?? ''));

// Work out the current path (base-path aware) to tell public browse pages apart
// from a user's own account area (dashboard, bookings, profile, ...).
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = \App\Core\App::basePath();
if ($base !== '' && str_starts_with($current, $base)) {
    $current = substr($current, strlen($base)) ?: '/';
}
$current = '/' . trim($current, '/');
$publicBrowse = $current === '/'
    || in_array($current, ['/listings', '/things-to-do', '/tour-guides', '/hotels'], true)
    || str_starts_with($current, '/listing/');
$isMessages = str_starts_with($current, '/messages');
$isDashboard = str_starts_with($current, '/dashboard') || str_starts_with($current, '/profile');
$hideFooter = $isMessages;
$guideWarned = $user && ($user['role'] ?? null) === 'guide' && \App\Models\User::isGuideWarned((int) $user['id']);

// Guides have their own workspace; on the public tourist-facing browse pages
// they appear as an anonymous visitor so their account never shows "connected"
// there. On their own pages (dashboard, etc.) their account shows normally.
$headerUser = ($isGuide && $publicBrowse) ? null : $user;
$unread = 0;
if ($headerUser) {
    $unread = \App\Models\Message::unreadCount((int) $headerUser['id']);
}
$currentLocale = $_SESSION['_locale'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?= e($currentLocale === 'tl' ? 'tl' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ? "$pageTitle · GuideMate" : 'GuideMate · Discover Cebu') ?></title>
    <meta name="description" content="GuideMate — discover the best tours, local guides and stays in Cebu, Philippines.">
    <link rel="icon" type="image/png" href="<?= e(asset('img/logo-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="<?= e(trim(($isMessages ? 'page-messages ' : '') . ($isDashboard ? 'page-dashboard ' : '') . ($hideFooter ? 'page-no-footer ' : '') . ($guideWarned ? 'guide-warned' : ''))) ?>">
<header class="site-header" id="siteHeader">
    <div class="container header-inner">
<?php if ($isGuide): ?>
            <span class="brand brand-static">
                <span class="brand-mark">◐</span>
                <span class="brand-text">Guide<strong>Mate</strong></span>
            </span>
        <?php else: ?>
            <a href="<?= e(url('/')) ?>" class="brand">
                <span class="brand-mark">◐</span>
                <span class="brand-text">Guide<strong>Mate</strong></span>
            </a>
        <?php endif; ?>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <?php if (!$isProvider): ?>
                <?php
                $navCategory = $activeCategory ?? ($listing['category_slug'] ?? ($filters['category'] ?? null));
                ?>
                <a href="<?= e(url('/things-to-do')) ?>" class="nav-item <?= active_when('/things-to-do', $navCategory === 'things-to-do') ?>"><?= __('nav_things', 'Things to Do') ?></a>
                <a href="<?= e(url('/tour-guides')) ?>" class="nav-item <?= active_when('/tour-guides', $navCategory === 'tour-guides') ?>"><?= __('nav_guides', 'Tour Guides') ?></a>
                <a href="<?= e(url('/hotels')) ?>" class="nav-item <?= active_when('/hotels', $navCategory === 'hotels') ?>"><?= __('nav_hotels', 'Hotels') ?></a>
            <?php endif; ?>

            <div class="nav-spacer"></div>

            <?php if ($headerUser): ?>
                <a href="<?= e(url('/messages')) ?>" class="nav-icon" title="Messages" aria-label="Messages">
                    <?= admin_icon('messages', 22) ?><?php if ($unread > 0): ?><span class="badge"><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <div class="nav-menu">
                    <button class="nav-avatar" id="userMenuBtn">
                        <img src="<?= e(img_src($headerUser['avatar'] ?? null, 'avatar' . $headerUser['id'])) ?>" alt="">
                        <span><?= e(explode(' ', (string) $headerUser['name'])[0]) ?></span>
                    </button>
                    <div class="dropdown" id="userMenu">
                        <a href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                        <a href="<?= e(url('/bookings')) ?>">My Bookings</a>
                        <?php // Saved/favorites is a tourist feature — hidden for providers. ?>
                        <?php if (!$isProvider): ?>
                            <a href="<?= e(url('/favorites')) ?>">Saved</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/profile')) ?>">Profile</a>
                        <form method="post" action="<?= e(url('/logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="link-button">Log out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>" class="btn btn-ghost btn-sm">Log in</a>
                <a href="<?= e(url('/register')) ?>" class="btn btn-primary btn-sm">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php foreach (['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info'] as $key => $cls): ?>
    <?php if ($msg = flash($key)): ?>
        <div class="flash <?= $cls ?>" role="alert">
            <div class="container"><?= e($msg) ?><button class="flash-close" aria-label="Dismiss">&times;</button></div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<main>
    <?= $content ?>
</main>

<?php if (!$hideFooter): ?>
<footer class="site-footer<?= $isDashboard ? ' site-footer--dash' : '' ?>">
    <div class="container footer-grid">
        <div>
            <?php if ($isGuide): ?>
                <span class="brand brand-static">
                    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
                </span>
            <?php else: ?>
                <a href="<?= e(url('/')) ?>" class="brand">
                    <span class="brand-mark">◐</span><span class="brand-text">Guide<strong>Mate</strong></span>
                </a>
            <?php endif; ?>
            <p class="footer-tag">Your local companion for discovering the best of <strong>Cebu</strong> — tours, guides and stays.</p>
        </div>
        <div>
            <h4>Explore</h4>
            <a href="<?= e(url('/things-to-do')) ?>">Things to Do</a>
            <a href="<?= e(url('/tour-guides')) ?>">Tour Guides</a>
            <a href="<?= e(url('/hotels')) ?>">Hotels &amp; Stays</a>
        </div>
        <div>
            <h4>Account</h4>
            <a href="<?= e(url('/login')) ?>">Log in</a>
            <a href="<?= e(url('/register')) ?>">Sign up</a>
            <a href="<?= e(url('/register?role=guide')) ?>">Sign up here</a>
            <a href="<?= e(url('/policy')) ?>">Platform policy</a>
        </div>
        <div>
            <h4>Cebu, Philippines</h4>
            <p class="footer-tag footer-locations">Kawasan Falls · Oslob · Moalboal · Bantayan · Mactan · Cebu City</p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">© <?= date('Y') ?> GuideMate. Made for Cebu travelers.</div>
    </div>
</footer>
<?php endif; ?>

<?php
$suspendedUser = $suspendedUser ?? $_SESSION['suspended_user'] ?? null;
unset($_SESSION['suspended_user']);
require BASE_DIR . '/app/Views/partials/suspension_modal.php';
?>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>

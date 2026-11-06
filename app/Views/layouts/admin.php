<?php
/** @var string $content */
$pageTitle = $title ?? 'Admin';
$adminUser = \App\Core\AdminAuth::user() ?? \App\Core\Auth::user() ?? \App\Models\User::adminAccount();
$adminId = $adminUser ? (int) $adminUser['id'] : 0;
$adminUnread = $adminId > 0 ? \App\Models\Message::unreadCount($adminId) : 0;
$adminName = $adminUser ? explode(' ', (string) ($adminUser['name'] ?? 'Admin'))[0] : 'Admin';
$adminAvatar = $adminUser ? img_src($adminUser['avatar'] ?? null, 'avatar' . ($adminUser['id'] ?? 1)) : asset('img/default-avatar.svg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e("$pageTitle · GuideMate Admin") ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/logo-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>window.APP_BASE_URL = <?= json_encode(rtrim(url('/'), '/')) ?>;</script>
    <?php if ($adminUser !== null): ?>
        <meta name="auth-user-id" content="<?= (int) $adminUser['id'] ?>">
        <meta name="auth-user-role" content="<?= e((string) ($adminUser['role'] ?? 'admin')) ?>">
    <?php endif; ?>
</head>
<body class="admin-body">
<header class="admin-header" id="siteHeader">
    <div class="admin-header-inner">
        <a href="<?= e(url('/admin')) ?>" class="brand brand-light">
            <span class="brand-mark"></span><span class="brand-text">Guide<strong>Mate</strong></span>
            <span class="admin-tag">Admin</span>
        </a>
        <div class="admin-user">
            <a href="<?= e(url('/admin/messages')) ?>" class="nav-icon admin-nav-icon" title="Messages" aria-label="Messages">
                <?= admin_icon('messages', 22) ?>
                <?php if ($adminUnread > 0): ?>
                    <span class="badge"><?= (int) $adminUnread ?></span>
                <?php endif; ?>
            </a>

            <div class="nav-spacer admin-nav-spacer"></div>

            <div class="nav-menu admin-nav-menu">
                <button class="nav-avatar admin-nav-avatar" id="userMenuBtn" type="button" aria-expanded="false" aria-haspopup="true" aria-label="Admin profile menu">
                    <img src="<?= e($adminAvatar) ?>" alt="<?= e($adminName) ?>">
                    <span><?= e($adminName) ?></span>
                </button>
                <div class="dropdown" id="userMenu" role="menu">
                    <a href="<?= e(url('/admin')) ?>" role="menuitem">Dashboard</a>
                    <form method="post" action="<?= e(url('/admin/logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button" role="menuitem">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<?php foreach (['success' => 'flash-success', 'error' => 'flash-error', 'info' => 'flash-info'] as $key => $cls): ?>
    <?php if ($msg = flash($key)): ?>
        <div class="flash <?= $cls ?>"><div class="container"><?= e($msg) ?><button class="flash-close">&times;</button></div></div>
    <?php endif; ?>
<?php endforeach; ?>

<main>
    <div class="container">
        <div class="dash-layout">
            <?= \App\Core\View::partial('partials/admin-nav') ?>
            <div><?= $content ?></div>
        </div>
    </div>
</main>

<?= \App\Core\View::partial('partials/doc-preview-modal') ?>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
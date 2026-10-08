<?php
/** @var string $content */
$pageTitle = $title ?? 'Admin';
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
</head>
<body class="admin-body">
<header class="admin-header">
    <div class="admin-header-inner">
        <a href="<?= e(url('/admin')) ?>" class="brand brand-light">
            <span class="brand-mark"></span><span class="brand-text">Guide<strong>Mate</strong></span>
            <span class="admin-tag">Admin</span>
        </a>
        <div class="admin-user">
            <a href="<?= e(url('/')) ?>" target="_blank" class="btn btn-ghost btn-sm" style="color:#fff;border-color:rgba(255,255,255,.25);"><?= admin_icon('external', 15) ?>View site</a>
            <form method="post" action="<?= e(url('/admin/logout')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('logout', 15) ?>Sign out</button>
            </form>
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

<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>

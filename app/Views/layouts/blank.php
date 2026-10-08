<?php
/** @var string $content */
$pageTitle = $title ?? 'GuideMate';
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
<?= $content ?>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>

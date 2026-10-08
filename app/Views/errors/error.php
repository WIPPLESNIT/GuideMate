<?php
/** @var int $status @var string $message */
$status = $status ?? 500;
$message = $message ?? 'Something went wrong.';
$titles = [403 => 'Access denied', 404 => 'Page not found', 419 => 'Session expired', 500 => 'Server error'];
$label = $titles[$status] ?? 'Error';
?>
<section class="section">
    <div class="container text-center" style="max-width:560px;">
        <div style="font-size:5rem;font-weight:800;color:var(--coral-500);line-height:1;"><?= e((string) $status) ?></div>
        <h1><?= e($label) ?></h1>
        <p class="hint" style="font-size:1.05rem;"><?= e($message) ?></p>
        <a href="<?= e(url('/')) ?>" class="btn btn-primary btn-lg mt-2">Back to home</a>
    </div>
</section>

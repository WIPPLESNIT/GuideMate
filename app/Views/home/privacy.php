<?php /** @var string $title */ ?>
<div class="container section">
    <div class="card-soft policy-page" style="max-width:800px;margin:0 auto;">
        <h1><?= e($title ?? 'Privacy Policy') ?></h1>
        <p class="hint">Last updated: <?= date('F j, Y') ?></p>

        <h2>1. Information we collect</h2>
        <p>We collect account details (name, email, phone), booking data, messages between users, verification documents from guides, and uploaded photos.</p>

        <h2>2. How we use information</h2>
        <p>To operate bookings, payments, disputes, guide verification, and customer support for the Cebu travel platform.</p>

        <h2>3. Sharing</h2>
        <p>Booking details are shared between the tourist and the guide for the reserved experience. We do not sell personal data to third parties.</p>

        <h2>4. Storage & security</h2>
        <p>Data is stored on secured servers. Passwords are hashed. Uploads are kept on the application server with access controls.</p>

        <h2>5. Your rights</h2>
        <p>You may update your profile or request account deletion by contacting support through the platform.</p>

        <h2>6. Cookies & sessions</h2>
        <p>We use session cookies to keep you signed in and to protect forms with CSRF tokens.</p>

        <p class="mt-3"><a href="<?= e(url('/terms')) ?>">Terms of Service →</a> · <a href="<?= e(url('/policy')) ?>">Platform Policy →</a></p>
    </div>
</div>

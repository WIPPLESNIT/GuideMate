<?php /** @var string $title */ ?>
<div class="container section">
    <div class="card-soft policy-page" style="max-width:800px;margin:0 auto;">
        <h1><?= e($title ?? 'Terms of Service') ?></h1>
        <p class="hint">Last updated: <?= date('F j, Y') ?></p>

        <h2>1. Acceptance</h2>
        <p>By using GuideMate you agree to these Terms of Service and our Platform Policy.</p>

        <h2>2. Accounts</h2>
        <p>You must provide accurate information. Guides must complete verification before publishing listings.</p>

        <h2>3. Bookings & payments</h2>
        <p>All fees for included services must be disclosed in the listing. Guides must not request undeclared extra payments after a tourist has paid through GuideMate.</p>

        <h2>4. Cancellations & refunds</h2>
        <p>Refunds follow our Platform Policy and admin dispute resolution. Guide-initiated cancellations of paid bookings may trigger automatic refunds to tourists.</p>

        <h2>5. Conduct</h2>
        <p>Harassment, fraud, or unsafe behavior may result in account suspension and guide warnings.</p>

        <h2>6. Limitation of liability</h2>
        <p>GuideMate connects tourists with guides and providers in Cebu. We are not the tour operator unless explicitly stated on a listing.</p>

        <p class="mt-3"><a href="<?= e(url('/policy')) ?>">Read Platform Policy →</a> · <a href="<?= e(url('/privacy')) ?>">Privacy Policy →</a></p>
    </div>
</div>

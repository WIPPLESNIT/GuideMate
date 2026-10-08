<?php
$pendingGuides = \App\Models\User::countProvidersByStatus('pending');
$openDisputes = \App\Models\Dispute::countOpen();
$newFeedback = \App\Models\Feedback::countNew();
$adminAcct = \App\Models\User::adminAccount();
$adminUnread = $adminAcct !== null ? \App\Models\Message::unreadCount((int) $adminAcct['id']) : 0;
$warnedProviders = \App\Models\User::countWarnedProviders();
?>
<nav class="dash-nav">
    <a href="<?= e(url('/admin')) ?>" class="<?= active_when('/admin') ?>"><?= admin_icon('overview') ?><span class="nav-label">Overview</span></a>
    <a href="<?= e(url('/admin/listings')) ?>" class="<?= active_when('/admin/listings') ?>"><?= admin_icon('listings') ?><span class="nav-label">Listings</span></a>
    <a href="<?= e(url('/admin/guides')) ?>" class="<?= active_when('/admin/guides') ?>"><?= admin_icon('guides') ?><span class="nav-label">Partners</span><?php if ($pendingGuides > 0): ?><span class="badge"><?= (int) $pendingGuides ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/disputes')) ?>" class="<?= active_when('/admin/disputes') ?>"><?= admin_icon('disputes') ?><span class="nav-label">Disputes</span><?php if ($openDisputes > 0): ?><span class="badge"><?= (int) $openDisputes ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/feedback')) ?>" class="<?= active_when('/admin/feedback') ?>"><?= admin_icon('feedback') ?><span class="nav-label">Feedback</span><?php if ($newFeedback > 0): ?><span class="badge"><?= (int) $newFeedback ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/messages')) ?>" class="<?= active_when('/admin/messages') ?>"><?= admin_icon('messages') ?><span class="nav-label">Messages</span><?php if ($adminUnread > 0): ?><span class="badge"><?= (int) $adminUnread ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/analytics')) ?>" class="<?= active_when('/admin/analytics') ?>"><?= admin_icon('analytics') ?><span class="nav-label">Analytics</span></a>
    <a href="<?= e(url('/admin/users')) ?>" class="<?= active_when('/admin/users') ?>"><?= admin_icon('users') ?><span class="nav-label">Users</span><?php if ($warnedProviders > 0): ?><span class="badge"><?= (int) $warnedProviders ?></span><?php endif; ?></a>
</nav>

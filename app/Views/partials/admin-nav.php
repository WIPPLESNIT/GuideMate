<?php
$pendingGuides = \App\Models\User::countProvidersByStatus('pending');
$pendingVehicles = \App\Models\Vehicle::pendingCount();
$pendingHotels = \App\Models\Listing::pendingHotelsCount();
$pendingGuideListings = \App\Models\Listing::pendingGuideListingsCount();
$pendingListingReviews = $pendingGuideListings + $pendingHotels + $pendingVehicles;
$openDisputes = \App\Models\Dispute::countOpen();
$newFeedback = \App\Models\Feedback::countNew();
$adminAcct = \App\Models\User::adminAccount();
$adminUnread = $adminAcct !== null ? \App\Models\Message::unreadCount((int) $adminAcct['id']) : 0;
$warnedProviders = \App\Models\User::countWarnedProviders();
?>
<nav class="dash-nav">
    <a href="<?= e(url('/admin')) ?>" class="<?= active_when('/admin') ?>"><?= admin_icon('overview') ?><span class="nav-label">Overview</span></a>
    <a href="<?= e(url('/admin/listings')) ?>" class="<?= active_when('/admin/listings') ?>"><?= admin_icon('listings') ?><span class="nav-label">Listings</span></a>
    <a href="<?= e(url('/admin/listing-review')) ?>" class="<?= active_when('/admin/listing-review', str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/listing-review') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/listings-review') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/guide-listings') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/hotels') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/vehicles')) ?>"><?= admin_icon('check-circle') ?><span class="nav-label">Listing Review</span><span style="display:none;" aria-hidden="true">>Guide</span><?php if ($pendingListingReviews > 0): ?><span class="badge"><?= (int) $pendingListingReviews ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/guides')) ?>" class="<?= active_when('/admin/guides', str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/partner-applications') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/partners') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/guides')) ?>"><?= admin_icon('guides') ?><span class="nav-label">Partner Applications</span><?php if ($pendingGuides > 0): ?><span class="badge"><?= (int) $pendingGuides ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/complaints')) ?>" class="<?= active_when('/admin/complaints', str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/disputes') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/complaints')) ?>"><?= admin_icon('disputes') ?><span class="nav-label">Complaint</span><?php if ($openDisputes > 0): ?><span class="badge"><?= (int) $openDisputes ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/feedback')) ?>" class="<?= active_when('/admin/feedback') ?>"><?= admin_icon('feedback') ?><span class="nav-label">Feedback</span><?php if ($newFeedback > 0): ?><span class="badge"><?= (int) $newFeedback ?></span><?php endif; ?></a>
    <a href="<?= e(url('/admin/analytics')) ?>" class="<?= active_when('/admin/analytics') ?>"><?= admin_icon('analytics') ?><span class="nav-label">Analytics</span></a>
    <a href="<?= e(url('/admin/users')) ?>" class="<?= active_when('/admin/users') ?>"><?= admin_icon('users') ?><span class="nav-label">Users</span><?php if ($warnedProviders > 0): ?><span class="badge"><?= (int) $warnedProviders ?></span><?php endif; ?></a>
</nav>

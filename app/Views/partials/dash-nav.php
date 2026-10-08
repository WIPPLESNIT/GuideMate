<?php
/** @var array<string,mixed> $user */
$user = auth_user();
$role = $user['role'] ?? 'tourist';
?>
<nav class="dash-nav">
    <a href="<?= e(url('/dashboard')) ?>" class="<?= active_when('/dashboard') ?>"><?= admin_icon('home') ?><span class="nav-label"><?= e(__('d_overview', 'Overview')) ?></span></a>
    <?php if ($role === 'guide'): ?>
        <a href="<?= e(url('/dashboard/listings')) ?>" class="<?= active_when('/dashboard/listings') ?>"><?= admin_icon('listings') ?><span class="nav-label"><?= e(__('d_my_listings', 'My Listings')) ?></span></a>
        <a href="<?= e(url('/dashboard/bookings')) ?>" class="<?= active_when('/dashboard/bookings') ?>"><?= admin_icon('bookings') ?><span class="nav-label"><?= e(__('d_bookings', 'Bookings')) ?></span></a>
        <a href="<?= e(url('/dashboard/reviews')) ?>" class="<?= active_when('/dashboard/reviews') ?>"><?= admin_icon('feedback') ?><span class="nav-label"><?= e(__('d_stat_reviews', 'Reviews')) ?></span></a>
        <a href="<?= e(url('/dashboard/transactions')) ?>" class="<?= active_when('/dashboard/transactions') ?>"><?= admin_icon('transactions') ?><span class="nav-label"><?= e(__('d_transactions', 'Transactions')) ?></span></a>
    <?php endif; ?>
    <?php if ($role === 'hotel_admin'): ?>
        <a href="<?= e(url('/dashboard/listings')) ?>" class="<?= active_when('/dashboard/listings') ?>"><?= admin_icon('listings') ?><span class="nav-label">My Hotels</span></a>
        <a href="<?= e(url('/messages')) ?>" class="<?= active_when('/messages') ?>"><?= admin_icon('messages') ?><span class="nav-label">Inquiries</span></a>
        <a href="<?= e(url('/dashboard/reviews')) ?>" class="<?= active_when('/dashboard/reviews') ?>"><?= admin_icon('feedback') ?><span class="nav-label">Reviews</span></a>
    <?php endif; ?>
    <?php if ($role === 'rental_admin'): ?>
        <a href="<?= e(url('/dashboard/rentals')) ?>" class="<?= active_when('/dashboard/rentals') ?>"><?= admin_icon('rentals') ?><span class="nav-label">Rental Requests</span></a>
        <a href="<?= e(url('/dashboard/reviews')) ?>" class="<?= active_when('/dashboard/reviews') ?>"><?= admin_icon('feedback') ?><span class="nav-label">Reviews</span></a>
    <?php endif; ?>
    <?php if ($role === 'admin'): ?>
        <a href="<?= e(url('/admin/listings')) ?>" class="<?= active_when('/admin/listings') ?>"><?= admin_icon('listings') ?><span class="nav-label"><?= e(__('d_listings', 'Listings')) ?></span></a>
        <a href="<?= e(url('/admin/users')) ?>" class="<?= active_when('/admin/users') ?>"><?= admin_icon('users') ?><span class="nav-label"><?= e(__('d_users', 'Users')) ?></span></a>
    <?php endif; ?>
    <?php if ($role === 'tourist'): ?>
        <a href="<?= e(url('/bookings')) ?>" class="<?= active_when('/bookings') ?>"><?= admin_icon('trips') ?><span class="nav-label"><?= e(__('d_my_bookings', 'My Bookings')) ?></span></a>
        <a href="<?= e(url('/bookings/map')) ?>" class="<?= active_when('/bookings/map') ?>"><?= admin_icon('map') ?><span class="nav-label"><?= e(__('d_trip_map', 'Trip Map')) ?></span></a>
        <a href="<?= e(url('/favorites')) ?>" class="<?= active_when('/favorites') ?>"><?= admin_icon('saved') ?><span class="nav-label"><?= e(__('d_saved', 'Saved')) ?></span></a>
    <?php endif; ?>
    <?php // Providers reach Messages from the chat icon in the top header, so the
          // sidebar link is hidden for them to avoid a duplicate. ?>
    <?php if (!\App\Models\User::isProviderRole((string) $role)): ?>
        <a href="<?= e(url('/messages')) ?>" class="<?= active_when('/messages') ?>"><?= admin_icon('feedback') ?><span class="nav-label"><?= e(__('d_messages', 'Messages')) ?></span></a>
    <?php endif; ?>
    <a href="<?= e(url('/profile')) ?>" class="<?= active_when('/profile') ?>"><?= admin_icon('profile') ?><span class="nav-label"><?= e(__('d_profile', 'Profile')) ?></span></a>
</nav>

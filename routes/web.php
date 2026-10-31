<?php

declare(strict_types=1);

/**
 * Application routes.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\AdminAuthController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\DashboardController;
use App\Controllers\DisputeController;
use App\Controllers\DocumentController;
use App\Controllers\GuideController;
use App\Controllers\HomeController;
use App\Controllers\HotelController;
use App\Controllers\ListingController;
use App\Controllers\LocaleController;
use App\Controllers\MessageController;
use App\Controllers\ProfileController;
use App\Controllers\RentalController;
use App\Controllers\ReviewController;

$router->get('/policy', [HomeController::class, 'policy']);
$router->get('/terms', [HomeController::class, 'terms']);
$router->get('/privacy', [HomeController::class, 'privacy']);

// ---- Public ---------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/areas/{slug}', [HomeController::class, 'area']);
$router->get('/locale/{locale}', [LocaleController::class, 'setLocale']);
$router->get('/currency/{currency}', [LocaleController::class, 'setCurrency']);
$router->get('/listings', [ListingController::class, 'index']);
$router->get('/things-to-do', [ListingController::class, 'thingsToDo']);
$router->get('/tour-guides', [ListingController::class, 'tourGuides']);
$router->get('/hotels', [ListingController::class, 'hotels']);
$router->get('/restaurants', [ListingController::class, 'restaurants']);
$router->get('/rentals', [RentalController::class, 'index']);
$router->get('/rentals/{slug}', [RentalController::class, 'show']);
$router->get('/listing/{slug}', [ListingController::class, 'show']);

// ---- Auth -----------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink'], ['guest']);
$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], ['guest']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], ['guest']);
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->post('/appeal', [AuthController::class, 'submitAppeal']);
$router->post('/logout', [AuthController::class, 'logout']);

// ---- Profile & favorites --------------------------------------------------
$router->get('/profile', [ProfileController::class, 'show'], ['auth']);
$router->get('/profile/edit', [ProfileController::class, 'edit'], ['auth']);
$router->post('/profile/edit', [ProfileController::class, 'update'], ['auth']);
$router->post('/profile', [ProfileController::class, 'update'], ['auth']);
$router->get('/favorites', [ListingController::class, 'favorites'], ['auth']);
$router->post('/favorites/toggle', [ListingController::class, 'toggleFavorite']);

// ---- Bookings & payments --------------------------------------------------
$router->post('/listing/{id}/book', [BookingController::class, 'store'], ['auth']);
$router->get('/bookings', [BookingController::class, 'index'], ['auth']);
$router->get('/bookings/map', [BookingController::class, 'map'], ['auth']);
$router->get('/checkout/{id}', [BookingController::class, 'checkout'], ['auth']);
$router->post('/checkout/{id}', [BookingController::class, 'pay'], ['auth']);
$router->post('/bookings/{id}/cancel', [BookingController::class, 'cancel'], ['auth']);
$router->get('/bookings/{id}/report', [DisputeController::class, 'create'], ['auth']);
$router->post('/bookings/{id}/report', [DisputeController::class, 'store'], ['auth']);

// ---- Reviews --------------------------------------------------------------
$router->post('/listing/{id}/review', [ReviewController::class, 'store'], ['auth']);

// ---- Messaging ------------------------------------------------------------
$router->get('/messages', [MessageController::class, 'index'], ['auth']);
$router->get('/messages/archived', [MessageController::class, 'archived'], ['auth']);
$router->get('/messages/archived/{partner}', [MessageController::class, 'archivedThread'], ['auth']);
$router->get('/messages/{partner}/poll', [MessageController::class, 'poll'], ['auth']);
$router->post('/messages/{id}/translate', [MessageController::class, 'translate'], ['auth']);
$router->post('/presence/ping', [MessageController::class, 'pingPresence'], ['auth']);
$router->post('/presence/offline', [MessageController::class, 'leavePresence'], ['auth']);
$router->get('/messages/{partner}', [MessageController::class, 'thread'], ['auth']);
$router->post('/messages', [MessageController::class, 'send'], ['auth']);
$router->post('/messages/{partner}/pin', [MessageController::class, 'pin'], ['auth']);
$router->post('/messages/{partner}/archive', [MessageController::class, 'archive'], ['auth']);
$router->post('/messages/{partner}/unarchive', [MessageController::class, 'unarchive'], ['auth']);
$router->post('/messages/{partner}/delete', [MessageController::class, 'deleteConversation'], ['auth']);
$router->post('/listing/{id}/contact', [MessageController::class, 'contactGuide'], ['auth']);
$router->post('/guides/{id}/contact', [MessageController::class, 'contactGuideProfile'], ['auth']);

// ---- Dashboard (all roles) ------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard/reviews', [DashboardController::class, 'reviews'], ['provider']);

// ---- Documents & Verification ---------------------------------------------
$router->get('/documents', [DocumentController::class, 'myDocuments'], ['auth']);
$router->post('/documents/upload', [DocumentController::class, 'upload'], ['auth']);
$router->post('/documents', [DocumentController::class, 'upload'], ['auth']);
$router->get('/documents/{id}', [DocumentController::class, 'show'], ['auth']);
$router->get('/documents/download/{id}', [DocumentController::class, 'download'], ['auth']);
$router->get('/admin/users/{id}/documents', [DocumentController::class, 'adminShow'], ['admin']);
$router->get('/uploads/guide-docs/{userId}/{filename}', [DocumentController::class, 'serveFile']);
$router->get('/uploads/rentals/ids/{userId}/{filename}', [DocumentController::class, 'serveRentalId']);
$router->get('/documents/file/{id}', [DocumentController::class, 'serveFileById']);

// ---- Legacy verification route redirects directly to /documents -----------
$router->get('/dashboard/verification', [GuideController::class, 'verification'], ['auth']);
$router->post('/dashboard/verification', [DocumentController::class, 'upload'], ['auth']);
$router->get('/dashboard/verification/status', [GuideController::class, 'verificationStatus'], ['auth']);

// ---- Listing management (guides + hotel partners) -------------------------
$router->get('/dashboard/listings', [GuideController::class, 'listings'], ['listing_provider']);
$router->get('/dashboard/listings/create', [GuideController::class, 'create'], ['listing_provider']);
$router->post('/dashboard/listings', [GuideController::class, 'store'], ['listing_provider']);
$router->get('/dashboard/listings/{id}/edit', [GuideController::class, 'edit'], ['listing_provider']);
$router->post('/dashboard/listings/{id}', [GuideController::class, 'update'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/delete', [GuideController::class, 'destroy'], ['listing_provider']);
$router->get('/dashboard/bookings', [GuideController::class, 'bookings'], ['listing_provider']);
$router->get('/dashboard/bookings/verify', [GuideController::class, 'showVerifyBooking'], ['listing_provider']);
$router->post('/dashboard/bookings/verify', [GuideController::class, 'verifyBooking'], ['listing_provider']);
$router->get('/dashboard/transactions', [GuideController::class, 'transactions'], ['listing_provider']);
$router->post('/dashboard/bookings/{id}/status', [GuideController::class, 'updateBookingStatus'], ['listing_provider']);
$router->get('/dashboard/listings/{id}/availability', [GuideController::class, 'availability'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/availability/block', [GuideController::class, 'blockDate'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/availability/unblock', [GuideController::class, 'unblockDate'], ['listing_provider']);
$router->get('/dashboard/listings/{id}/schedule', [GuideController::class, 'schedule'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/schedule/add', [GuideController::class, 'addSchedule'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/schedule/{scheduleId}/update', [GuideController::class, 'updateSchedule'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/schedule/{scheduleId}/edit', [GuideController::class, 'updateSchedule'], ['listing_provider']);
$router->post('/dashboard/listings/{id}/schedule/remove', [GuideController::class, 'removeSchedule'], ['listing_provider']);

// ---- Hotel partner property management, room inventory & reservations -------
$router->get('/dashboard/hotels', [HotelController::class, 'hotels'], ['hotel_provider']);
$router->post('/dashboard/hotels', [HotelController::class, 'storeHotel'], ['hotel_provider']);
$router->get('/dashboard/hotels/{id}', [HotelController::class, 'getHotel'], ['hotel_provider']);
$router->post('/dashboard/hotels/{id}', [HotelController::class, 'updateHotel'], ['hotel_provider']);
$router->post('/dashboard/hotels/{id}/resubmit', [HotelController::class, 'resubmitHotel'], ['hotel_provider']);
$router->post('/dashboard/hotels/{id}/delete', [HotelController::class, 'destroyHotel'], ['hotel_provider']);
$router->get('/dashboard/hotels/{id}/rooms', [HotelController::class, 'propertyRooms'], ['hotel_provider']);
$router->post('/dashboard/hotel/rooms/create', [HotelController::class, 'createRoom'], ['auth']);
$router->post('/dashboard/hotel/rooms/{id}/edit', [HotelController::class, 'updateRoom'], ['auth']);
$router->post('/dashboard/hotel/rooms/{id}/status', [HotelController::class, 'updateRoomStatus'], ['auth']);
$router->post('/dashboard/hotel/rooms/{id}/delete', [HotelController::class, 'destroyRoom'], ['auth']);
$router->post('/dashboard/hotel/bookings/{id}/status', [HotelController::class, 'updateReservationStatus'], ['auth']);

// ---- Rental partner management (relocated from the admin portal) -----------
$router->get('/dashboard/vehicles', [RentalController::class, 'vehicles'], ['rental_provider']);
$router->get('/dashboard/vehicles/create', [RentalController::class, 'createVehicle'], ['rental_provider']);
$router->post('/dashboard/vehicles', [RentalController::class, 'storeVehicle'], ['rental_provider']);
$router->get('/dashboard/vehicles/{id}/edit', [RentalController::class, 'editVehicle'], ['rental_provider']);
$router->post('/dashboard/vehicles/{id}', [RentalController::class, 'updateVehicle'], ['rental_provider']);
$router->post('/dashboard/vehicles/{id}/toggle-availability', [RentalController::class, 'toggleAvailability'], ['rental_provider']);
$router->post('/dashboard/vehicles/{id}/resubmit', [RentalController::class, 'resubmitVehicle'], ['rental_provider']);
$router->post('/dashboard/vehicles/{id}/delete', [RentalController::class, 'destroyVehicle'], ['rental_provider']);
$router->get('/dashboard/rentals', [RentalController::class, 'requests'], ['rental_provider']);
$router->post('/dashboard/rentals/{id}/status', [RentalController::class, 'updateStatus'], ['rental_provider']);
$router->post('/dashboard/rentals/{id}/refund', [RentalController::class, 'refund'], ['rental_provider']);
$router->post('/dashboard/rentals/{id}/reject-report', [RentalController::class, 'rejectReport'], ['rental_provider']);

// ---- Admin portal (separate auth, isolated from public users) -------------
$router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);
$router->get('/admin/2fa', [AdminAuthController::class, 'showTwoFa']);
$router->post('/admin/2fa', [AdminAuthController::class, 'verifyTwoFa']);
$router->post('/admin/logout', [AdminAuthController::class, 'logout']);

$router->get('/admin', [AdminController::class, 'index'], ['admin']);
$router->get('/admin/feedback', [AdminController::class, 'feedback'], ['admin']);
$router->post('/admin/feedback/{id}/status', [AdminController::class, 'updateFeedbackStatus'], ['admin']);
$router->get('/admin/analytics', [AdminController::class, 'analytics'], ['admin']);
$router->get('/admin/messages', [AdminController::class, 'messages'], ['admin']);
$router->post('/admin/messages/send', [AdminController::class, 'sendMessage'], ['admin']);
$router->get('/admin/messages/{partner}/poll', [AdminController::class, 'poll'], ['admin']);
$router->get('/admin/messages/{partner}', [AdminController::class, 'messageThread'], ['admin']);
$router->get('/admin/export/bookings', [AdminController::class, 'exportBookings'], ['admin']);
$router->get('/admin/export/users', [AdminController::class, 'exportUsers'], ['admin']);
$router->get('/admin/export/disputes', [AdminController::class, 'exportDisputes'], ['admin']);
$router->get('/admin/security', [AdminAuthController::class, 'showSecurity'], ['admin']);
$router->post('/admin/security/setup', [AdminAuthController::class, 'setupTwoFa'], ['admin']);
$router->post('/admin/security/enable', [AdminAuthController::class, 'enableTwoFa'], ['admin']);
$router->post('/admin/security/disable', [AdminAuthController::class, 'disableTwoFa'], ['admin']);
$router->get('/admin/listings', [AdminController::class, 'listings'], ['admin']);
$router->post('/admin/listings/{id}/status', [AdminController::class, 'updateListingStatus'], ['admin']);
$router->post('/admin/listings/{id}/feature', [AdminController::class, 'toggleFeatured'], ['admin']);
$router->get('/admin/hotels', [AdminController::class, 'hotels'], ['admin']);
$router->get('/admin/hotels/{id}', [AdminController::class, 'showHotel'], ['admin']);
$router->post('/admin/hotels/{id}/approve', [AdminController::class, 'approveHotel'], ['admin']);
$router->post('/admin/hotels/{id}/reject', [AdminController::class, 'rejectHotel'], ['admin']);
$router->post('/admin/hotels/{id}/delete', [AdminController::class, 'deleteHotel'], ['admin']);
$router->delete('/admin/hotels/{id}', [AdminController::class, 'deleteHotel'], ['admin']);
$router->get('/admin/vehicles', [AdminController::class, 'vehicles'], ['admin']);
$router->get('/admin/vehicles/{id}', [AdminController::class, 'showVehicle'], ['admin']);
$router->post('/admin/vehicles/{id}/approve', [AdminController::class, 'approveVehicle'], ['admin']);
$router->post('/admin/vehicles/{id}/reject', [AdminController::class, 'rejectVehicle'], ['admin']);
$router->post('/admin/vehicles/{id}/delete', [AdminController::class, 'deleteVehicle'], ['admin']);
$router->delete('/admin/vehicles/{id}', [AdminController::class, 'deleteVehicle'], ['admin']);
$router->get('/admin/users', [AdminController::class, 'users'], ['admin']);
$router->get('/admin/users/create', [AdminController::class, 'createUser'], ['admin']);
$router->post('/admin/users', [AdminController::class, 'storeUser'], ['admin']);
$router->get('/admin/users/{id}/edit', [AdminController::class, 'editUser'], ['admin']);
$router->post('/admin/users/{id}/edit', [AdminController::class, 'updateUser'], ['admin']);
$router->post('/admin/users/{id}', [AdminController::class, 'updateUser'], ['admin']);
$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], ['admin']);
$router->post('/admin/users/{id}/revoke', [AdminController::class, 'revokeGuide'], ['admin']);
$router->post('/admin/users/{id}/toggle', [AdminController::class, 'toggleUser'], ['admin']);
$router->post('/admin/users/{id}/suspend', [AdminController::class, 'suspendUser'], ['admin']);
$router->post('/admin/users/{id}/unsuspend', [AdminController::class, 'unsuspendUser'], ['admin']);
$router->post('/admin/users/{id}/clear-warning', [AdminController::class, 'clearGuideWarning'], ['admin']);
$router->get('/admin/guides', [AdminController::class, 'guides'], ['admin']);
$router->post('/admin/guides/{id}/approve', [AdminController::class, 'approveGuide'], ['admin']);
$router->post('/admin/guides/{id}/reject', [AdminController::class, 'rejectGuide'], ['admin']);
$router->post('/admin/guides/{id}/revoke', [AdminController::class, 'revokeGuide'], ['admin']);
$router->get('/admin/disputes', [AdminController::class, 'disputes'], ['admin']);
$router->post('/admin/disputes/{id}/resolve', [AdminController::class, 'resolveDispute'], ['admin']);

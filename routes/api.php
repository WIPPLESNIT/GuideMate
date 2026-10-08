<?php

declare(strict_types=1);

use App\Controllers\ApiController;
use App\Core\Router;

/** @var Router $router */

$router->get('/api/home', [ApiController::class, 'home']);
$router->get('/api/categories', [ApiController::class, 'categories']);
$router->get('/api/listings', [ApiController::class, 'listings']);
$router->get('/api/listings/{slug}', [ApiController::class, 'listing']);

$router->post('/api/auth/login', [ApiController::class, 'login']);
$router->post('/api/auth/register', [ApiController::class, 'register']);
$router->post('/api/auth/social', [ApiController::class, 'socialAuth']);
$router->get('/api/auth/google/start', [ApiController::class, 'googleStart']);
$router->get('/api/auth/google/callback', [ApiController::class, 'googleCallback']);
$router->get('/api/auth/google/pending', [ApiController::class, 'googlePending']);
$router->post('/api/auth/google/exchange', [ApiController::class, 'googleExchange']);
$router->get('/api/auth/facebook/start', [ApiController::class, 'facebookStart']);
$router->get('/api/auth/facebook/callback', [ApiController::class, 'facebookCallback']);
$router->post('/api/auth/forgot-password', [ApiController::class, 'forgotPassword']);
$router->get('/api/auth/me', [ApiController::class, 'me']);
$router->post('/api/auth/logout', [ApiController::class, 'logout']);
$router->post('/api/auth/change-password', [ApiController::class, 'changePassword']);
$router->post('/api/auth/delete-account', [ApiController::class, 'deleteAccount']);

$router->post('/api/profile', [ApiController::class, 'updateProfile']);
$router->post('/api/profile/avatar', [ApiController::class, 'uploadAvatar']);

$router->get('/api/bookings', [ApiController::class, 'bookings']);
$router->post('/api/bookings', [ApiController::class, 'createBooking']);
$router->get('/api/trip-map', [ApiController::class, 'tripMap']);
$router->get('/api/maps', [ApiController::class, 'mapsConfig']);
$router->get('/api/vouchers', [ApiController::class, 'vouchers']);
$router->post('/api/vouchers/validate', [ApiController::class, 'validateVoucher']);

$router->get('/api/favorites', [ApiController::class, 'favorites']);
$router->post('/api/favorites/toggle', [ApiController::class, 'toggleFavorite']);

$router->post('/api/reviews', [ApiController::class, 'submitReview']);
$router->get('/api/disputes', [ApiController::class, 'myDispute']);
$router->post('/api/disputes', [ApiController::class, 'submitDispute']);

$router->get('/api/guides', [ApiController::class, 'guides']);
$router->get('/api/messages', [ApiController::class, 'conversations']);
$router->get('/api/messages/thread', [ApiController::class, 'thread']);
$router->get('/api/messages/poll', [ApiController::class, 'pollThread']);
$router->post('/api/messages/send', [ApiController::class, 'sendMessage']);
$router->post('/api/messages/archive', [ApiController::class, 'archiveConversation']);
$router->post('/api/messages/unarchive', [ApiController::class, 'unarchiveConversation']);
$router->post('/api/messages/delete', [ApiController::class, 'deleteConversation']);

$router->get('/api/rentals', [ApiController::class, 'rentalUnits']);
$router->get('/api/rentals/mine', [ApiController::class, 'myRentals']);
$router->post('/api/rentals', [ApiController::class, 'createRental']);
$router->post('/api/rentals/report', [ApiController::class, 'reportRental']);

$router->get('/api/feedback', [ApiController::class, 'myFeedback']);
$router->post('/api/feedback', [ApiController::class, 'submitFeedback']);

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiAuth;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Geo;
use App\Core\Translator;
use App\Core\Upload;
use RuntimeException;
use App\Models\Booking;
use App\Models\Category;
use App\Models\ConversationSetting;
use App\Models\Dispute;
use App\Models\DisputeFile;
use App\Models\Favorite;
use App\Models\Feedback;
use App\Models\Listing;
use App\Core\App;
use App\Models\Message;
use App\Models\PasswordReset;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\RentalReportFile;
use App\Models\RentalRequest;
use App\Models\RentalReview;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationService;
use App\Services\OAuthPending;
use App\Services\RecommendationService;

final class ApiController extends Controller
{
    /**
     * Cache of the current user's favorited listing ids, loaded once per
     * request so every listing in a list response can report `favorited`
     * without an extra query per row. Null until first resolved.
     *
     * @var array<int, int>|null
     */
    private ?array $favoriteIds = null;

    public function __construct()
    {
        $this->sendCors();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    public function home(): void
    {
        $userId = ApiAuth::id();
        $this->json([
            'categories' => array_map([$this, 'categoryPayload'], Category::withPublicCounts()),
            'featured' => array_map([$this, 'listingPayload'], Listing::featured(8)),
            'recommended' => array_map(
                [$this, 'listingPayload'],
                RecommendationService::forUser($userId, 8)
            ),
            // Total number of published (approved) places. The app compares this
            // to the value it last saw to show a "new places to explore" banner
            // when guides publish new tours (after admin approval).
            'listings_total' => Listing::count('approved'),
            'areas' => [
                ['slug' => 'cebu-city', 'name' => 'Cebu City', 'tagline' => 'Heritage & food tours'],
                ['slug' => 'mactan', 'name' => 'Mactan', 'tagline' => 'Island hopping & resorts'],
                ['slug' => 'oslob', 'name' => 'Oslob', 'tagline' => 'Whale sharks & waterfalls'],
                ['slug' => 'moalboal', 'name' => 'Moalboal', 'tagline' => 'Diving & sardine runs'],
                ['slug' => 'bantayan', 'name' => 'Bantayan', 'tagline' => 'White-sand beaches'],
            ],
        ]);
    }

    public function categories(): void
    {
        $this->json([
            'categories' => array_map([$this, 'categoryPayload'], Category::withPublicCounts()),
        ]);
    }

    public function listings(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'category' => (string) $this->input('category', ''),
            'area' => (string) $this->input('area', ''),
            'sort' => (string) $this->input('sort', ''),
            'min_rating' => (string) $this->input('min_rating', ''),
            'featured' => (string) $this->input('featured', ''),
        ];
        $listings = Listing::search($filters);
        $this->json([
            'listings' => array_map([$this, 'listingPayload'], $listings),
            'filters' => $filters,
        ]);
    }

    public function listing(string $slug): void
    {
        $listing = Listing::findBySlug($slug);
        if ($listing === null || $listing['status'] !== 'approved') {
            $this->json(['error' => 'Listing not found.'], 404);
            return;
        }
        if (Category::isHiddenSlug((string) ($listing['category_slug'] ?? ''))) {
            $this->json(['error' => 'Listing not found.'], 404);
            return;
        }

        $listingId = (int) $listing['id'];
        $reviews = Review::forListing($listingId);
        foreach ($reviews as &$review) {
            $review['images'] = ReviewImage::forReview((int) $review['id']);
        }
        unset($review);

        $gallery = Listing::gallery($listingId);
        $userId = ApiAuth::id();
        $favorited = $userId !== null && Favorite::exists($userId, $listingId);
        $activeBooking = $userId !== null ? Booking::getActiveBookingForListing($listingId, $userId) : null;
        $alreadyBooked = $activeBooking !== null;
        $hasReviewed = $userId !== null && Review::userHasReviewed($listingId, $userId);
        // Reviews open once the tourist has a completed (or confirmed) booking.
        $canReview = $userId !== null && !$hasReviewed && Booking::hasCompletedBooking($listingId, $userId);

        $listingPayload = $this->listingPayload($listing, true);
        // Suggest the closest hotel to this experience (skip if the listing is
        // itself a hotel) so travelers can plan where to stay nearby.
        if ((string) ($listing['category_slug'] ?? '') !== 'hotels') {
            $nearest = $this->nearestHotelPayload($listing);
            if ($nearest !== null) {
                $listingPayload['nearest_hotel'] = $nearest;
            }
        }

        $rawRooms = ((string) ($listing['category_slug'] ?? '') === 'hotels')
            ? \App\Models\HotelRoom::forListing($listingId)
            : [];
        $rooms = array_map(static function (array $rm): array {
            $photos = \App\Models\HotelRoom::getPhotos($rm);
            $rm['photos'] = array_map(static fn (string $p): string => api_img_src($p, 'room' . ($rm['id'] ?? 0)), $photos);
            $rm['image'] = !empty($rm['photos']) ? $rm['photos'][0] : api_img_src((string) ($rm['image'] ?? ''), 'room' . ($rm['id'] ?? 0));
            unset($rm['room_size']);
            return $rm;
        }, $rawRooms);

        $listingCap = (int) (!empty($listing['capacity']) ? $listing['capacity'] : 10);
        $totalBookedSum = Database::first(
            'SELECT COALESCE(SUM(guests), 0) AS total_booked
             FROM bookings
             WHERE listing_id = ?
               AND status IN ("pending", "confirmed")
               AND (booking_date >= CURDATE() OR booking_date IS NULL)',
            [$listingId]
        );
        $totalListingBooked = (int) ($totalBookedSum['total_booked'] ?? 0);
        $listingAvailSlots = max(0, $listingCap - $totalListingBooked);

        $dateBookedRows = Database::all(
            'SELECT booking_date, COALESCE(SUM(guests), 0) AS booked_count
             FROM bookings
             WHERE listing_id = ?
               AND status IN ("pending", "confirmed")
               AND booking_date >= CURDATE()
             GROUP BY booking_date',
            [$listingId]
        );
        $dateBookedMap = [];
        foreach ($dateBookedRows as $dr) {
            if (!empty($dr['booking_date'])) {
                $dateBookedMap[(string) $dr['booking_date']] = (int) $dr['booked_count'];
            }
        }

        $listingPayload['capacity'] = $listingCap;
        $listingPayload['total_capacity'] = $listingCap;
        $listingPayload['booked_count'] = $totalListingBooked;
        $listingPayload['available_slots'] = $listingAvailSlots;
        $listingPayload['is_available'] = $listingAvailSlots > 0;
        $listingPayload['date_booked_counts'] = $dateBookedMap;

        $this->json([
            'listing' => $listingPayload,
            'rooms' => $rooms,
            'schedules' => array_map(function (array $s) use ($listingId, $listing): array {
                $sessionCap = (int) (!empty($s['capacity']) ? $s['capacity'] : ($listing['capacity'] ?? 10));
                $sessionTime = $s['start_time'] !== null ? substr((string) $s['start_time'], 0, 5) : null;
                $bookedSum = Database::first(
                    'SELECT COALESCE(SUM(guests), 0) AS total_booked
                     FROM bookings
                     WHERE listing_id = ?
                       AND booking_date = ?
                       AND status IN ("pending", "confirmed")
                       AND (? IS NULL OR booking_time LIKE CONCAT(?, "%"))',
                    [$listingId, $s['schedule_date'], $sessionTime, $sessionTime]
                );
                $bookedCount = (int) ($bookedSum['total_booked'] ?? 0);
                $availSlots = max(0, $sessionCap - $bookedCount);
                return [
                    'id' => (int) $s['id'],
                    'listing_id' => (int) $s['listing_id'],
                    'schedule_date' => (string) $s['schedule_date'],
                    'start_time' => $s['start_time'] !== null ? (string) $s['start_time'] : null,
                    'end_time' => $s['end_time'] !== null ? (string) $s['end_time'] : null,
                    'capacity' => $sessionCap,
                    'booked_count' => $bookedCount,
                    'available_slots' => $availSlots,
                    'is_available' => $availSlots > 0,
                    'note' => $s['note'] !== null ? (string) $s['note'] : null,
                ];
            }, \App\Models\ListingSchedule::forListing($listingId)),
            'already_booked' => $alreadyBooked,
            'active_booking' => $activeBooking !== null ? [
                'id' => (int) $activeBooking['id'],
                'status' => (string) $activeBooking['status'],
                'status_label' => ucfirst((string) $activeBooking['status']),
                'booking_date' => (string) $activeBooking['booking_date'],
                'booking_time' => (string) ($activeBooking['booking_time'] ?? ''),
            ] : null,
            'has_reviewed' => $hasReviewed,
            'can_review' => $canReview,
            // Guide-level: disable every slot where this guide is already busy
            // (on this or any of their other listings) to avoid double-booking.
            'booked_slots' => Booking::guideBookedSlots((int) ($listing['user_id'] ?? 0)),
            'gallery' => array_map(
                static fn (array $img): array => [
                    'id' => (int) $img['id'],
                    'url' => api_img_src((string) ($img['image_path'] ?? ''), 'listing' . $listingId),
                ],
                $gallery
            ),
            'reviews' => array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'rating' => (int) $r['rating'],
                'comment' => (string) ($r['comment'] ?? ''),
                'user_name' => (string) ($r['user_name'] ?? 'Traveler'),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ], $reviews),
            'summary' => Review::summary($listingId),
            'favorited' => $favorited,
        ]);
    }

    public function login(): void
    {
        $body = $this->jsonBody();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->json(['error' => 'Email and password are required.'], 422);
            return;
        }

        $existing = User::findByEmail($email);
        if ($existing !== null && $existing['role'] === 'admin') {
            $this->json(['error' => 'Administrators must use the web admin portal.'], 403);
            return;
        }
        if ($existing !== null && (int) $existing['is_active'] === 0) {
            $msg = !empty($existing['suspension_reason'])
                ? 'This account has been suspended (' . $existing['suspension_reason'] . '). Please contact admin to activate it.'
                : 'This account is inactive. Please contact admin to activate it.';
            $this->json(['error' => $msg], 403);
            return;
        }

        if (!Auth::attempt($email, $password)) {
            $this->json(['error' => 'Invalid email or password.'], 401);
            return;
        }

        $user = Auth::user();
        if ($user === null) {
            $this->json(['error' => 'Login failed.'], 500);
            return;
        }

        $token = ApiAuth::issue((int) $user['id']);
        Auth::logout();

        $this->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function register(): void
    {
        $body = $this->jsonBody();
        $name = trim((string) ($body['name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            $this->json(['error' => 'Name, email, and password are required.'], 422);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Please enter a valid email address.'], 422);
            return;
        }
        if (User::emailExists($email)) {
            $this->json(['error' => 'That email is already registered.'], 422);
            return;
        }

        $userId = User::create($name, $email, $password, 'tourist');

        $user = User::find($userId);
        if ($user === null) {
            $this->json(['error' => 'Registration failed.'], 500);
            return;
        }

        $token = ApiAuth::issue($userId);
        $this->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * POST /api/auth/social â€” sign in with Google or Facebook.
     *
     * Privacy by design: we store ONLY the provider + its opaque user id. The
     * real Google/Facebook email and password are never received or stored, so
     * an admin can never see them; the account appears anonymously.
     *
     * DEMO mode (config social.demo=true) trusts a client-supplied "subject" so
     * the buttons work without real OAuth apps. In LIVE mode we verify a real
     * Google id_token / Facebook access_token server-side before trusting it.
     */
    public function socialAuth(): void
    {
        $body = $this->jsonBody();
        $provider = strtolower(trim((string) ($body['provider'] ?? '')));
        if (!in_array($provider, ['google', 'facebook'], true)) {
            $this->json(['error' => 'Unsupported sign-in provider.'], 422);
            return;
        }

        $social = App::config('social') ?? [];
        $demo = (bool) ($social['demo'] ?? true);
        $token = trim((string) ($body['token'] ?? ''));

        if ($token !== '') {
            // A real provider token was supplied â€” always verify it server-side.
            $subject = $provider === 'google'
                ? $this->verifyGoogleToken($token, (array) ($social['google_client_ids'] ?? []))
                : $this->verifyFacebookToken($token);
            if ($subject === null) {
                $this->json(['error' => 'Could not verify your ' . ucfirst($provider) . ' sign-in. Please try again.'], 401);
                return;
            }
        } elseif ($demo) {
            // No token: fall back to the anonymous demo identity.
            $subject = trim((string) ($body['subject'] ?? ''));
            if ($subject === '') {
                $this->json(['error' => 'Missing demo subject.'], 422);
                return;
            }
            // Namespace demo ids so they can never collide with real ones.
            $subject = 'demo_' . $subject;
        } else {
            $this->json(['error' => 'Missing provider token.'], 422);
            return;
        }

        $existing = User::findByOAuth($provider, $subject);
        if ($existing !== null) {
            if ((int) ($existing['is_active'] ?? 1) === 0) {
                $this->json(['error' => 'This account is inactive. Please contact admin to activate it.'], 403);
                return;
            }
            $userId = (int) $existing['id'];
        } else {
            $userId = User::createOAuthUser($provider, $subject);
        }

        $user = User::find($userId);
        if ($user === null) {
            $this->json(['error' => 'Sign-in failed.'], 500);
            return;
        }

        $token = ApiAuth::issue($userId);
        $this->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Verify a Google ID token via Google's tokeninfo endpoint. Returns the
     * stable subject id ("sub") only when the token is valid and (if client ids
     * are configured) was issued for one of our apps.
     */
    private function verifyGoogleToken(string $idToken, array $clientIds): ?string
    {
        $resp = $this->httpGetJson('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        if ($resp === null || empty($resp['sub'])) {
            return null;
        }
        if (!in_array((string) ($resp['iss'] ?? ''), ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return null;
        }
        if ($clientIds !== [] && !in_array((string) ($resp['aud'] ?? ''), $clientIds, true)) {
            return null;
        }
        return (string) $resp['sub'];
    }

    /**
     * Verify a Facebook access token via the Graph API and return the
     * app-scoped user id. We intentionally request only the id (never email).
     */
    private function verifyFacebookToken(string $accessToken): ?string
    {
        $resp = $this->httpGetJson('https://graph.facebook.com/me?fields=id&access_token=' . urlencode($accessToken));
        if ($resp === null || empty($resp['id'])) {
            return null;
        }
        return (string) $resp['id'];
    }

    /**
     * Minimal HTTPS GET returning decoded JSON, or null on any failure.
     *
     * @return array<string, mixed>|null
     */
    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>|null
     */
    private function httpGetJson(string $url, array $headers = []): ?array
    {
        $body = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ];
            if (!empty($headers)) {
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
            curl_setopt_array($ch, $opts);
            $out = curl_exec($ch);
            $ok = $out !== false && (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
            curl_close($ch);
            $body = $ok ? (string) $out : null;
        } else {
            $httpOpts = ['timeout' => 10, 'ignore_errors' => true];
            if (!empty($headers)) {
                $httpOpts['header'] = implode("\r\n", $headers);
            }
            $ctx = stream_context_create(['http' => $httpOpts]);
            $out = @file_get_contents($url, false, $ctx);
            $body = $out === false ? null : (string) $out;
        }
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * GET /api/auth/google/start â€” begin the Expo Go-compatible Google login.
     *
     * The mobile app opens this URL in a web browser; we redirect to Google.
     * Google only ever redirects back to OUR https callback (never the phone),
     * which is why this works in Expo Go without a dev build.
     */
    public function googleStart(): void
    {
        $social = App::config('social') ?? [];
        $clientId = (string) (($social['google_web_client_id'] ?? '') ?: ($social['google_client_ids'][0] ?? ''));
        $secret = (string) ($social['google_client_secret'] ?? '');
        $redirect = $this->googleRegisteredRedirect();
        $this->wakeGoogleTunnel();
        $return = trim((string) ($_GET['return'] ?? ''));
        $asJson = (string) ($_GET['format'] ?? '') === 'json'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if ($clientId === '' || $secret === '' || $redirect === '') {
            if ($asJson) {
                $this->json(['error' => 'Google sign-in is not configured on the server yet.'], 503);
                return;
            }
            $this->socialErrorPage('Google sign-in is not configured on the server yet.');
            return;
        }
        if ($return === '' || !preg_match('#^(exp|guidemate|https?)://#i', $return)) {
            if ($asJson) {
                $this->json(['error' => 'Invalid return target.'], 422);
                return;
            }
            $this->socialErrorPage('Invalid return target.');
            return;
        }

        $sessionId = bin2hex(random_bytes(16));
        OAuthPending::put($sessionId, ['status' => 'pending']);

        $payload = $this->b64urlEncode((string) json_encode([
            'r' => $return,
            't' => time(),
            'n' => bin2hex(random_bytes(8)),
            's' => $sessionId,
        ]));
        $sig = $this->b64urlEncode(hash_hmac('sha256', $payload, $secret, true));
        $state = $payload . '.' . $sig;

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            // 'profile' returns the real display name (and 'email' the address)
            // in the id_token so we can show "Mole Kraine" instead of "Google User".
            'scope' => 'openid profile email',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online',
        ]);
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;

        if ($asJson) {
            $this->json([
                'auth_url' => $authUrl,
                'session_id' => $sessionId,
                'redirect_uri' => $redirect,
            ]);
            return;
        }
        header('Location: ' . $authUrl, true, 302);
    }

    /**
     * GET /api/auth/google/pending?session= â€” phone polls the LAN for the
     * finished token so login still works if the browser deep-link is lost.
     */
    public function googlePending(): void
    {
        $sessionId = trim((string) ($_GET['session'] ?? ''));
        $row = OAuthPending::get($sessionId);
        if ($row === null) {
            $this->json(['status' => 'missing'], 404);
            return;
        }
        $this->json([
            'status' => (string) ($row['status'] ?? 'pending'),
            'token' => (string) ($row['token'] ?? ''),
            'error' => (string) ($row['error'] ?? ''),
        ]);
    }

    /**
     * POST /api/auth/google/exchange â€” the mobile WebView intercepted Google's
     * redirect (so ngrok never has to be online) and sends us the auth code.
     */
    public function googleExchange(): void
    {
        $body = $this->jsonBody();
        $code = trim((string) ($body['code'] ?? ''));
        $state = trim((string) ($body['state'] ?? ''));
        $oauthError = trim((string) ($body['oauth_error'] ?? ''));
        $redirectUri = trim((string) ($body['redirect_uri'] ?? ''));

        $result = $this->redeemGoogleAuth(
            $code,
            $state,
            $oauthError !== '' ? $oauthError : null,
            $redirectUri !== '' ? $redirectUri : null
        );
        if (isset($result['error'])) {
            $this->json(['error' => (string) $result['error']], 401);
            return;
        }
        $this->json(['token' => (string) $result['token']]);
    }

    /**
     * GET /api/auth/google/callback â€” Google redirects here with an auth code.
     * We exchange it, verify the id token, load/create the anonymous account,
     * mint our app token, and hand it back to the app via its return link.
     */
    public function googleCallback(): void
    {
        $social = App::config('social') ?? [];
        $secret = (string) ($social['google_client_secret'] ?? '');
        $decoded = $this->decodeOAuthState((string) ($_GET['state'] ?? ''), $secret);
        $return = $decoded !== null ? (string) $decoded['r'] : 'guidemate://auth/google';
        $sessionId = $decoded !== null ? (string) ($decoded['s'] ?? '') : '';

        $result = $this->redeemGoogleAuth(
            (string) ($_GET['code'] ?? ''),
            (string) ($_GET['state'] ?? ''),
            isset($_GET['error']) ? (string) $_GET['error'] : null
        );
        if (isset($result['error'])) {
            $this->finishOAuth($return, $sessionId, ['error' => (string) $result['error']]);
            return;
        }
        $this->finishOAuth($return, $sessionId, ['token' => (string) $result['token']]);
    }

    /**
     * Must match an Authorized redirect URI on the Google Web client.
     * This projectâ€™s Console entry is the static ngrok URL (localhost is not registered).
     */
    private function googleRegisteredRedirect(): string
    {
        $configured = trim((string) ((App::config('social') ?? [])['google_redirect'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        return 'https://concinnous-unobliging-max.ngrok-free.dev/GuideMate/public/api/auth/google/callback';
    }

    /**
     * Kick ngrok in the background so a missed WebView intercept can still
     * complete on the public callback. Does not block the JSON response.
     */
    private function wakeGoogleTunnel(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }
        $root = dirname(__DIR__, 2);
        $vbs = $root . DIRECTORY_SEPARATOR . 'start-ngrok-hidden.vbs';
        $bat = $root . DIRECTORY_SEPARATOR . 'start-ngrok.bat';
        if (is_file($vbs)) {
            @pclose(@popen('wscript.exe ' . escapeshellarg($vbs), 'r'));
            return;
        }
        if (is_file($bat)) {
            @pclose(@popen('cmd.exe /c start "" /MIN ' . escapeshellarg($bat), 'r'));
        }
    }

    /**
     * @return list<string>
     */
    private function googleRedirectCandidates(?string $preferred = null): array
    {
        $candidates = [
            trim((string) $preferred),
            $this->googleRegisteredRedirect(),
            'https://concinnous-unobliging-max.ngrok-free.dev/GuideMate/public/api/auth/google/callback',
            'http://localhost/GuideMate/public/api/auth/google/callback',
            'http://127.0.0.1/GuideMate/public/api/auth/google/callback',
        ];
        $unique = [];
        foreach ($candidates as $uri) {
            if ($uri !== '' && !in_array($uri, $unique, true)) {
                $unique[] = $uri;
            }
        }
        return $unique;
    }

    /**
     * Exchange a Google auth code for a GuideMate session token.
     *
     * @return array{token?: string, error?: string}
     */
    private function redeemGoogleAuth(string $code, string $state, ?string $oauthError, ?string $preferredRedirect = null): array
    {
        $social = App::config('social') ?? [];
        $clientId = (string) (($social['google_web_client_id'] ?? '') ?: ($social['google_client_ids'][0] ?? ''));
        $secret = (string) ($social['google_client_secret'] ?? '');
        $redirects = $this->googleRedirectCandidates($preferredRedirect);

        $decoded = $this->decodeOAuthState($state, $secret);
        $sessionId = $decoded !== null ? (string) ($decoded['s'] ?? '') : '';

        if ($oauthError !== null && $oauthError !== '') {
            $message = $oauthError === 'access_denied' ? 'Google sign-in was cancelled.' : 'Google sign-in was cancelled.';
            if ($sessionId !== '') {
                OAuthPending::put($sessionId, ['status' => 'error', 'error' => $message]);
            }
            return ['error' => $message];
        }
        if ($decoded === null) {
            return ['error' => 'Your sign-in link expired. Please try again.'];
        }
        if ($code === '' || $clientId === '' || $secret === '' || $redirects === []) {
            $message = 'Google sign-in failed. Please try again.';
            OAuthPending::put($sessionId, ['status' => 'error', 'error' => $message]);
            return ['error' => $message];
        }

        $tokenResp = null;
        $googleError = '';
        foreach ($redirects as $redirect) {
            $attempt = $this->httpPostForm('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $secret,
                'redirect_uri' => $redirect,
                'grant_type' => 'authorization_code',
            ]);
            if (is_array($attempt) && trim((string) ($attempt['id_token'] ?? '')) !== '') {
                $tokenResp = $attempt;
                break;
            }
            if (is_array($attempt)) {
                $googleError = trim((string) ($attempt['error_description'] ?? $attempt['error'] ?? ''));
            }
        }
        $claims = $this->decodeJwtClaims(is_array($tokenResp) ? (string) ($tokenResp['id_token'] ?? '') : '');
        $sub = (string) ($claims['sub'] ?? '');
        $iss = (string) ($claims['iss'] ?? '');
        $aud = (string) ($claims['aud'] ?? '');
        if ($sub === '' || !in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true) || $aud !== $clientId) {
            $message = $googleError !== ''
                ? ('Google sign-in failed: ' . $googleError)
                : 'Could not verify your Google sign-in.';
            OAuthPending::put($sessionId, ['status' => 'error', 'error' => $message]);
            return ['error' => $message];
        }

        $nameClaim = (string) ($claims['name'] ?? '');
        $givenClaim = (string) ($claims['given_name'] ?? '');
        $familyClaim = (string) ($claims['family_name'] ?? '');
        $emailClaim = (string) ($claims['email'] ?? '');
        if ($nameClaim === '' && $givenClaim === '' && $familyClaim === '') {
            $accessToken = is_array($tokenResp) ? (string) ($tokenResp['access_token'] ?? '') : '';
            if ($accessToken !== '') {
                $info = $this->httpGetJson('https://www.googleapis.com/oauth2/v3/userinfo', [
                    'Authorization: Bearer ' . $accessToken,
                ]);
                if (is_array($info)) {
                    $nameClaim = (string) ($info['name'] ?? $nameClaim);
                    $givenClaim = (string) ($info['given_name'] ?? $givenClaim);
                    $familyClaim = (string) ($info['family_name'] ?? $familyClaim);
                    $emailClaim = (string) ($info['email'] ?? $emailClaim);
                }
            }
        }
        $name = $this->displayNameFromClaims($nameClaim, $givenClaim, $familyClaim, $emailClaim);

        $existing = User::findByOAuth('google', $sub);
        if ($existing !== null) {
            if ((int) ($existing['is_active'] ?? 1) === 0) {
                $message = 'This account is inactive. Please contact admin to activate it.';
                OAuthPending::put($sessionId, ['status' => 'error', 'error' => $message]);
                return ['error' => $message];
            }
            $userId = (int) $existing['id'];
            User::renameIfPlaceholder($userId, (string) ($existing['name'] ?? ''), $name);
        } else {
            $userId = User::createOAuthUser('google', $sub, $name);
        }

        $appToken = ApiAuth::issue($userId);
        OAuthPending::put($sessionId, ['status' => 'done', 'token' => $appToken]);
        return ['token' => $appToken];
    }

    /**
     * GET /api/auth/facebook/start â€” begin the Expo Go-compatible Facebook
     * login. Same design as Google: Facebook only ever redirects to our public
     * https callback, never the phone.
     */
    public function facebookStart(): void
    {
        $social = App::config('social') ?? [];
        $appId = (string) ($social['facebook_app_id'] ?? '');
        $secret = (string) ($social['facebook_app_secret'] ?? '');
        $redirect = (string) ($social['facebook_redirect'] ?? '');
        $return = trim((string) ($_GET['return'] ?? ''));

        if ($appId === '' || $secret === '' || $redirect === '') {
            $this->socialErrorPage('Facebook sign-in is not configured on the server yet.');
            return;
        }
        if ($return === '' || !preg_match('#^(exp|guidemate|https?)://#i', $return)) {
            $this->socialErrorPage('Invalid return target.');
            return;
        }

        $payload = $this->b64urlEncode((string) json_encode([
            'r' => $return,
            't' => time(),
            'n' => bin2hex(random_bytes(8)),
        ]));
        $sig = $this->b64urlEncode(hash_hmac('sha256', $payload, $secret, true));
        $state = $payload . '.' . $sig;

        $params = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'public_profile',
            'state' => $state,
        ]);
        header('Location: https://www.facebook.com/v18.0/dialog/oauth?' . $params, true, 302);
    }

    /**
     * GET /api/auth/facebook/callback â€” exchange the code, read the app-scoped
     * user id (never the email), load/create the anonymous account, and hand
     * our app token back to the app.
     */
    public function facebookCallback(): void
    {
        $social = App::config('social') ?? [];
        $appId = (string) ($social['facebook_app_id'] ?? '');
        $secret = (string) ($social['facebook_app_secret'] ?? '');
        $redirect = (string) ($social['facebook_redirect'] ?? '');

        $return = $this->verifyState((string) ($_GET['state'] ?? ''), $secret);
        if ($return === null) {
            $this->socialErrorPage('Your sign-in link expired. Please try again.');
            return;
        }
        if (isset($_GET['error'])) {
            $this->redirectToApp($return, ['error' => 'Facebook sign-in was cancelled.']);
            return;
        }

        $code = (string) ($_GET['code'] ?? '');
        if ($code === '' || $appId === '' || $secret === '' || $redirect === '') {
            $this->redirectToApp($return, ['error' => 'Facebook sign-in failed. Please try again.']);
            return;
        }

        $tokenResp = $this->httpGetJson('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirect,
            'client_secret' => $secret,
            'code' => $code,
        ]));
        $accessToken = is_array($tokenResp) ? (string) ($tokenResp['access_token'] ?? '') : '';
        if ($accessToken === '') {
            $this->redirectToApp($return, ['error' => 'Could not verify your Facebook sign-in.']);
            return;
        }

        $me = $this->httpGetJson('https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,name',
            'access_token' => $accessToken,
        ]));
        $fid = is_array($me) ? (string) ($me['id'] ?? '') : '';
        if ($fid === '') {
            $this->redirectToApp($return, ['error' => 'Could not verify your Facebook sign-in.']);
            return;
        }

        $existing = User::findByOAuth('facebook', $fid);
        if ($existing !== null) {
            if ((int) ($existing['is_active'] ?? 1) === 0) {
                $this->redirectToApp($return, ['error' => 'This account is inactive. Please contact admin to activate it.']);
                return;
            }
            $userId = (int) $existing['id'];
            $name = $this->displayNameFromClaims(
                is_array($me) ? (string) ($me['name'] ?? '') : '',
                '',
                '',
                ''
            );
            User::renameIfPlaceholder($userId, (string) ($existing['name'] ?? ''), $name);
        } else {
            $name = $this->displayNameFromClaims(
                is_array($me) ? (string) ($me['name'] ?? '') : '',
                '',
                '',
                ''
            );
            $userId = User::createOAuthUser('facebook', $fid, $name);
        }

        $appToken = ApiAuth::issue($userId);
        $this->redirectToApp($return, ['token' => $appToken]);
    }

    /**
     * Validate a signed state token and return the payload (return URL + session).
     *
     * @return array<string, mixed>|null
     */
    private function decodeOAuthState(string $state, string $secret): ?array
    {
        if ($secret === '' || !str_contains($state, '.')) {
            return null;
        }
        [$payload, $sig] = explode('.', $state, 2);
        $expected = $this->b64urlEncode(hash_hmac('sha256', $payload, $secret, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $data = json_decode($this->b64urlDecode($payload), true);
        if (!is_array($data) || empty($data['r'])) {
            return null;
        }
        if ((int) ($data['t'] ?? 0) < time() - 600) {
            return null; // expired after 10 minutes
        }
        return $data;
    }

    /** Validate a signed state token and return the app's return URL. */
    private function verifyState(string $state, string $secret): ?string
    {
        $data = $this->decodeOAuthState($state, $secret);
        return $data === null ? null : (string) $data['r'];
    }

    /**
     * Store the result for LAN polling, then bounce back into the app.
     *
     * @param array<string, string> $params
     */
    private function finishOAuth(string $return, string $sessionId, array $params): void
    {
        if ($sessionId !== '') {
            if (!empty($params['token'])) {
                OAuthPending::put($sessionId, ['status' => 'done', 'token' => $params['token']]);
            } elseif (!empty($params['error'])) {
                OAuthPending::put($sessionId, ['status' => 'error', 'error' => $params['error']]);
            }
        }
        $this->redirectToApp($return, $params);
    }

    /** Bounce back into the app (its exp:// / guidemate:// return link). */
    private function redirectToApp(string $return, array $params): void
    {
        $sep = str_contains($return, '?') ? '&' : '?';
        $url = $return . $sep . http_build_query($params);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:40px;color:#111;">'
            . '<p>Signing you inâ€¦</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">Tap here if you are not redirected</a></p>'
            . '<script>window.location.href=' . json_encode($url) . ';</script>'
            . '</body></html>';
    }

    private function socialErrorPage(string $message): void
    {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><body style="font-family:sans-serif;padding:40px;color:#111;">'
            . htmlspecialchars($message) . '</body>';
    }

    private function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $claims = json_decode($this->b64urlDecode($parts[1]), true);
        return is_array($claims) ? $claims : [];
    }

    /**
     * Build a friendly display name for a social account. Prefers the provider's
     * full name, then given+family, then a title-cased version of the email's
     * local part (e.g. "mole.kraine" / "mole_kraine" â†’ "Mole Kraine"). Returns
     * '' when nothing usable is available (caller falls back to a label).
     */
    private function displayNameFromClaims(string $name, string $given, string $family, string $email): string
    {
        $name = trim($name);
        if ($name !== '') {
            return $name;
        }
        $combined = trim(trim($given) . ' ' . trim($family));
        if ($combined !== '') {
            return $combined;
        }
        $local = strtolower(trim((string) strstr($email . '@', '@', true)));
        if ($local === '') {
            return '';
        }
        // Split on dots/underscores/hyphens/plus/digits and title-case each word.
        $words = preg_split('/[._\-+0-9]+/', $local) ?: [];
        $words = array_filter(array_map('trim', $words), static fn ($w) => $w !== '');
        if (empty($words)) {
            return '';
        }
        return implode(' ', array_map(static fn ($w) => ucfirst($w), $words));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>|null
     */
    private function httpPostForm(string $url, array $fields): ?array
    {
        $body = http_build_query($fields);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $out = curl_exec($ch);
            $ok = $out !== false && (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) < 500;
            curl_close($ch);
            $out = $ok ? (string) $out : null;
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ]]);
            $res = @file_get_contents($url, false, $ctx);
            $out = $res === false ? null : (string) $res;
        }
        if ($out === null) {
            return null;
        }
        $data = json_decode($out, true);
        return is_array($data) ? $data : null;
    }

    public function forgotPassword(): void
    {
        $body = $this->jsonBody();
        $email = strtolower(trim((string) ($body['email'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Please enter a valid email address.'], 422);
            return;
        }

        $user = User::findByEmail($email);
        if ($user === null) {
            $this->json(['error' => 'We could not find an account with that email address.'], 404);
            return;
        }

        $response = [
            'message' => 'A password reset link has been sent to your email address. Please check your inbox.',
        ];

        if (($user['role'] ?? '') !== 'admin') {
            try {
                $token = PasswordReset::create($email);
                // Build the link from the host the phone actually connected to (e.g. the
                // LAN IP), so the link is reachable from the device rather than localhost.
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $link = rtrim($scheme . '://' . $host . App::basePath(), '/') . '/reset-password/' . $token;
                NotificationService::passwordReset($email, $token, $link);

                $mailConfigured = \App\Core\Mailer::isConfigured(App::config('mail') ?? []);
                $debug = (bool) (App::config('app')['debug'] ?? false);
                // Dev fallback: if no SMTP is configured yet, hand the link back so the
                // reset flow is testable end-to-end without a mail server.
                if ($debug && !$mailConfigured) {
                    $response['dev_reset_url'] = $link;
                }
            } catch (\Throwable $e) {
                error_log("[API Password Reset] Failed to send email to {$email}: " . $e->getMessage());
            }
        }

        $this->json($response);
    }

    public function me(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }
        $this->json(['user' => $this->userPayload($user)]);
    }

    public function logout(): void
    {
        ApiAuth::revoke();
        $this->json(['success' => true]);
    }

    public function changePassword(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $current = (string) ($body['current_password'] ?? '');
        $new = (string) ($body['new_password'] ?? '');

        if ($current === '' || $new === '') {
            $this->json(['error' => 'Current and new password are required.'], 422);
            return;
        }
        if (!password_verify($current, (string) $user['password'])) {
            $this->json(['error' => 'Your current password is incorrect.'], 401);
            return;
        }
        if (strlen($new) < 8) {
            $this->json(['error' => 'New password must be at least 8 characters.'], 422);
            return;
        }

        User::updatePassword((int) $user['id'], $new);
        $this->json(['success' => true]);
    }

    public function deleteAccount(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $password = (string) ($body['password'] ?? '');

        if ($password === '') {
            $this->json(['error' => 'Please enter your password to confirm.'], 422);
            return;
        }
        if (!password_verify($password, (string) $user['password'])) {
            $this->json(['error' => 'Incorrect password.'], 401);
            return;
        }

        ApiAuth::revoke();
        User::delete((int) $user['id']);
        $this->json(['success' => true]);
    }

    /**
     * Update the signed-in user's editable profile fields (name, bio).
     * Only the fields present in the request are changed; the rest are kept.
     */
    public function updateProfile(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();

        $name = array_key_exists('name', $body) ? trim((string) $body['name']) : (string) ($user['name'] ?? '');
        $bio = array_key_exists('bio', $body) ? trim((string) $body['bio']) : (string) ($user['bio'] ?? '');

        if ($name === '') {
            $this->json(['error' => 'Name cannot be empty.'], 422);
            return;
        }
        if (mb_strlen($bio) > 300) {
            $bio = mb_substr($bio, 0, 300);
        }

        User::updateProfile((int) $user['id'], [
            'name' => $name,
            'phone' => (string) ($user['phone'] ?? ''),
            'location' => (string) ($user['location'] ?? ''),
            'bio' => $bio,
        ]);

        $updated = User::find((int) $user['id']) ?? $user;
        $this->json(['user' => $this->userPayload($updated)]);
    }

    /**
     * Store a new profile photo uploaded as multipart/form-data (field "avatar").
     */
    public function uploadAvatar(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $file = $_FILES['avatar'] ?? null;
        if (!Upload::present($file)) {
            $this->json(['error' => 'No image was received. Please pick a photo and try again.'], 422);
            return;
        }

        try {
            $path = Upload::store($file, 'avatars/' . (int) $user['id'], ['jpg', 'jpeg', 'png', 'webp']);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        }

        User::updateAvatar((int) $user['id'], $path);

        $updated = User::find((int) $user['id']) ?? $user;
        $this->json(['user' => $this->userPayload($updated)]);
    }

    public function bookings(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        Booking::reconcileOrphanRefunds();
        $bookings = Booking::forCustomer($userId);
        $this->json([
            'bookings' => array_map(fn (array $b): array => $this->bookingPayload($b, $userId), $bookings),
        ]);
    }

    public function tripMap(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        // Backfill any missing listing coordinates, then load only the bookings
        // that are paid + confirmed/completed (the map is gated behind a booking).
        Listing::syncCoordinatesForBookings($userId);
        $rows = Booking::navigableForCustomer($userId);

        $today = date('Y-m-d');
        $pins = [];
        foreach ($rows as $b) {
            $notes = (string) ($b['notes'] ?? '');
            $isPkg = ($b['category_slug'] ?? '') === 'packages' || str_contains($notes, 'package_booking');
            $date = (string) ($b['booking_date'] ?? '');
            $dateLabel = $date !== '' ? date('M j, Y', strtotime($date)) : '';
            $bId = (int) $b['id'];

            if ($isPkg && str_starts_with(trim($notes), '{')) {
                // Multi-destination pins for package booking
                $pins[] = [
                    'id' => $bId,
                    'title' => '[Meeting Point] Panagsama Beach, Moalboal',
                    'area' => 'Moalboal, Cebu',
                    'address' => 'Panagsama Beach, Moalboal, Cebu',
                    'googleQuery' => 'Panagsama Beach, Moalboal, Cebu, Philippines',
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => 9.9492466,
                    'lng' => 123.3658045,
                    'upcoming' => true,
                    'approximate' => false,
                    'status' => 'confirmed',
                ];
                $pins[] = [
                    'id' => $bId,
                    'title' => '[Activity] Kawasan Falls & Canyoneering',
                    'area' => 'Badian, Cebu',
                    'address' => 'Matutinao, Badian, Cebu',
                    'googleQuery' => 'Kawasan Falls, Badian, Cebu, Philippines',
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => 9.8037,
                    'lng' => 123.3742,
                    'upcoming' => true,
                    'approximate' => false,
                    'status' => 'confirmed',
                ];
                $pins[] = [
                    'id' => $bId,
                    'title' => '[Activity] Moalboal Sardine & Turtle Sanctuary',
                    'area' => 'Moalboal, Cebu',
                    'address' => 'Panagsama Reef, Moalboal, Cebu',
                    'googleQuery' => 'Panagsama Reef, Moalboal, Cebu, Philippines',
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => 9.9515,
                    'lng' => 123.3662,
                    'upcoming' => true,
                    'approximate' => false,
                    'status' => 'confirmed',
                ];
                $pins[] = [
                    'id' => $bId,
                    'title' => '[Hotel Stay] Sea Breeze Oceanfront Resort',
                    'area' => 'Moalboal, Cebu',
                    'address' => 'Panagsama Coastal Road, Moalboal, Cebu',
                    'googleQuery' => 'Sea Breeze Resort, Moalboal, Cebu, Philippines',
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => 9.9542,
                    'lng' => 123.3689,
                    'upcoming' => true,
                    'approximate' => false,
                    'status' => 'confirmed',
                ];
                $pins[] = [
                    'id' => $bId,
                    'title' => '[Vehicle Rental] Yamaha NMAX Station Handover',
                    'area' => 'Moalboal, Cebu',
                    'address' => 'Moalboal Central Hub, Moalboal, Cebu',
                    'googleQuery' => 'Moalboal Municipal Town Center, Cebu, Philippines',
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => 9.9575,
                    'lng' => 123.3995,
                    'upcoming' => true,
                    'approximate' => false,
                    'status' => 'confirmed',
                ];
            } else {
                $address = trim((string) ($b['address'] ?? ''));
                $area = (string) ($b['area'] ?? '');
                $title = (string) ($b['listing_title'] ?? '');
                $googleQuery = $title . ($address !== '' ? ', ' . $address : ', ' . ($area !== '' ? $area : 'Cebu') . ', Philippines');

                $pins[] = [
                    'id' => $bId,
                    'title' => $title,
                    'area' => $area,
                    'address' => $address,
                    'googleQuery' => $googleQuery,
                    'date' => $date,
                    'dateLabel' => $dateLabel,
                    'lat' => (float) $b['latitude'],
                    'lng' => (float) $b['longitude'],
                    'upcoming' => $date !== '' && $date >= $today,
                    'approximate' => !empty($b['approximate']),
                    'status' => (string) ($b['status'] ?? 'pending'),
                ];
            }
        }

        $maps = App::config('maps') ?? [];
        $this->json([
            'bookings' => $pins,
            'mapbox_token' => trim((string) ($maps['mapbox_access_token'] ?? '')),
            'mapillary_token' => trim((string) ($maps['mapillary_access_token'] ?? '')),
        ]);
    }

    /** GET /api/maps â€” public Mapbox token for live traffic (no login). */
    public function mapsConfig(): void
    {
        $maps = App::config('maps') ?? [];
        $this->json([
            'mapbox_token' => trim((string) ($maps['mapbox_access_token'] ?? '')),
        ]);
    }

    /** GET /api/vouchers â€” Sale tab catalog plus which codes this account already used. */
    public function vouchers(): void
    {
        $userId = ApiAuth::id();
        $this->json([
            'vouchers' => PromoCode::catalogForUser($userId),
        ]);
    }

    /** POST /api/vouchers/validate â€” check a code before checkout. */
    public function validateVoucher(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Sign in to redeem a voucher.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $subtotal = max(0, (float) ($body['subtotal'] ?? 0));
        if ($code === '') {
            $this->json(['error' => 'Enter a voucher code.'], 422);
            return;
        }

        $check = PromoCode::validate($code, $subtotal, $userId);
        $this->json($check);
    }

    public function createBooking(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $listingId = (int) ($body['listing_id'] ?? 0);
        $date = trim((string) ($body['booking_date'] ?? ''));
        $rawDates = $body['booking_dates'] ?? null;
        $dates = [];
        if (is_array($rawDates) && $rawDates !== []) {
            foreach ($rawDates as $d) {
                $trimmed = trim((string) $d);
                if ($trimmed !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                    $dates[] = $trimmed;
                }
            }
            $dates = array_values(array_unique($dates));
            sort($dates);
        }
        if ($dates === [] && $date !== '') {
            $dates = [$date];
        }
        if ($dates === []) {
            $this->json(['error' => 'At least one booking date is required.'], 422);
            return;
        }

        $primaryDate = $dates[0];
        $daysCount = count($dates);
        $time = substr(trim((string) ($body['booking_time'] ?? '')), 0, 5);
        $endTime = substr(trim((string) ($body['booking_end_time'] ?? $body['end_time'] ?? '')), 0, 5);
        $guests = max(1, (int) ($body['guests'] ?? 1));
        $notes = trim((string) ($body['notes'] ?? ''));

        // Payment details coming from the mobile checkout (GCash / InstaPay / card).
        $allowedMethods = ['gcash', 'instapay', 'card'];
        $method = strtolower(trim((string) ($body['payment_method'] ?? 'card')));
        if (!in_array($method, $allowedMethods, true)) {
            $method = 'card';
        }
        $paymentReference = trim((string) ($body['payment_reference'] ?? ''));
        $cardLast4 = preg_replace('/\D/', '', (string) ($body['card_last4'] ?? '')) ?? '';

        $listing = Listing::find($listingId);
        if ($listing === null || $listing['status'] !== 'approved') {
            $this->json(['error' => 'Listing not available.'], 404);
            return;
        }

        $isHotelBooking = (string) ($listing['category_slug'] ?? '') === 'hotels' || !empty($body['room_id']);
        if ($isHotelBooking) {
            $roomId = (int) ($body['room_id'] ?? 0);
            $room = $roomId > 0 ? \App\Models\HotelRoom::find($roomId) : null;
            if ($room === null || (int) $room['listing_id'] !== $listingId) {
                // If no specific room passed, find first available room for this hotel
                $availRooms = \App\Models\HotelRoom::forListing($listingId, true);
                if ($availRooms !== []) {
                    $room = $availRooms[0];
                    $roomId = (int) $room['id'];
                } else {
                    $this->json(['error' => 'No rooms currently available for this hotel.'], 422);
                    return;
                }
            }

            if (($room['status'] ?? '') !== \App\Models\HotelRoom::STATUS_AVAILABLE) {
                $this->json(['error' => 'This room is currently ' . str_replace('_', ' ', (string) $room['status']) . ' and cannot be booked.'], 422);
                return;
            }

            $checkIn = trim((string) ($body['check_in_date'] ?? $primaryDate));
            $checkOutInput = trim((string) ($body['check_out_date'] ?? ''));
            $checkOut = $checkOutInput !== '' ? $checkOutInput : (isset($dates[1]) && $dates[1] !== $checkIn ? $dates[count($dates) - 1] : date('Y-m-d', strtotime($checkIn . ' +1 day')));

            if ($checkIn === '' || strtotime($checkIn) === false) {
                $this->json(['error' => 'Please provide a valid arrival (check-in) date.'], 422);
                return;
            }
            if (strtotime($checkIn) < strtotime('today')) {
                $this->json(['error' => 'Check-in date cannot be in the past.'], 422);
                return;
            }
            if ($checkOut === '' || strtotime($checkOut) === false) {
                $this->json(['error' => 'Please provide a valid departure (check-out) date.'], 422);
                return;
            }
            if (strtotime($checkOut) < strtotime($checkIn)) {
                $this->json(['error' => 'Check-out date cannot be earlier than check-in date.'], 422);
                return;
            }
            if (strtotime($checkOut) === strtotime($checkIn)) {
                $this->json(['error' => 'Check-out date must be at least one day after check-in date. Same-day overnight stays are not permitted.'], 422);
                return;
            }

            if ($paymentReference !== '') {
                $existingBooking = Database::first(
                    "SELECT b.* FROM bookings b
                     WHERE b.user_id = ? AND b.listing_id = ? AND b.notes LIKE ?
                     ORDER BY b.id DESC LIMIT 1",
                    [$userId, $listingId, "%Ref: {$paymentReference}%"]
                );
                if ($existingBooking !== null) {
                    $this->json([
                        'booking' => $this->bookingPayload($existingBooking),
                        'deposit_amount' => (float) ($existingBooking['deposit_amount'] ?? 0),
                        'balance_amount' => (float) ($existingBooking['balance_amount'] ?? 0),
                        'payment_instructions' => "Deposit received. Remaining balance is payable at the hotel front desk upon check-in.",
                        'idempotent' => true,
                    ], 200);
                    return;
                }
            }

            if (!\App\Models\HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut)) {
                $this->json(['error' => 'Sorry, this room is already booked or blocked for your selected dates.'], 409);
                return;
            }

            $nights = max(1, (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400));
            $nightlyRate = (float) $room['price_per_night'];
            $totalStay = round($nightlyRate * $nights, 2);
            $depositPct = max(5, min(100, (int) ($room['deposit_percentage'] ?? 20)));
            $depositAmount = round(($totalStay * $depositPct) / 100, 2);
            $balanceAmount = round($totalStay - $depositAmount, 2);

            $parsedTimes = Listing::parseCheckInOutTimes($listing['duration'] ?? null);
            $checkInTime = !empty($time) ? $time . ':00' : $parsedTimes['check_in_time'];
            $checkOutTime = !empty($endTime) ? $endTime . ':00' : $parsedTimes['check_out_time'];
            $checkInLabel = $parsedTimes['check_in_time_label'];
            $checkOutLabel = $parsedTimes['check_out_time_label'];

            $methodLabel = match ($method) {
                'gcash' => 'GCash / e-Wallet QR',
                'instapay' => 'InstaPay QR',
                default => 'Debit/Credit Card',
            };
            $hotelNotes = "Hotel Room: {$room['name']} ({$nights} nights: {$checkIn} to {$checkOut})\n"
                . "Check-in: From {$checkInLabel} Â· Check-out: Until {$checkOutLabel}\n"
                . "Deposit paid via {$methodLabel}: â‚±" . number_format($depositAmount, 2) . "\n"
                . "Remaining balance due at front desk: â‚±" . number_format($balanceAmount, 2);
            if ($paymentReference !== '') {
                $hotelNotes .= ' Â· Ref: ' . $paymentReference;
            }
            if ($notes !== '') {
                $hotelNotes .= "\nGuest notes: " . $notes;
            }

            $bookingId = \App\Models\Booking::createHotelReservation(
                $listingId,
                $roomId,
                $userId,
                $checkIn,
                $checkOut,
                $nights,
                $guests,
                $totalStay,
                $depositAmount,
                $balanceAmount,
                $hotelNotes,
                $checkInTime,
                $checkOutTime
            );

            \App\Models\Payment::create($bookingId, $depositAmount, $method, 'paid');
            \App\Core\Database::run("UPDATE bookings SET hotel_status = 'deposit_paid', payment_stage = 'deposit_paid' WHERE id = ?", [$bookingId]);

            $customer = User::find($userId);
            if ($customer !== null && !empty($customer['email'])) {
                try {
                    NotificationService::bookingAwaitingConfirmation(
                        (string) $customer['email'],
                        (string) ($listing['title'] ?? ''),
                        "{$nights} nights ({$checkIn} to {$checkOut})"
                    );
                } catch (\Throwable $e) {}
            }

            $this->notifyAdminsOfBooking($listing, $userId, $checkIn, $guests, $depositAmount, $methodLabel . ' (Room Deposit)');

            $booking = \App\Models\Booking::find($bookingId);
            $this->json([
                'booking' => $booking !== null ? $this->bookingPayload($booking) : null,
                'deposit_amount' => $depositAmount,
                'balance_amount' => $balanceAmount,
                'payment_instructions' => "Deposit of â‚±" . number_format($depositAmount, 2) . " received. Remaining â‚±" . number_format($balanceAmount, 2) . " is payable at the hotel front desk upon check-in.",
            ], 201);
            return;
        }

        $guideId = (int) ($listing['user_id'] ?? 0);

        $scheduleId = !empty($body['schedule_id']) ? (int) $body['schedule_id'] : 0;
        $sched = null;
        if ($scheduleId > 0) {
            $sched = Database::first(
                'SELECT * FROM listing_schedules WHERE id = ? AND listing_id = ? LIMIT 1',
                [$scheduleId, $listingId]
            );
        }
        if ($sched === null && $primaryDate !== '') {
            if ($time !== '') {
                $sched = Database::first(
                    'SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = ? AND start_time LIKE CONCAT(?, "%") LIMIT 1',
                    [$listingId, $primaryDate, $time]
                );
            }
            if ($sched === null) {
                $sched = Database::first(
                    'SELECT * FROM listing_schedules WHERE listing_id = ? AND schedule_date = ? LIMIT 1',
                    [$listingId, $primaryDate]
                );
            }
        }
        if ($sched !== null) {
            $primaryDate = (string) $sched['schedule_date'];
            $dates = [$primaryDate];
            if ($time === '' && !empty($sched['start_time'])) {
                $time = substr((string) $sched['start_time'], 0, 5);
            }
            if ($endTime === '' && !empty($sched['end_time'])) {
                $endTime = substr((string) $sched['end_time'], 0, 5);
            }
        }
        if ($time === '') {
            $time = '09:00';
        }
        if ($endTime === '') {
            $endTime = '13:00';
        }

        // Server-side duplicate active booking check for the entire tour / package identifier (listing_id)
        $activeBooking = Booking::getActiveBookingForListing($listingId, $userId);
        if ($activeBooking !== null) {
            $statusLabel = ucfirst((string) $activeBooking['status']);
            $dateFormatted = date('M j, Y', strtotime((string) $activeBooking['booking_date']));
            $this->json([
                'error' => "You already have an active booking for this tour (Status: {$statusLabel}, Date: {$dateFormatted}). You can book this tour again once your current experience is marked as completed.",
                'active_booking' => [
                    'id' => (int) $activeBooking['id'],
                    'status' => (string) $activeBooking['status'],
                    'status_label' => $statusLabel,
                    'booking_date' => (string) $activeBooking['booking_date'],
                ]
            ], 409);
            return;
        }

        // Idempotency check for repeated clicks or refreshes with the same payment reference
        if ($paymentReference !== '') {
            $existingRefBooking = Database::first(
                "SELECT b.* FROM bookings b
                 WHERE b.user_id = ? AND b.listing_id = ? AND b.notes LIKE ?
                 ORDER BY b.id DESC LIMIT 1",
                [$userId, $listingId, "%Ref: {$paymentReference}%"]
            );
            if ($existingRefBooking !== null) {
                $this->json([
                    'booking' => $this->bookingPayload($existingRefBooking),
                    'idempotent' => true,
                ], 200);
                return;
            }
        }

        foreach ($dates as $d) {
            if (strtotime($d) < strtotime('today')) {
                $this->json(['error' => "Date {$d} cannot be in the past."], 422);
                return;
            }
            $excludeListingId = ($sched !== null) ? $listingId : null;
            if ($time !== '' && Booking::isGuideSlotBooked($guideId, $d, $time, null, $excludeListingId)) {
                $this->json(['error' => "This guide is already booked on {$d} at {$time}. Please pick another slot."], 409);
                return;
            }
            if ($time === '' && Booking::isGuideDateBooked($guideId, $d)) {
                $this->json(['error' => "This guide is already booked on {$d}. Please pick another day."], 409);
                return;
            }
        }

        // Validate session slots and capacity to prevent overbooking
        if (!$isHotel) {
            $maxCap = (int) (!empty($sched['capacity']) ? $sched['capacity'] : ($listing['capacity'] ?? 10));
            $sessionTime = ($sched && !empty($sched['start_time'])) ? substr((string) $sched['start_time'], 0, 5) : ($time ?: null);
            $targetDate = $sched ? (string) $sched['schedule_date'] : $primaryDate;
            $bookedSum = Database::first(
                'SELECT COALESCE(SUM(guests), 0) AS total_booked
                 FROM bookings
                 WHERE listing_id = ?
                   AND booking_date = ?
                   AND status IN ("pending", "confirmed")
                   AND (? IS NULL OR booking_time LIKE CONCAT(?, "%"))',
                [$listingId, $targetDate, $sessionTime, $sessionTime]
            );
            $currentBooked = (int) ($bookedSum['total_booked'] ?? 0);
            $availableSlots = max(0, $maxCap - $currentBooked);
            if ($availableSlots <= 0) {
                $this->json(['error' => 'This tour session is already fully booked. Please choose another date or session.'], 422);
                return;
            }
            if ($guests > $availableSlots) {
                $this->json(['error' => "This tour session only has {$availableSlots} slot(s) remaining."], 422);
                return;
            }
        }

        $price = Listing::effectivePrice($listing);
        $isPerGroup = str_contains(strtolower((string) ($listing['price_unit'] ?? '')), 'group');
        $subtotal = $isPerGroup ? ($price * $daysCount) : ($price * $guests * $daysCount);
        $total = $subtotal;
        $promoId = null;
        $discount = 0.0;
        $promoCode = strtoupper(trim((string) ($body['promo_code'] ?? '')));
        if ($promoCode !== '') {
            $check = PromoCode::validate($promoCode, $subtotal, $userId);
            if (!$check['valid'] || $check['id'] === null) {
                $this->json(['error' => $check['message']], 422);
                return;
            }
            $discount = (float) $check['discount'];
            $promoId = (int) $check['id'];
            $total = max(0, $subtotal - $discount);
        }

        // Keep a human-readable note of how the customer paid so it shows in admin.
        $methodLabel = match ($method) {
            'gcash' => 'GCash / e-Wallet QR',
            'instapay' => 'InstaPay QR',
            default => 'Debit/Credit Card',
        };
        $datesSummary = $daysCount > 1 ? " ({$daysCount} days: " . implode(', ', $dates) . ")" : " ({$primaryDate})";
        $paymentNote = 'Paid via ' . $methodLabel . $datesSummary;
        if ($cardLast4 !== '') {
            $paymentNote .= ' ****' . substr($cardLast4, -4);
        }
        if ($paymentReference !== '') {
            $paymentNote .= ' Â· Ref: ' . $paymentReference;
        }
        $fullNotes = trim($notes === '' ? $paymentNote : ($notes . "\n" . $paymentNote));

        $bookingId = Booking::create(
            $listingId,
            $userId,
            $primaryDate,
            $guests,
            $total,
            $fullNotes,
            $time !== '' ? $time : null,
            $dates,
            $endTime !== '' ? $endTime : null
        );
        if ($promoId !== null) {
            Booking::attachPromo($bookingId, $promoId, $discount);
            PromoCode::incrementUse($promoId);
            PromoCode::recordRedemption($userId, $promoCode, $bookingId);
        }
        Payment::create($bookingId, $total, $method, 'paid');
        // Stays pending until the listing owner (guide / hotel) confirms it.

        $customer = User::find($userId);
        if ($customer !== null && !empty($customer['email'])) {
            try {
                $dateLabel = $daysCount > 1 ? "{$daysCount} days (" . implode(', ', $dates) . ")" : $primaryDate;
                NotificationService::bookingAwaitingConfirmation(
                    (string) $customer['email'],
                    (string) ($listing['title'] ?? ''),
                    $dateLabel
                );
            } catch (\Throwable $e) {
                // Notifications are best-effort.
            }
        }

        // Notify the listing owner (and admins) so they can confirm or decline.
        $this->notifyAdminsOfBooking($listing, $userId, $date, $guests, $total, $methodLabel);

        $booking = Booking::find($bookingId);
        $this->json([
            'booking' => $booking !== null ? $this->bookingPayload($booking) : null,
        ], 201);
    }

    // â”€â”€ Chat / messaging â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /** GET /api/messages â€” conversation list (inbox or archived). */
    public function conversations(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $archived = (string) $this->input('archived', '') !== '';
        $rows = $archived
            ? Message::archivedConversations($userId)
            : Message::conversations($userId);

        $conversations = array_map(static function (array $c): array {
            return [
                'partner_id' => (int) $c['partner_id'],
                'partner_name' => (string) ($c['partner_name'] ?? ''),
                'partner_avatar' => api_img_src((string) ($c['partner_avatar'] ?? ''), 'user' . $c['partner_id']),
                'last_body' => (string) ($c['last_body'] ?? ''),
                'last_at' => (string) ($c['last_at'] ?? ''),
                'unread' => (int) ($c['unread'] ?? 0),
                'is_pinned' => (bool) ($c['is_pinned'] ?? false),
            ];
        }, $rows);

        $this->json([
            'conversations' => $conversations,
            'unread_total' => Message::unreadCount($userId),
        ]);
    }

    /** GET /api/messages/thread?partner=&lang= â€” full conversation. */
    public function thread(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $partnerId = (int) $this->input('partner', 0);
        $partner = $partnerId > 0 ? User::find($partnerId) : null;
        if ($partner === null || $partnerId === $userId) {
            $this->json(['error' => 'Conversation not found.'], 404);
            return;
        }

        $lang = $this->langParam();
        Message::markRead($userId, $partnerId);

        $rows = Message::thread($userId, $partnerId);
        // Only translate the most recent messages to keep the first load fast;
        // cached translations make later loads instant.
        $translateFrom = max(0, count($rows) - 60);
        $messages = [];
        foreach ($rows as $i => $m) {
            $messages[] = $this->messagePayload($m, $userId, $i >= $translateFrom ? $lang : null);
        }

        $settings = ConversationSetting::forPair($userId, $partnerId);
        $this->json([
            'partner' => $this->chatPartnerPayload($partner),
            'messages' => $messages,
            'archived' => (bool) ($settings['is_archived'] ?? false),
        ]);
    }

    /** GET /api/messages/poll?partner=&since=&lang= â€” new messages + receipts. */
    public function pollThread(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $partnerId = (int) $this->input('partner', 0);
        $partner = $partnerId > 0 ? User::find($partnerId) : null;
        if ($partner === null || $partnerId === $userId) {
            $this->json(['error' => 'Conversation not found.'], 404);
            return;
        }

        $lang = $this->langParam();
        $since = (int) $this->input('since', 0);
        $newRows = Message::since($userId, $partnerId, $since);
        if ($newRows !== []) {
            Message::markRead($userId, $partnerId);
        }

        $new = array_map(fn (array $m): array => $this->messagePayload($m, $userId, $lang), $newRows);
        $statuses = array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'status' => 'read'],
            Message::readReceipts($userId, $partnerId)
        );

        $this->json([
            'new' => $new,
            'statuses' => $statuses,
            'partner_online' => User::isOnline($partnerId),
        ]);
    }

    /** POST /api/messages/send â€” body { partner_id, body, listing_id? }. */
    public function sendMessage(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $partnerId = (int) ($body['partner_id'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        $listingId = isset($body['listing_id']) ? (int) $body['listing_id'] : 0;

        if ($partnerId <= 0 || $partnerId === $userId || $text === '') {
            $this->json(['error' => 'A recipient and message are required.'], 422);
            return;
        }
        if (User::find($partnerId) === null) {
            $this->json(['error' => 'Recipient not found.'], 404);
            return;
        }

        // Replying to an archived chat brings it back to the inbox.
        $settings = ConversationSetting::forPair($userId, $partnerId);
        if ($settings !== null && (bool) ($settings['is_archived'] ?? false)) {
            ConversationSetting::setArchived($userId, $partnerId, false);
        }

        $id = Message::send($userId, $partnerId, $text, $listingId > 0 ? $listingId : null);

        $this->json([
            'message' => [
                'id' => $id,
                'body' => $text,
                'mine' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 'sent',
            ],
        ], 201);
    }

    /** POST /api/messages/archive â€” body { partner_id }. */
    public function archiveConversation(): void
    {
        $this->setArchivedState(true);
    }

    /** POST /api/messages/unarchive â€” body { partner_id }. */
    public function unarchiveConversation(): void
    {
        $this->setArchivedState(false);
    }

    /** POST /api/messages/delete â€” body { partner_id }. */
    public function deleteConversation(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }
        $partnerId = (int) ($this->jsonBody()['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            $this->json(['error' => 'partner_id is required.'], 422);
            return;
        }
        Message::deleteBetween($userId, $partnerId);
        ConversationSetting::remove($userId, $partnerId);
        $this->json(['success' => true]);
    }

    /** GET /api/guides â€” approved tour guides available to message. */
    public function guides(): void
    {
        $userId = ApiAuth::id();
        $guides = [];
        foreach (User::guides('approved') as $u) {
            if ($userId !== null && (int) $u['id'] === $userId) {
                continue;
            }
            $payload = $this->chatPartnerPayload($u);
            $stats = Booking::guideRatingStats((int) $u['id']);
            $payload['rating'] = (float) ($stats['avg_rating'] ?? 0);
            $payload['review_count'] = (int) ($stats['review_count'] ?? 0);
            $payload['completed_tours'] = (int) ($stats['completed'] ?? 0);
            // Guide is "on a tour" while they have a confirmed booking today;
            // once it's completed (or the day passes) they're available again.
            $payload['available'] = !Booking::isGuideBusyToday((int) $u['id']);
            $guides[] = $payload;
        }
        // Available guides first, then best-rated and most-experienced.
        usort($guides, static function (array $a, array $b): int {
            return [$b['available'], $b['rating'], $b['completed_tours']]
                <=> [$a['available'], $a['rating'], $a['completed_tours']];
        });
        $this->json(['guides' => $guides]);
    }

    private function setArchivedState(bool $archived): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }
        $partnerId = (int) ($this->jsonBody()['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            $this->json(['error' => 'partner_id is required.'], 422);
            return;
        }
        ConversationSetting::setArchived($userId, $partnerId, $archived);
        $this->json(['success' => true]);
    }

    public function favorites(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $listings = Favorite::forUser($userId);
        $this->json([
            'listings' => array_map([$this, 'listingPayload'], $listings),
        ]);
    }

    public function toggleFavorite(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $listingId = (int) ($body['listing_id'] ?? 0);
        if ($listingId <= 0) {
            $this->json(['error' => 'listing_id is required.'], 422);
            return;
        }

        $favorited = Favorite::toggle($userId, $listingId);
        $this->json(['favorited' => $favorited]);
    }

    public function rentalUnits(): void
    {
        $this->json([
            'vehicles' => array_map(
                static fn (array $v): array => [
                    'id' => (string) $v['id'],
                    'slug' => (string) ($v['slug'] ?? ''),
                    'name' => (string) $v['name'],
                    'make' => (string) ($v['make'] ?? ''),
                    'model' => (string) ($v['model'] ?? ''),
                    'year' => (int) ($v['year'] ?? 0),
                    'type' => (string) $v['type'],
                    'price_per_day' => (float) $v['price_per_day'],
                    'specs' => $v['specs'],
                    'shop' => (string) $v['shop'],
                    'provider_id' => (int) ($v['provider_id'] ?? 0),
                    'provider_name' => (string) ($v['provider_name'] ?? $v['shop']),
                    'location' => (string) $v['location'],
                    'address' => (string) ($v['address'] ?? ''),
                    'description' => (string) ($v['description'] ?? ''),
                    'status' => (string) ($v['status'] ?? 'approved'),
                    'is_available' => (bool) ($v['is_available'] ?? true),
                    'rating' => (float) $v['rating'],
                    'image' => (string) ($v['image'] ?? ''),
                    'images' => (array) ($v['images'] ?? [$v['image']]),
                ],
                RentalRequest::vehicleCatalog()
            ),
        ]);
    }

    /**
     * GET /api/rentals/{id} â€” full vehicle details for mobile and web.
     */
    public function rentalDetail(string $id): void
    {
        $vehicle = is_numeric($id) ? Vehicle::find((int) $id) : Vehicle::findBySlug($id);
        if ($vehicle === null) {
            $this->json(['error' => 'Vehicle listing not found.'], 404);
            return;
        }

        $providerUser = User::find((int) $vehicle['user_id']);
        $providerName = $providerUser['name'] ?? ($vehicle['provider_name'] ?? 'Rental Partner');
        $providerAvatar = !empty($providerUser['avatar'])
            ? api_img_src($providerUser['avatar'], 'user' . $vehicle['user_id'])
            : null;
        $isOnline = User::isOnline((int) $vehicle['user_id']);

        $mainImg = api_img_src((string) ($vehicle['image'] ?? ''), 'vehicle' . $vehicle['id']);
        $gallery = Vehicle::gallery((int) $vehicle['id']);
        $images = [];
        if ($mainImg !== '') {
            $images[] = $mainImg;
        }
        foreach ($gallery as $g) {
            $gUrl = api_img_src((string) ($g['image_path'] ?? ''), 'vehicle' . $vehicle['id'] . '-' . $g['id']);
            if (!in_array($gUrl, $images, true)) {
                $images[] = $gUrl;
            }
        }
        if ($images === [] && $mainImg !== '') {
            $images[] = $mainImg;
        }

        $specs = Vehicle::parseSpecs($vehicle['specs'] ?? null);
        $providerStats = RentalReview::providerStats((int) $vehicle['user_id']);
        $vehicleStats = RentalReview::vehicleStats((string) $vehicle['id']);
        $displayRating = $vehicleStats['rating'] > 0.0 ? $vehicleStats['rating'] : $providerStats['rating'];

        $this->json([
            'vehicle' => [
                'id' => (string) $vehicle['id'],
                'slug' => (string) $vehicle['slug'],
                'name' => (string) $vehicle['name'],
                'make' => (string) $vehicle['make'],
                'model' => (string) $vehicle['model'],
                'year' => (int) $vehicle['year'],
                'type' => (string) $vehicle['type'],
                'price_per_day' => (float) $vehicle['price_per_day'],
                'specs' => $specs,
                'shop' => (string) $providerName,
                'location' => (string) $vehicle['location'],
                'address' => (string) ($vehicle['address'] ?? ''),
                'description' => (string) ($vehicle['description'] ?? ''),
                'status' => (string) ($vehicle['status'] ?? 'approved'),
                'is_available' => (bool) ($vehicle['is_available'] ?? true),
                'rating' => (float) $displayRating,
                'reviews_count' => (int) $vehicleStats['reviews_count'],
                'image' => $mainImg,
                'images' => $images,
                'provider' => [
                    'id' => (int) $vehicle['user_id'],
                    'name' => (string) $providerName,
                    'email' => (string) ($providerUser['email'] ?? ''),
                    'avatar' => $providerAvatar,
                    'online' => $isOnline,
                    'role' => (string) ($providerUser['role'] ?? 'rental_provider'),
                    'rating' => (float) $providerStats['rating'],
                    'reviews_count' => (int) $providerStats['reviews_count'],
                    'response_time' => 'Typically replies in 15 mins',
                    'completed_rentals' => (int) $providerStats['completed_rentals'],
                ],
                'requirements' => [
                    "Valid Philippine Driver's License or International Driving Permit",
                    "Government-issued ID or Passport held for the duration of the rental",
                    "Full-to-full fuel policy (unit is provided full and returned full)",
                    "Certified helmet use mandatory at all times for riders and passengers",
                    "Deposit of valid physical ID safely held by the partner until vehicle return",
                ],
            ],
        ]);
    }

    /**
     * POST /api/rentals â€” reserve a vehicle with FULL up-front payment.
     * Sent as multipart/form-data so the tourist can attach a valid ID that is
     * held for the rental owner until the unit is returned.
     */
    public function createRental(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $user = User::find($userId);
        if ($user === null) {
            $this->json(['error' => 'User not found.'], 404);
            return;
        }

        $vehicleId = trim((string) ($_POST['vehicle_id'] ?? ''));
        $pickupDate = trim((string) ($_POST['pickup_date'] ?? ''));
        $phone = trim((string) ($_POST['customer_phone'] ?? ''));

        if ($vehicleId === '' || $pickupDate === '') {
            $this->json(['error' => 'vehicle_id and pickup_date are required.'], 422);
            return;
        }

        $vehicle = null;
        foreach (RentalRequest::vehicleCatalog() as $item) {
            if ($item['id'] === $vehicleId) {
                $vehicle = $item;
                break;
            }
        }
        if ($vehicle === null) {
            $this->json(['error' => 'Unknown vehicle.'], 404);
            return;
        }

        // â”€â”€ Verification: a valid ID is required and held for the owner â”€â”€
        $idType = trim((string) ($_POST['id_type'] ?? ''));
        $idNumber = trim((string) ($_POST['id_number'] ?? ''));
        if ($idType === '') {
            $this->json(['error' => 'Please choose which valid ID you are presenting.'], 422);
            return;
        }
        $idFile = $_FILES['id_document'] ?? null;
        if (!Upload::present(is_array($idFile) ? $idFile : null)) {
            $this->json(['error' => 'A photo of your valid ID is required to reserve a unit.'], 422);
            return;
        }
        try {
            $idPath = Upload::store($idFile, 'rentals/ids/' . $userId);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        }

        // â”€â”€ Payment: the tourist pays the whole amount to reserve â”€â”€
        $method = strtolower(trim((string) ($_POST['payment_method'] ?? '')));
        if (!in_array($method, ['gcash', 'instapay', 'card'], true)) {
            $this->json(['error' => 'Please choose a valid payment method.'], 422);
            return;
        }
        $reference = trim((string) ($_POST['payment_reference'] ?? ''));
        if ($method === 'card') {
            $last4 = preg_replace('/\D/', '', (string) ($_POST['card_last4'] ?? ''));
            if (strlen((string) $last4) !== 4) {
                $this->json(['error' => 'Enter the last 4 digits of your card.'], 422);
                return;
            }
            $reference = 'CARD-' . $last4;
        } elseif (mb_strlen($reference) < 4) {
            $this->json(['error' => 'Enter the payment reference number from your app.'], 422);
            return;
        }

        if ($reference !== '') {
            $existingRental = Database::first(
                "SELECT * FROM rental_requests WHERE user_id = ? AND vehicle_id = ? AND payment_reference = ? ORDER BY id DESC LIMIT 1",
                [$userId, $vehicleId, $reference]
            );
            if ($existingRental !== null) {
                $this->json([
                    'request' => $this->rentalPayload($existingRental),
                    'idempotent' => true,
                ], 200);
                return;
            }
        }

        $id = RentalRequest::create($userId, [
            'vehicle_id' => $vehicleId,
            'vehicle_name' => (string) ($_POST['vehicle_name'] ?? $vehicle['name']),
            'vehicle_type' => (string) ($_POST['vehicle_type'] ?? $vehicle['type']),
            'shop_name' => (string) ($_POST['shop_name'] ?? $vehicle['shop']),
            'location' => (string) ($_POST['location'] ?? $vehicle['location']),
            'pickup_date' => $pickupDate,
            'rental_days' => (int) ($_POST['rental_days'] ?? 1),
            'price_per_day' => (float) ($_POST['price_per_day'] ?? $vehicle['price_per_day']),
            'customer_name' => (string) $user['name'],
            'customer_email' => (string) $user['email'],
            'customer_phone' => $phone,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'payment_status' => 'paid',
            'payment_method' => $method,
            'payment_reference' => $reference,
            'id_document' => $idPath,
            'id_type' => $idType,
            'id_number' => $idNumber,
        ]);

        $rental = RentalRequest::find($id);
        try {
            NotificationService::rentalAwaitingConfirmation(
                (string) $user['email'],
                (string) ($vehicle['name'] ?? 'your rental'),
                $pickupDate
            );
            $this->notifyRentalPartnersOfRequest($user, $vehicle, $pickupDate, (float) ($rental['total_amount'] ?? 0));
        } catch (\Throwable $e) {
            // Notifications are best-effort; never block the reservation on them.
        }
        $this->json([
            'request' => $rental !== null ? $this->rentalPayload($rental) : ['id' => $id, 'status' => 'pending'],
        ], 201);
    }

    /**
     * GET /api/rentals/mine â€” the signed-in tourist's reservations.
     */
    public function myRentals(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $rentals = array_map(
            fn (array $r): array => $this->rentalPayload($r),
            RentalRequest::forCustomer($userId)
        );
        $this->json(['rentals' => $rentals]);
    }

    /**
     * POST /api/rentals/report â€” report a problem with a reserved unit so the
     * rental owner can review and refund. JSON body.
     */

    /**
     * POST /api/rentals/reviews â€” submit or update rating and feedback for a completed rental.
     */
    public function submitRentalReview(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $rentalId = (int) ($body['rental_id'] ?? 0);
        $rating = (int) ($body['rating'] ?? 0);
        $feedback = trim((string) ($body['feedback'] ?? ''));

        if ($rentalId <= 0) {
            $this->json(['error' => 'Rental ID is required.'], 422);
            return;
        }

        $rental = RentalRequest::find($rentalId);
        if ($rental === null || (int) $rental['user_id'] !== $userId) {
            $this->json(['error' => 'Rental reservation not found or access denied.'], 404);
            return;
        }

        // Must be completed!
        if (($rental['status'] ?? '') !== 'completed') {
            $this->json([
                'error' => 'You can only rate and review a rental after it has been marked as Completed by the provider.'
            ], 403);
            return;
        }

        if ($rating < 1 || $rating > 5) {
            $this->json(['error' => 'Please select a valid rating between 1 and 5 stars.'], 422);
            return;
        }

        if (mb_strlen($feedback) < 3) {
            $this->json(['error' => 'Please provide written feedback with at least 3 characters.'], 422);
            return;
        }

        // Resolve provider ID
        $providerId = 0;
        $vehicleId = $rental['vehicle_id'] ?? null;
        if ($vehicleId !== null && is_numeric($vehicleId)) {
            $vehicle = Vehicle::find((int) $vehicleId);
            if ($vehicle !== null && !empty($vehicle['user_id'])) {
                $providerId = (int) $vehicle['user_id'];
            }
        }
        if ($providerId <= 0) {
            $shopName = trim((string) ($rental['shop_name'] ?? ''));
            $partnerUser = Database::first("SELECT id FROM users WHERE name = ? AND role = 'rental_provider'", [$shopName]);
            if ($partnerUser !== null) {
                $providerId = (int) $partnerUser['id'];
            }
        }
        if ($providerId <= 0) {
            $providerId = 1;
        }

        try {
            $review = RentalReview::createOrUpdate(
                $rentalId,
                $userId,
                $providerId,
                $vehicleId ? (string) $vehicleId : null,
                $rating,
                $feedback
            );

            $stats = RentalReview::providerStats($providerId);

            $this->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'review' => [
                    'id' => (int) $review['id'],
                    'rental_id' => (int) $review['rental_id'],
                    'rating' => (int) $review['rating'],
                    'feedback' => (string) $review['feedback'],
                    'created_at' => (string) $review['created_at'],
                    'updated_at' => (string) $review['updated_at'],
                ],
                'provider_stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/rentals/reviews?rental_id=... â€” fetch a review by rental ID.
     */
    public function getRentalReview(): void
    {
        $rentalId = (int) $this->input('rental_id', 0);
        if ($rentalId <= 0) {
            $this->json(['error' => 'Rental ID is required.'], 422);
            return;
        }

        $review = RentalReview::findForRental($rentalId);
        $this->json([
            'review' => $review !== null ? [
                'id' => (int) $review['id'],
                'rental_id' => (int) $review['rental_id'],
                'rating' => (int) $review['rating'],
                'feedback' => (string) $review['feedback'],
                'created_at' => (string) $review['created_at'],
                'updated_at' => (string) $review['updated_at'],
            ] : null,
        ]);
    }

    public function reportRental(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $rentalId = (int) ($_POST['rental_id'] ?? $body['rental_id'] ?? 0);
        $type = trim((string) ($_POST['problem_type'] ?? $body['problem_type'] ?? ''));
        $message = trim((string) ($_POST['description'] ?? $body['description'] ?? ''));

        $rental = $rentalId > 0 ? RentalRequest::find($rentalId) : null;
        if ($rental === null || (int) $rental['user_id'] !== $userId) {
            $this->json(['error' => 'Reservation not found.'], 404);
            return;
        }
        if (($rental['payment_status'] ?? 'unpaid') !== 'paid') {
            $this->json(['error' => 'You can only report a problem on a paid reservation.'], 403);
            return;
        }
        if (($rental['report_status'] ?? 'none') !== 'none') {
            $this->json(['error' => 'You already reported a problem for this reservation.'], 409);
            return;
        }
        if (!isset(RentalRequest::REPORT_TYPES[$type])) {
            $this->json(['error' => 'Please choose a valid problem type.'], 422);
            return;
        }
        if (mb_strlen($message) < 20) {
            $this->json(['error' => 'Please describe the problem in at least 20 characters.'], 422);
            return;
        }

        RentalRequest::report($rentalId, $type, $message);

        // Attach any photo evidence (field "evidence" or "evidence[]")
        foreach ($this->normalizeFiles($_FILES['evidence'] ?? null) as $file) {
            $path = Upload::image($file, 'rentals/reports');
            if ($path !== null) {
                RentalReportFile::add($rentalId, $path, (string) ($file['name'] ?? ''));
            }
        }

        $fresh = RentalRequest::find($rentalId);
        $this->json(['request' => $fresh !== null ? $this->rentalPayload($fresh) : null]);
    }

    /**
     * POST /api/rentals/{id}/report/evidence - upload additional evidence photo for a rental report.
     */
    public function uploadRentalReportEvidence(int $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $rental = RentalRequest::find($id);
        if ($rental === null || (int) $rental['user_id'] !== $userId) {
            $this->json(['error' => 'Reservation not found.'], 404);
            return;
        }

        $attached = 0;
        foreach ($this->normalizeFiles($_FILES['evidence'] ?? null) as $file) {
            $path = Upload::image($file, 'rentals/reports');
            if ($path !== null) {
                RentalReportFile::add($id, $path, (string) ($file['name'] ?? ''));
                $attached++;
            }
        }

        $this->json(['success' => true, 'files_attached' => $attached]);
    }

    /**
     * Shape a rental row for the mobile app. The ID document is intentionally
     * NOT exposed here â€” it is held only for the rental owner.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function rentalPayload(array $r): array
    {
        $reportStatus = (string) ($r['report_status'] ?? 'none');
        $paymentStatus = (string) ($r['payment_status'] ?? 'unpaid');
        $status = (string) $r['status'];

        $review = RentalReview::findForRental((int) $r['id']);
        $reportFiles = RentalReportFile::forRental((int) $r['id']);

        $today = date('Y-m-d');
        $pickupDate = (string) ($r['pickup_date'] ?? '');
        $rentalDays = max(1, (int) ($r['rental_days'] ?? 1));
        $returnDate = $pickupDate !== '' ? date('Y-m-d', strtotime($pickupDate . " +{$rentalDays} days")) : '';
        $isCompleted = ($status === 'completed' || $status === 'cancelled' || $status === 'rejected' || $status === 'refunded');
        if (!$isCompleted && $returnDate !== '' && $returnDate < $today) {
            $isCompleted = true;
        }

        return [
            'id' => (int) $r['id'],
            'vehicle_id' => (string) ($r['vehicle_id'] ?? ''),
            'vehicle_name' => (string) $r['vehicle_name'],
            'vehicle_type' => (string) $r['vehicle_type'],
            'shop_name' => (string) $r['shop_name'],
            'location' => (string) ($r['location'] ?? ''),
            'pickup_date' => $pickupDate,
            'return_date' => $returnDate,
            'rental_days' => $rentalDays,
            'total_amount' => (float) $r['total_amount'],
            'status' => $status,
            'status_label' => RentalRequest::statusLabel($status),
            'is_completed' => $isCompleted,
            'payment_status' => $paymentStatus,
            'payment_method' => (string) ($r['payment_method'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'report_status' => $reportStatus,
            'report_type' => (string) ($r['report_type'] ?? ''),
            'report_label' => RentalRequest::reportTypeLabel($r['report_type'] ?? null),
            'report_message' => (string) ($r['report_message'] ?? ''),
            'report_photos' => array_map(function ($f) {
                return (string) ($f['file_path'] ?? '');
            }, $reportFiles),
            'owner_report_note' => (string) ($r['owner_report_note'] ?? ''),
            'can_report' => $paymentStatus === 'paid' && $reportStatus === 'none' && $status !== 'refunded',
            'can_review' => $status === 'completed',
            'has_reviewed' => $review !== null,
            'cancellation' => $this->computeCancellationPolicy($r, 'rental'),
            'review' => $review !== null ? [
                'id' => (int) $review['id'],
                'rating' => (int) $review['rating'],
                'feedback' => (string) $review['feedback'],
                'created_at' => (string) $review['created_at'],
                'updated_at' => (string) $review['updated_at'],
            ] : null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    /**
     * Accept app feedback from any tourist (signed in or not). When a valid
     * bearer token is present we attach the account so admins can follow up.
     */
    public function submitFeedback(): void
    {
        $body = $this->jsonBody();

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            $this->json(['error' => 'Please enter your feedback before sending.'], 422);
            return;
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $rating = (int) ($body['rating'] ?? 0);
        if ($rating < 0 || $rating > 5) {
            $rating = 0;
        }

        $category = strtolower(trim((string) ($body['category'] ?? 'general')));
        if (!in_array($category, Feedback::CATEGORIES, true)) {
            $category = 'general';
        }

        $user = ApiAuth::user();
        $name = trim((string) ($body['name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if ($user !== null) {
            $name = (string) $user['name'];
            $email = (string) $user['email'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Please enter a valid email address.'], 422);
            return;
        }

        $id = Feedback::create([
            'user_id' => $user !== null ? (int) $user['id'] : null,
            'name' => $name,
            'email' => $email,
            'rating' => $rating,
            'category' => $category,
            'message' => $message,
        ]);

        $this->json([
            'feedback' => ['id' => $id],
            'message' => 'Thank you! Your feedback helps us improve GuideMate.',
        ], 201);
    }

    /**
     * List the signed-in tourist's own feedback, including its review status,
     * so they can tell whether an admin has looked at it.
     */
    public function myFeedback(): void
    {
        $user = ApiAuth::user();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $items = array_map(static function (array $f): array {
            return [
                'id' => (int) $f['id'],
                'rating' => (int) $f['rating'],
                'category' => (string) $f['category'],
                'message' => (string) $f['message'],
                'status' => (string) $f['status'],
                'created_at' => (string) $f['created_at'],
            ];
        }, Feedback::forUser((int) $user['id']));

        $this->json(['feedback' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sendCors(): void
    {
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
    }

    /**
     * @param array<string, mixed> $category
     * @return array<string, mixed>
     */
    private function categoryPayload(array $category): array
    {
        return [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'icon' => (string) ($category['icon'] ?? ''),
            'listing_count' => (int) ($category['listing_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $listing
     * @return array<string, mixed>
     */
    /**
     * The current user's favorited listing ids, resolved once per request.
     * Returns an empty list for guests.
     *
     * @return array<int, int>
     */
    private function favoriteIdSet(): array
    {
        if ($this->favoriteIds === null) {
            $userId = ApiAuth::id();
            $this->favoriteIds = $userId !== null ? Favorite::idsForUser($userId) : [];
        }
        return $this->favoriteIds;
    }

    private function listingPayload(array $listing, bool $detailed = false): array
    {
        $isHotel = ($listing['category_slug'] ?? '') === 'hotels' || (int) ($listing['category_id'] ?? 0) === 3;
        $lowestRoomPrice = null;

        if ($isHotel) {
            $lowestRoomPrice = \App\Models\HotelRoom::getLowestRoomPrice((int) $listing['id']);
            $price = $lowestRoomPrice ?? 0.0;
        } else {
            $price = Listing::effectivePrice($listing);
        }

        $parsedTimes = Listing::parseCheckInOutTimes($listing['duration'] ?? null);

        $payload = [
            'id' => (int) $listing['id'],
            'title' => (string) $listing['title'],
            'slug' => (string) $listing['slug'],
            'summary' => (string) ($listing['summary'] ?? ''),
            'area' => (string) ($listing['area'] ?? ''),
            'address' => (string) ($listing['address'] ?? ''),
            'latitude' => isset($listing['latitude']) && $listing['latitude'] !== null && $listing['latitude'] !== '' ? (float) $listing['latitude'] : null,
            'longitude' => isset($listing['longitude']) && $listing['longitude'] !== null && $listing['longitude'] !== '' ? (float) $listing['longitude'] : null,
            'price' => $price,
            'lowest_room_price' => $lowestRoomPrice,
            'price_unit' => $isHotel ? 'per night' : (string) ($listing['price_unit'] ?? 'per group'),
            'capacity' => (int) (!empty($listing['capacity']) ? $listing['capacity'] : 10),
            'total_capacity' => (int) (!empty($listing['capacity']) ? $listing['capacity'] : 10),
            'currency' => 'PHP',
            'rating' => round((float) ($listing['avg_rating'] ?? 0), 1),
            'review_count' => (int) ($listing['review_count'] ?? 0),
            'category' => (string) ($listing['category_name'] ?? ''),
            'category_slug' => (string) ($listing['category_slug'] ?? ''),
            'image' => api_img_src((string) ($listing['cover_image'] ?? ''), 'listing' . $listing['id']),
            'photos' => array_map(
                static fn (string $p): string => api_img_src($p, 'listing' . $listing['id']),
                \App\Models\Listing::getPhotos($listing)
            ),
            'featured' => (bool) ($listing['is_featured'] ?? false),
            'duration' => $isHotel && !empty($listing['duration'])
                ? (string) $listing['duration']
                : ($isHotel ? 'Check-in 2:00 PM / Check-out 12:00 PM' : (string) ($listing['duration'] ?? '')),
            'check_in_time' => $isHotel ? $parsedTimes['check_in_time'] : null,
            'check_out_time' => $isHotel ? $parsedTimes['check_out_time'] : null,
            'check_in_time_label' => $isHotel ? $parsedTimes['check_in_time_label'] : null,
            'check_out_time_label' => $isHotel ? $parsedTimes['check_out_time_label'] : null,
            'check_in_out_label' => $isHotel ? $parsedTimes['check_in_out_label'] : null,
            'check_in_schedule_label' => $isHotel ? $parsedTimes['summary_label'] : null,
            'owner_name' => (string) ($listing['owner_name'] ?? ''),
            'owner_id' => (int) ($listing['user_id'] ?? 0),
            'owner_role' => (string) ($listing['owner_role'] ?? ''),
            'favorited' => in_array((int) $listing['id'], $this->favoriteIdSet(), true),
            // Freshly published (within the last 7 days) â€” the app shows a
            // "New" badge on these cards so travelers can spot new tours.
            'is_new' => isset($listing['created_at'])
                && strtotime((string) $listing['created_at']) >= strtotime('-7 days'),
        ];

        if ($detailed) {
            $payload['description'] = (string) ($listing['description'] ?? '');
            $payload['included'] = (string) ($listing['included'] ?? '');
            $payload['not_included'] = (string) ($listing['not_included'] ?? '');
        }

        return $payload;
    }

    /**
     * Build the "nearest hotel" suggestion for an experience: the closest
     * approved hotel, with straight-line distance and a Google Maps directions
     * link from the experience to the hotel.
     *
     * @param array<string, mixed> $listing
     * @return array<string, mixed>|null
     */
    private function nearestHotelPayload(array $listing): ?array
    {
        // Prefer the listing's own stored coordinates; only fall back to
        // area-based geocoding when they are missing (e.g. older listings).
        $lat = $listing['latitude'] ?? null;
        $lng = $listing['longitude'] ?? null;
        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
            $tourLat = (float) $lat;
            $tourLng = (float) $lng;
        } else {
            $coords = Geo::forListing($listing);
            if ($coords === null) {
                return null;
            }
            $tourLat = $coords['latitude'];
            $tourLng = $coords['longitude'];
        }

        $hotel = Listing::nearestHotel(
            $tourLat,
            $tourLng,
            (int) ($listing['id'] ?? 0)
        );
        if ($hotel === null) {
            return null;
        }

        $hLat = (float) $hotel['latitude'];
        $hLng = (float) $hotel['longitude'];
        $directions = 'https://www.google.com/maps/dir/?api=1'
            . '&origin=' . $tourLat . ',' . $tourLng
            . '&destination=' . $hLat . ',' . $hLng
            . '&travelmode=driving';

        return [
            'id' => (int) $hotel['id'],
            'title' => (string) $hotel['title'],
            'slug' => (string) $hotel['slug'],
            'area' => (string) ($hotel['area'] ?? ''),
            'address' => (string) ($hotel['address'] ?? ''),
            'image' => api_img_src((string) ($hotel['cover_image'] ?? ''), 'listing' . $hotel['id']),
            'price' => Listing::effectivePrice($hotel),
            'price_unit' => (string) ($hotel['price_unit'] ?? 'night'),
            'distance_km' => (float) $hotel['distance_km'],
            'latitude' => $hLat,
            'longitude' => $hLng,
            'directions_url' => $directions,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function chatPartnerPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'avatar' => api_img_src((string) ($user['avatar'] ?? ''), 'user' . $user['id']),
            'bio' => (string) ($user['bio'] ?? ''),
            'online' => User::isOnline((int) $user['id']),
        ];
    }

    /**
     * Shape a chat message for the mobile app, optionally auto-translating an
     * incoming message into the reader's language ($lang).
     *
     * @param array<string, mixed> $m
     * @return array<string, mixed>
     */
    private function messagePayload(array $m, int $userId, ?string $lang): array
    {
        $mine = (int) $m['sender_id'] === $userId;
        $isRead = (int) ($m['is_read'] ?? 0) === 1;

        $payload = [
            'id' => (int) $m['id'],
            'body' => (string) $m['body'],
            'mine' => $mine,
            'created_at' => (string) $m['created_at'],
            'status' => $mine ? ($isRead ? 'read' : 'delivered') : 'read',
        ];

        // Only the other person's messages need translating for this reader.
        if (!$mine && $lang !== null) {
            $translation = $this->translateMessage($m, $lang);
            if ($translation !== null) {
                $payload['translated_body'] = $translation['body'];
                $payload['source_lang'] = $translation['source'];
                $payload['translated'] = true;
            }
        }

        return $payload;
    }

    /**
     * Return a cached/fresh translation of a message into $target, or null when
     * translation isn't needed (same language) or unavailable.
     *
     * @param array<string, mixed> $m
     * @return array{body: string, source: ?string}|null
     */
    private function translateMessage(array $m, string $target): ?array
    {
        $id = (int) $m['id'];
        $original = (string) $m['body'];
        $known = $m['source_lang'] ?? null;
        $known = is_string($known) && $known !== '' ? $known : null;

        // Already known to be in the reader's language â†’ nothing to do.
        if ($known !== null && $this->normLang($known) === $this->normLang($target)) {
            return null;
        }

        $cached = Message::cachedTranslation($id, $target);
        if ($cached !== null) {
            return ['body' => $cached['body'], 'source' => $cached['source_lang']];
        }

        $result = Translator::translate($original, $target, $known);

        // Remember the detected language so we can skip this message next time.
        if ($known === null && !empty($result['source'])) {
            Message::setSourceLang($id, (string) $result['source']);
        }
        if (!$result['translated']) {
            return null;
        }

        Message::cacheTranslation($id, $target, $result['body'], $result['source']);
        return ['body' => $result['body'], 'source' => $result['source']];
    }

    private function langParam(): ?string
    {
        $lang = strtolower(trim((string) $this->input('lang', '')));
        return $lang === '' ? null : $lang;
    }

    private function normLang(string $code): string
    {
        return strtolower(explode('-', trim($code))[0]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function userPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'avatar' => api_img_src((string) ($user['avatar'] ?? ''), 'user' . $user['id']),
        ];
    }

    /**
     * Email the admin(s) and the listing's guide that a paid booking just came
     * in, so they can process it immediately.
     *
     * @param array<string, mixed> $listing
     */
    private function notifyAdminsOfBooking(
        array $listing,
        int $customerId,
        string $date,
        int $guests,
        float $total,
        string $methodLabel
    ): void {
        try {
            $customer = User::find($customerId);
            $customerName = $customer['name'] ?? 'A customer';
            $title = (string) ($listing['title'] ?? 'a listing');

            // Recipients: all admins + the guide who owns the listing.
            $recipients = Database::all("SELECT email, name FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
            $owner = User::find((int) ($listing['user_id'] ?? 0));
            if ($owner !== null && !empty($owner['email'])) {
                $recipients[] = ['email' => $owner['email'], 'name' => $owner['name']];
            }

            $subject = 'New booking awaiting your confirmation â€” ' . $title;
            $body = "{$customerName} booked \"{$title}\" and payment was received.\n\n"
                . "Date: {$date}\n"
                . "Guests: {$guests}\n"
                . 'Amount paid: PHP ' . number_format($total, 2) . "\n"
                . "Payment: {$methodLabel}\n\n"
                . "This booking is NOT confirmed yet. Open your dashboard â†’ Bookings and confirm or decline it.\n"
                . url('/dashboard/bookings');

            $seen = [];
            foreach ($recipients as $r) {
                $email = trim((string) ($r['email'] ?? ''));
                if ($email === '' || isset($seen[$email])) {
                    continue;
                }
                $seen[$email] = true;
                NotificationService::adminBookingPlaced($email, $subject, $body);
            }
        } catch (\Throwable $e) {
            // Notifications are best-effort; never block the booking on them.
        }
    }

    /**
     * Email rental partners that a paid reservation is waiting to be confirmed.
     *
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $vehicle
     */
    private function notifyRentalPartnersOfRequest(
        array $customer,
        array $vehicle,
        string $pickupDate,
        float $total
    ): void {
        try {
            $customerName = (string) ($customer['name'] ?? 'A tourist');
            $title = (string) ($vehicle['name'] ?? 'a vehicle');
            $recipients = Database::all(
                "SELECT email, name FROM users WHERE role IN ('rental_provider', 'rental_admin', 'admin')"
            );
            $subject = 'Confirm this rental â€” ' . $title;
            $body = "{$customerName} reserved \"{$title}\" and payment was received.\n\n"
                . "Pickup: {$pickupDate}\n"
                . 'Amount paid: PHP ' . number_format($total, 2) . "\n\n"
                . "This reservation is NOT confirmed yet. Open your dashboard â†’ Rental requests and confirm or decline it.\n"
                . url('/dashboard/rentals');

            $seen = [];
            foreach ($recipients as $r) {
                $email = trim((string) ($r['email'] ?? ''));
                if ($email === '' || isset($seen[$email])) {
                    continue;
                }
                $seen[$email] = true;
                NotificationService::adminBookingPlaced($email, $subject, $body);
            }
        } catch (\Throwable $e) {
            // Notifications are best-effort; never block the reservation on them.
        }
    }

    /**
     * @param array<string, mixed> $booking
     * @return array<string, mixed>
     */
    private function bookingPayload(array $booking, ?int $viewerId = null): array
    {
        $listingId = (int) $booking['listing_id'];
        $status = (string) $booking['status'];
        $disputeStatus = (string) ($booking['dispute_status'] ?? '');
        $paid = (string) ($booking['payment_status'] ?? '') === 'paid';
        $canReport = $paid && in_array($status, ['confirmed', 'completed', 'disputed'], true) && empty($booking['dispute_id']);

        $reviewed = $viewerId !== null && Review::userHasReviewed($listingId, $viewerId);
        // A tourist can review a finished tour once, and report a paid booking
        // that hasn't already been reported.
        $canReview = $status === 'completed' && !$reviewed;
        $bookingDates = null;
        if (!empty($booking['booking_dates'])) {
            $parsed = json_decode((string) $booking['booking_dates'], true);
            if (is_array($parsed) && $parsed !== []) {
                $bookingDates = $parsed;
            }
        }
        $daysCount = $bookingDates !== null ? count($bookingDates) : 1;

        $today = date('Y-m-d');
        $isHotel = !empty($booking['room_id']) || !empty($booking['hotel_status']) || (!empty($booking['deposit_amount']) && (float) $booking['deposit_amount'] > 0);
        $hotelStatus = (string) ($booking['hotel_status'] ?? '');
        $isCompleted = ($hotelStatus === 'completed' || $status === 'completed' || $status === 'cancelled' || $status === 'refunded' || $hotelStatus === 'cancelled' || $hotelStatus === 'no_show');

        if (!$isCompleted) {
            if ($isHotel) {
                $checkout = (string) ($booking['check_out_date'] ?? $booking['booking_date'] ?? '');
                if ($checkout !== '' && $checkout < $today) {
                    $isCompleted = true;
                }
            } else {
                $lastVisit = $bookingDates !== null && $bookingDates !== [] ? end($bookingDates) : (string) ($booking['booking_date'] ?? '');
                if ($lastVisit !== '' && $lastVisit < $today) {
                    $isCompleted = true;
                }
            }
        }

        $hotelCheckInTime = null;
        $hotelCheckOutTime = null;
        $hotelCheckInLabel = null;
        $hotelCheckOutLabel = null;
        $hotelCheckInOutLabel = null;
        $hotelCheckInScheduleLabel = null;

        if ($isHotel) {
            $parsedTimes = Listing::parseCheckInOutTimes($booking['listing_duration'] ?? $booking['duration'] ?? null);
            $hotelCheckInTime = !empty($booking['booking_time']) ? (string) $booking['booking_time'] : $parsedTimes['check_in_time'];
            $hotelCheckOutTime = !empty($booking['booking_end_time']) ? (string) $booking['booking_end_time'] : $parsedTimes['check_out_time'];
            $hotelCheckInLabel = (strtotime($hotelCheckInTime) !== false) ? date('g:i A', strtotime($hotelCheckInTime)) : $parsedTimes['check_in_time_label'];
            $hotelCheckOutLabel = (strtotime($hotelCheckOutTime) !== false) ? date('g:i A', strtotime($hotelCheckOutTime)) : $parsedTimes['check_out_time_label'];
            $hotelCheckInOutLabel = "{$hotelCheckInLabel} / {$hotelCheckOutLabel}";
            $hotelCheckInScheduleLabel = "Check-in from {$hotelCheckInLabel} Â· Check-out until {$hotelCheckOutLabel}";
        }

        $rawNotes = (string) ($booking['notes'] ?? '');
        $packageDetails = null;
        if (str_starts_with(trim($rawNotes), '{') && str_contains($rawNotes, 'package_booking')) {
            $packageDetails = json_decode($rawNotes, true);
        }
        $isPackage = ($booking['category_slug'] ?? '') === 'packages'
            || !empty($booking['is_package'])
            || !empty($booking['package_booking_ref'])
            || $packageDetails !== null
            || str_contains(strtolower((string)($booking['listing_title'] ?? '')), 'package');

        if ($isPackage && ($packageDetails === null || empty($packageDetails['inclusions']))) {
            $pkgListing = Listing::find($listingId);
            if ($pkgListing !== null) {
                $pkgGuide = User::find((int) ($pkgListing['user_id'] ?? 1));
                $inspected = \App\Controllers\AdminController::buildPackageInspectionData($pkgListing, $pkgGuide);
                
                $inclusions = [];
                if (!empty($inspected['inclusions']['attractions'])) {
                    foreach ($inspected['inclusions']['attractions'] as $attr) {
                        $inclusions[] = [
                            'type' => 'attraction',
                            'title' => (string) $attr['title'],
                            'subtitle' => (string) (!empty($attr['summary']) ? $attr['summary'] : (!empty($attr['area']) ? $attr['area'] : 'Attraction access')),
                            'icon' => 'water-outline',
                            'badge' => 'ATTRACTION',
                        ];
                    }
                }
                if (!empty($inspected['inclusions']['things_to_do'])) {
                    foreach ($inspected['inclusions']['things_to_do'] as $act) {
                        $inclusions[] = [
                            'type' => 'activity',
                            'title' => (string) $act['title'],
                            'subtitle' => (string) (!empty($act['summary']) ? $act['summary'] : (!empty($act['area']) ? $act['area'] : 'Activity tour')),
                            'icon' => 'compass-outline',
                            'badge' => 'THINGS TO DO',
                        ];
                    }
                }
                $hotelIncluded = !empty($inspected['hotel_included']) && !empty($inspected['hotel']);
                $rentalIncluded = !empty($inspected['rental_included']) && !empty($inspected['rental']);

                if ($hotelIncluded) {
                    $inclusions[] = [
                        'type' => 'hotel',
                        'title' => (string) ($inspected['hotel']['hotel_name'] ?? 'Hotel Stay'),
                        'subtitle' => (string) ($inspected['hotel']['room_name'] ?? 'Room'),
                        'icon' => 'bed-outline',
                        'badge' => 'HOTEL STAY',
                    ];
                }
                if ($rentalIncluded) {
                    $inclusions[] = [
                        'type' => 'rental',
                        'title' => (string) ($inspected['rental']['vehicle_name'] ?? $inspected['rental']['name'] ?? 'Vehicle Rental'),
                        'subtitle' => (string) ($inspected['rental']['vehicle_type'] ?? 'Vehicle'),
                        'icon' => 'car-sport-outline',
                        'badge' => 'VEHICLE RENTAL',
                    ];
                }

                $packageDetails = [
                    'type' => 'package_booking',
                    'package_id' => $listingId,
                    'package_title' => $pkgListing['title'],
                    'package_booking_ref' => $booking['package_booking_ref'] ?? ('GM-PKG-' . date('Ymd', strtotime($booking['created_at'] ?? 'now')) . '-' . $booking['id']),
                    'travel_date' => (string) $booking['booking_date'],
                    'check_in_date' => (string) ($booking['check_in_date'] ?? $booking['booking_date']),
                    'check_out_date' => (string) ($booking['check_out_date'] ?? $booking['booking_date']),
                    'duration_days' => max(1, (int) ($inspected['duration_days'] ?? 3)),
                    'duration_nights' => max(0, (int) ($inspected['duration_nights'] ?? 2)),
                    'duration' => $inspected['duration_code'] ?? '3D2N',
                    'destination' => $inspected['destination'] ?? ($pkgListing['area'] ?? 'Cebu'),
                    'meeting_point' => $inspected['meeting_point'] ?? ($pkgListing['address'] ?? 'Meeting point coordinated with guide'),
                    'guests_count' => (int) $booking['guests'],
                    'inclusions' => $inclusions,
                    'exclusions' => $inspected['exclusions'] ?? [],
                    'itinerary' => $inspected['itinerary'] ?? [],
                    'accommodation' => $hotelIncluded ? $inspected['hotel'] : null,
                    'rental' => $rentalIncluded ? $inspected['rental'] : null,
                    'hotel_included' => $hotelIncluded,
                    'rental_included' => $rentalIncluded,
                    'guide' => [
                        'id' => (int) ($pkgListing['user_id'] ?? 1),
                        'name' => $pkgGuide['name'] ?? ($booking['guide_name'] ?? 'Marc Repuesto'),
                        'avatar' => $pkgGuide['avatar'] ?? ($booking['guide_avatar'] ?? ''),
                        'phone' => $pkgGuide['phone'] ?? ($booking['guide_phone'] ?? '0917 123 4567'),
                    ],
                ];
            }
        }

        if ($packageDetails !== null) {
            if (!isset($packageDetails['hotel_included'])) {
                $packageDetails['hotel_included'] = !empty($packageDetails['accommodation']);
            }
            if (!isset($packageDetails['rental_included'])) {
                $packageDetails['rental_included'] = !empty($packageDetails['rental']);
            }
        }

        $pkgSummary = $isPackage
            ? \App\Services\PackageBookingService::getPackageBookingSummary($booking['package_booking_ref'] ?? $booking['id'])
            : null;
        $overallPackageStatus = (($booking['status'] ?? '') === 'completed' || ($booking['package_overall_status'] ?? '') === 'completed')
            ? 'completed'
            : ($pkgSummary['overall_status'] ?? ($booking['package_overall_status'] ?? $status));

        $providerStatuses = $pkgSummary['items'] ?? [];
        if (empty($providerStatuses) && $isPackage) {
            $guideItemStatus = ($status === 'confirmed' && empty($booking['package_overall_status'])) ? 'pending' : ($status === 'cancelled' ? 'cancelled' : 'pending');
            $providerStatuses = [
                [
                    'service_type' => 'guide',
                    'provider_id' => (int) ($packageDetails['guide']['id'] ?? $booking['guide_id'] ?? 1),
                    'provider_name' => (string) ($packageDetails['guide']['name'] ?? $booking['guide_name'] ?? 'Marc Repuesto'),
                    'service_title' => (string) ($booking['listing_title'] ?? 'Guided Tour Activities'),
                    'status' => $guideItemStatus,
                ]
            ];
            if (!empty($packageDetails['hotel_included']) && !empty($packageDetails['accommodation'])) {
                $hotel = $packageDetails['accommodation'];
                $providerStatuses[] = [
                    'service_type' => 'hotel',
                    'provider_id' => (int) ($hotel['hotel_id'] ?? $hotel['user_id'] ?? 0),
                    'provider_name' => (string) ($hotel['hotel_name'] ?? $hotel['name'] ?? 'Hotel Provider'),
                    'service_title' => (string) ($hotel['room_name'] ?? $hotel['roomType'] ?? 'Room Accommodation'),
                    'status' => 'pending',
                ];
            }
            if (!empty($packageDetails['rental_included']) && !empty($packageDetails['rental'])) {
                $rental = $packageDetails['rental'];
                $providerStatuses[] = [
                    'service_type' => 'vehicle',
                    'provider_id' => (int) ($rental['vehicle_id'] ?? $rental['user_id'] ?? 0),
                    'provider_name' => (string) ($rental['owner_name'] ?? $rental['rental_name'] ?? 'Rental Provider'),
                    'service_title' => (string) ($rental['vehicle_name'] ?? $rental['name'] ?? 'Vehicle Rental'),
                    'status' => 'pending',
                ];
            }
        }

        if ($isPackage) {
            if ($overallPackageStatus === 'completed') {
                $isCompleted = true;
                $canReview = !$reviewed;
                $canReport = empty($booking['dispute_id']);
            } elseif ($overallPackageStatus === 'confirmed') {
                $canReport = empty($booking['dispute_id']);
            }
        }

        return [
            'id' => (int) $booking['id'],
            'listing_id' => $listingId,
            'is_hotel' => $isHotel,
            'is_package' => $isPackage,
            'is_completed' => $isCompleted,
            'listing_title' => (string) ($booking['listing_title'] ?? ($packageDetails['package_title'] ?? '')),
            'listing_slug' => (string) ($booking['listing_slug'] ?? ''),
            'category_slug' => (string) ($booking['category_slug'] ?? ($isPackage ? 'packages' : '')),
            'guide_name' => (string) ($booking['guide_name'] ?? ($packageDetails['guide']['name'] ?? '')),
            'guide_avatar' => !empty($booking['guide_avatar']) ? api_img_src((string)$booking['guide_avatar'], 'user' . ($booking['guide_id'] ?? '')) : (!empty($packageDetails['guide']['avatar']) ? $packageDetails['guide']['avatar'] : ''),
            'guide_phone' => (string) ($booking['guide_phone'] ?? ($packageDetails['guide']['phone'] ?? '')),
            'guide_id' => !empty($booking['guide_id']) ? (int) $booking['guide_id'] : (!empty($packageDetails['guide']['id']) ? (int) $packageDetails['guide']['id'] : null),
            'package_details' => $packageDetails,
            'payment_method' => (string) ($booking['payment_method'] ?? ($packageDetails['payment_method'] ?? 'ewallet')),
            'payment_reference' => (string) ($booking['payment_reference'] ?? ($packageDetails['payment_reference'] ?? ('BK-' . $booking['id']))),
            'payment_paid_at' => (string) ($booking['payment_paid_at'] ?? $booking['created_at']),
            'notes_raw' => $rawNotes,
            'listing_slug' => (string) ($booking['listing_slug'] ?? ''),
            'image' => api_img_src((string) ($booking['cover_image'] ?? ''), 'listing' . $booking['listing_id']),
            'booking_date' => (string) $booking['booking_date'],
            'booking_dates' => $bookingDates ?? [(string) $booking['booking_date']],
            'days_count' => $daysCount,
            'booking_time' => (string) (!empty($booking['booking_time']) ? $booking['booking_time'] : ($isHotel ? ($hotelCheckInTime ?? '14:00:00') : '')),
            'booking_end_time' => (string) (!empty($booking['booking_end_time']) ? $booking['booking_end_time'] : ($isHotel ? ($hotelCheckOutTime ?? '12:00:00') : '')),
            'check_in_time_label' => $isHotel ? $hotelCheckInLabel : null,
            'check_out_time_label' => $isHotel ? $hotelCheckOutLabel : null,
            'check_in_out_label' => $isHotel ? $hotelCheckInOutLabel : null,
            'check_in_schedule_label' => $isHotel ? $hotelCheckInScheduleLabel : null,
            'guests' => (int) $booking['guests'],
            'total_amount' => (float) $booking['total_amount'],
            'room_id' => !empty($booking['room_id']) ? (int) $booking['room_id'] : null,
            'room_name' => (string) ($booking['room_name'] ?? ''),
            'check_in_date' => (string) ($booking['check_in_date'] ?? $booking['booking_date']),
            'check_out_date' => (string) (!empty($booking['check_out_date']) ? $booking['check_out_date'] : ($isHotel ? date('Y-m-d', strtotime(($booking['check_in_date'] ?? $booking['booking_date']) . ' +1 day')) : ($booking['booking_date'] ?? ''))),
            'nights' => (int) ($booking['nights'] ?? 1),
            'deposit_amount' => (float) ($booking['deposit_amount'] ?? 0),
            'balance_amount' => (($booking['payment_stage'] ?? '') === 'settled' || ($booking['hotel_status'] ?? '') === 'completed' || ($booking['payment_stage'] ?? '') === 'paid' || ($booking['hotel_status'] ?? '') === 'checked_in')
                ? 0.0
                : ((float) ($booking['balance_amount'] ?? 0) > 0
                    ? (float) $booking['balance_amount']
                    : max(0.0, round((float) $booking['total_amount'] - (float) ($booking['deposit_amount'] ?? 0), 2))),
            'payment_stage' => (($booking['payment_stage'] ?? '') === 'settled' || ($booking['hotel_status'] ?? '') === 'completed')
                ? 'settled'
                : ((($booking['payment_stage'] ?? '') === 'paid' || ($booking['hotel_status'] ?? '') === 'checked_in')
                    ? 'paid'
                    : (string) ($booking['payment_stage'] ?? 'full')),
            'balance_payment_status' => (($booking['payment_stage'] ?? '') === 'settled' || ($booking['hotel_status'] ?? '') === 'completed')
                ? 'Settled'
                : ((($booking['payment_stage'] ?? '') === 'paid' || ($booking['hotel_status'] ?? '') === 'checked_in')
                    ? 'Paid'
                    : (((float) ($booking['balance_amount'] ?? 0) > 0 || (float) ($booking['deposit_amount'] ?? 0) > 0) ? 'Pending' : 'Settled')),
            'hotel_status' => (string) ($booking['hotel_status'] ?? ''),
            'package_booking_ref' => $booking['package_booking_ref'] ?? ($pkgSummary['package_booking_ref'] ?? ($packageDetails['package_booking_ref'] ?? null)),
            'package_overall_status' => $overallPackageStatus,
            'provider_statuses' => $providerStatuses,
            'status' => $isPackage ? $overallPackageStatus : $status,
            'status_label' => $isPackage ? match ($overallPackageStatus) {
                'pending' => 'Pending Confirmations',
                'partially_confirmed' => 'Awaiting Providers Confirmation',
                'confirmed' => 'Confirmed',
                'cancelled' => 'Cancelled',
                'completed' => 'Completed',
                default => ucfirst(str_replace('_', ' ', $overallPackageStatus)),
            } : (!empty($booking['hotel_status']) ? match ($booking['hotel_status']) {
                'pending' => 'Pending Review',
                'accepted' => 'Accepted',
                'deposit_paid' => 'Deposit Paid',
                'confirmed' => 'Confirmed',
                'checked_in' => 'Checked In',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'no_show' => 'No-Show',
                default => ucfirst($booking['hotel_status']),
            } : match ($status) {
                'pending' => 'Awaiting confirmation',
                'confirmed' => 'Approved',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded',
                'disputed' => 'In review',
                default => ucfirst($status),
            }),
            'area' => (string) ($booking['area'] ?? ''),
            'reviewed' => $reviewed,
            'can_review' => $canReview,
            'can_report' => $canReport,
            'dispute_status' => $disputeStatus,
            'cancellation' => $this->computeCancellationPolicy($booking, $isHotel ? 'hotel' : 'attraction'),
        ];
    }

    // â”€â”€ Reviews & problem reports â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /** POST /api/reviews â€” leave a rating/review for a completed experience. */
    public function submitReview(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $listingId = (int) ($body['listing_id'] ?? 0);
        $rating = (int) ($body['rating'] ?? 0);
        $comment = trim((string) ($body['comment'] ?? ''));
        $title = trim((string) ($body['title'] ?? ''));

        $listing = $listingId > 0 ? Listing::find($listingId) : null;
        if ($listing === null) {
            $this->json(['error' => 'Listing not found.'], 404);
            return;
        }
        if ($rating < 1 || $rating > 5) {
            $this->json(['error' => 'Please choose a rating from 1 to 5 stars.'], 422);
            return;
        }
        if ($comment === '') {
            $this->json(['error' => 'Please write a short review.'], 422);
            return;
        }
        if (!Booking::hasCompletedBooking($listingId, $userId)) {
            $this->json(['error' => 'You can only review experiences you have booked.'], 403);
            return;
        }
        if (Review::userHasReviewed($listingId, $userId)) {
            $this->json(['error' => 'You have already reviewed this experience.'], 409);
            return;
        }

        Review::create($listingId, $userId, $rating, $title, $comment, date('Y-m-d'));

        $this->json([
            'success' => true,
            'summary' => Review::summary($listingId),
        ], 201);
    }

    /** GET /api/disputes?booking_id= â€” the tourist's existing report, if any. */
    public function myDispute(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $bookingId = (int) $this->input('booking_id', 0);
        $booking = $bookingId > 0 ? Booking::find($bookingId) : null;
        if ($booking === null || (int) $booking['user_id'] !== $userId) {
            $this->json(['error' => 'Booking not found.'], 404);
            return;
        }

        $dispute = Dispute::forBooking($bookingId);
        $this->json(['dispute' => $dispute !== null ? $this->disputePayload($dispute) : null]);
    }

    /**
     * POST /api/disputes â€” report a problem with a guide/booking to the admin.
     * Sent as multipart/form-data so photo evidence can be attached.
     */
    public function submitDispute(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $bookingId = (int) ($_POST['booking_id'] ?? $body['booking_id'] ?? 0);
        $type = trim((string) ($_POST['problem_type'] ?? $body['problem_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? $body['description'] ?? ''));
        $amountRaw = trim((string) ($_POST['amount_requested'] ?? $body['amount_requested'] ?? ''));

        $booking = $bookingId > 0 ? Booking::find($bookingId) : null;
        if ($booking === null || (int) $booking['user_id'] !== $userId) {
            $this->json(['error' => 'Booking not found.'], 404);
            return;
        }
        if (!Payment::isPaid($bookingId)) {
            $this->json(['error' => 'You can only report a problem on a paid booking.'], 403);
            return;
        }
        if (!in_array((string) $booking['status'], ['confirmed', 'completed', 'disputed'], true)) {
            $this->json(['error' => 'This booking cannot be reported yet.'], 422);
            return;
        }
        if (Dispute::forBooking($bookingId) !== null) {
            $this->json(['error' => 'You already reported a problem for this booking.'], 409);
            return;
        }
        if (!isset(Dispute::TYPES[$type])) {
            $this->json(['error' => 'Please choose a valid problem type.'], 422);
            return;
        }
        if (mb_strlen($description) < 20) {
            $this->json(['error' => 'Please describe the problem in at least 20 characters.'], 422);
            return;
        }
        $amount = null;
        if ($type === 'extra_payment') {
            if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                $this->json(['error' => 'Enter the extra amount the guide requested.'], 422);
                return;
            }
            $amount = (float) $amountRaw;
        }

        $disputeId = Dispute::create([
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'guide_id' => (int) $booking['guide_id'],
            'problem_type' => $type,
            'amount_requested' => $amount,
            'description' => $description,
        ]);

        // Attach any photo evidence (field "evidence" or "evidence[]").
        foreach ($this->normalizeFiles($_FILES['evidence'] ?? null) as $file) {
            $path = Upload::image($file, 'disputes');
            if ($path !== null) {
                DisputeFile::add($disputeId, $path, (string) ($file['name'] ?? ''));
            }
        }

        Booking::updateStatus($bookingId, 'disputed');

        $dispute = Dispute::find($disputeId);
        $this->json([
            'success' => true,
            'dispute' => $dispute !== null ? $this->disputePayload($dispute) : null,
        ], 201);
    }

    /**
     * POST /api/disputes/{id}/evidence - upload additional photo evidence for a dispute.
     */
    public function uploadDisputeEvidence(int $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $dispute = Dispute::find($id);
        if ($dispute === null || (int) $dispute['user_id'] !== $userId) {
            $this->json(['error' => 'Dispute not found.'], 404);
            return;
        }

        $attached = 0;
        foreach ($this->normalizeFiles($_FILES['evidence'] ?? null) as $file) {
            $path = Upload::image($file, 'disputes');
            if ($path !== null) {
                DisputeFile::add($id, $path, (string) ($file['name'] ?? ''));
                $attached++;
            }
        }

        $this->json(['success' => true, 'files_attached' => $attached]);
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function disputePayload(array $d): array
    {
        $files = DisputeFile::forDispute((int) $d['id']);

        return [
            'id' => (int) $d['id'],
            'booking_id' => (int) $d['booking_id'],
            'problem_type' => (string) $d['problem_type'],
            'problem_label' => Dispute::typeLabel((string) $d['problem_type']),
            'amount_requested' => $d['amount_requested'] !== null ? (float) $d['amount_requested'] : null,
            'description' => (string) $d['description'],
            'status' => (string) $d['status'],
            'status_label' => Dispute::statusLabel((string) $d['status']),
            'admin_note' => (string) ($d['admin_note'] ?? ''),
            'created_at' => (string) ($d['created_at'] ?? ''),
            'photos' => array_map(function ($f) {
                return (string) ($f['file_path'] ?? '');
            }, $files),
        ];
    }

    /**
     * Normalise a $_FILES entry (single file or PHP's arrayized multi-file
     * shape) into a flat list of single-file arrays, skipping empty slots.
     *
     * @param array<string, mixed>|null $entry
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFiles(?array $entry): array
    {
        if (!is_array($entry) || !isset($entry['name'])) {
            return [];
        }
        if (!is_array($entry['name'])) {
            return $entry['error'] === UPLOAD_ERR_NO_FILE ? [] : [$entry];
        }

        $files = [];
        $count = count($entry['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $entry['name'][$i],
                'type' => $entry['type'][$i],
                'tmp_name' => $entry['tmp_name'][$i],
                'error' => $entry['error'][$i],
                'size' => $entry['size'][$i],
            ];
        }
        return $files;
    }

    /**
     * Settle the remaining unpaid balance for a checked-in hotel reservation.
     */
    public function payBookingBalance(string|int $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $bookingId = (int) $id;
        $booking = Booking::find($bookingId);
        if ($booking === null || (int) $booking['user_id'] !== $userId) {
            $this->json(['error' => 'Reservation not found.'], 404);
            return;
        }

        $hotelStatus = (string) ($booking['hotel_status'] ?? '');
        $paymentStage = (string) ($booking['payment_stage'] ?? '');
        $balance = (float) ($booking['balance_amount'] ?? 0);
        if ($balance <= 0 && (float) ($booking['total_amount'] ?? 0) > (float) ($booking['deposit_amount'] ?? 0)) {
            $balance = max(0.0, round((float) $booking['total_amount'] - (float) ($booking['deposit_amount'] ?? 0), 2));
        }

        if ($paymentStage === 'settled' || $paymentStage === 'paid' || $hotelStatus === 'checked_in' || $balance <= 0) {
            $this->json(['error' => 'This reservation has no outstanding balance due.'], 422);
            return;
        }

        if ($hotelStatus !== 'checked_in') {
            $this->json(['error' => 'Balance payment is only available once the guest has checked in at the property.'], 422);
            return;
        }

        $body = $this->jsonBody();
        $allowedMethods = ['gcash', 'instapay', 'card'];
        $method = strtolower(trim((string) ($body['payment_method'] ?? 'card')));
        if (!in_array($method, $allowedMethods, true)) {
            $method = 'card';
        }
        $paymentReference = trim((string) ($body['payment_reference'] ?? ''));
        $cardLast4 = preg_replace('/\D/', '', (string) ($body['card_last4'] ?? '')) ?? '';

        if (($method === 'gcash' || $method === 'instapay') && strlen($paymentReference) < 4) {
            $this->json(['error' => 'Please provide a valid payment reference number.'], 422);
            return;
        }

        // Record the payment
        $paymentId = Payment::create($bookingId, $balance, $method, 'paid');
        $db = \App\Core\Database::connection();
        if ($paymentReference !== '') {
            $db->prepare("UPDATE payments SET reference = ? WHERE id = ?")->execute([$paymentReference, $paymentId]);
        }

        // Update booking state
        $stmt = $db->prepare("UPDATE bookings SET payment_stage = 'settled', balance_amount = 0.00 WHERE id = ?");
        $stmt->execute([$bookingId]);

        // Fetch refreshed booking
        $updatedBooking = Booking::find($bookingId);

        $this->json([
            'success' => true,
            'message' => 'Remaining balance settled successfully.',
            'booking' => $updatedBooking !== null ? $this->bookingPayload($updatedBooking) : null,
            'payment_id' => $paymentId,
            'amount_paid' => $balance,
        ]);
    }

    /**
     * Calculate cancellation policy metadata, deadlines, remaining time, and eligibility.
     * Delegates to CancellationPolicyService for unified, multi-platform policy enforcement.
     *
     * @param array<string, mixed> $item
     * @param string $kind 'hotel' | 'attraction' | 'rental'
     * @return array<string, mixed>
     */
    private function computeCancellationPolicy(array $item, string $kind): array
    {
        return \App\Services\CancellationPolicyService::compute($item, $kind);
    }

    /**
     * POST /api/bookings/{id}/cancel
     * Tourist-initiated cancellation for Hotel or Attraction bookings.
     */
    public function cancelBooking(string|int $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Cancelled by tourist via GuideMate app';
        }

        try {
            $result = \App\Services\CancellationPolicyService::cancelBooking((int) $id, $userId, $reason);
            $booking = $result['booking'];
            $this->json([
                'success' => true,
                'booking_id' => (int) $id,
                'status' => $result['status'],
                'refund_amount' => $result['refund_amount'],
                'refund_status' => $result['refund_status'],
                'refund_description' => $result['refund_description'],
                'deposit_rule' => $result['deposit_rule'],
                'processing_time' => $result['processing_time'],
                'message' => $result['message'],
                'booking' => $booking !== null ? $this->bookingPayload($booking) : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'warning') ? 403 : 422;
            $this->json(['error' => $e->getMessage()], $status);
        }
    }

    /**
     * POST /api/rentals/{id}/cancel
     * Tourist-initiated cancellation for Vehicle Rentals.
     */
    public function cancelRental(string|int $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Cancelled by tourist via GuideMate app';
        }

        try {
            $result = \App\Services\CancellationPolicyService::cancelRental((int) $id, $userId, $reason);
            $rental = $result['rental'];
            $this->json([
                'success' => true,
                'rental_id' => (int) $id,
                'status' => $result['status'],
                'refund_amount' => $result['refund_amount'],
                'refund_status' => $result['refund_status'],
                'refund_description' => $result['refund_description'],
                'processing_time' => $result['processing_time'],
                'message' => $result['message'],
                'rental' => $rental !== null ? $this->rentalPayload($rental) : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'warning') ? 403 : 422;
            $this->json(['error' => $e->getMessage()], $status);
        }
    }

    /**
     * GET /api/packages
     * Return curated travel packages created and hosted by local guides.
     */
    public function packages(): void
    {
        $category = trim((string) ($_GET['category'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));

        $packages = $this->getPackagesCatalog();

        if ($category !== '' && $category !== 'all') {
            if ($category === 'multi-day') {
                $packages = array_filter($packages, fn($p) => ($p['durationDays'] ?? 0) > 1);
            } elseif ($category === 'day-tour') {
                $packages = array_filter($packages, fn($p) => ($p['durationDays'] ?? 0) === 1);
            } else {
                $packages = array_filter($packages, fn($p) => ($p['category'] ?? '') === $category);
            }
        }

        if ($q !== '') {
            $ql = strtolower($q);
            $packages = array_filter($packages, function ($p) use ($ql) {
                return str_contains(strtolower($p['title'] ?? ''), $ql)
                    || str_contains(strtolower($p['destination'] ?? ''), $ql)
                    || str_contains(strtolower($p['host']['name'] ?? ''), $ql)
                    || str_contains(strtolower($p['tagline'] ?? ''), $ql);
            });
        }

        $this->json([
            'packages' => array_values($packages),
            'total' => count($packages),
        ]);
    }

    /**
     * GET /api/packages/{id}
     */
    public function packageDetail(string $id): void
    {
        // 1. Direct database lookup for dynamic package by ID or slug
        try {
            $row = \App\Core\Database::first("
                SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                       u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.bio AS guide_bio,
                       COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
                FROM listings l
                LEFT JOIN categories c ON c.id = l.category_id
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN reviews r ON r.listing_id = l.id
                WHERE (l.id = ? OR l.slug = ?)
                  AND l.status IN ('approved', 'pending')
                GROUP BY l.id
                LIMIT 1
            ", [$id, $id]);

            if ($row) {
                $pkgApprovalStatus = \App\Models\PackageService::getOverallStatus(
                    (int) $row['id'],
                    isset($row['package_approval_status']) ? (string) $row['package_approval_status'] : null
                );
                $currentUserId = ApiAuth::id() ?? \App\Core\Auth::id();
                $isOwnerOrAdmin = ($currentUserId !== null && ((int)$row['user_id'] === (int)$currentUserId || \App\Core\Auth::isAdmin()));

                if ($pkgApprovalStatus !== 'agreed' && !$isOwnerOrAdmin) {
                    $this->json(['error' => 'This travel package is not currently available.'], 404);
                    return;
                }

                $pkg = $this->formatListingAsPackage($row);
                $viewerId = ApiAuth::id() ?? \App\Core\Auth::id() ?? ($_SESSION['user_id'] ?? null);
                if ($viewerId !== null) {
                    $activeBooking = \App\Core\Database::first("
                        SELECT id, status, package_overall_status, package_booking_ref
                        FROM bookings
                        WHERE user_id = ?
                          AND listing_id = ?
                          AND (
                              status IN ('pending', 'confirmed')
                              OR package_overall_status IN ('pending', 'partially_confirmed', 'confirmed')
                          )
                          AND status NOT IN ('completed', 'cancelled', 'refunded')
                        ORDER BY id DESC
                        LIMIT 1
                    ", [$viewerId, (int) $row['id']]);

                    $pkg['has_active_booking'] = (bool) $activeBooking;
                    $pkg['active_booking'] = $activeBooking ? [
                        'id' => (int) $activeBooking['id'],
                        'status' => $activeBooking['status'],
                        'overall_status' => $activeBooking['package_overall_status'] ?? $activeBooking['status'],
                        'ref' => $activeBooking['package_booking_ref'] ?? ('BK-' . $activeBooking['id']),
                    ] : null;
                } else {
                    $pkg['has_active_booking'] = false;
                    $pkg['active_booking'] = null;
                }
                $this->json(['package' => $pkg]);
                return;
            }
        } catch (\Throwable $e) {}

        // 2. Fallback to full catalog (including static packages)
        $packages = $this->getPackagesCatalog();
        $viewerId = ApiAuth::id() ?? \App\Core\Auth::id() ?? ($_SESSION['user_id'] ?? null);
        foreach ($packages as $pkg) {
            if ((string)$pkg['id'] === (string)$id || ($pkg['slug'] ?? '') === $id) {
                if ($viewerId !== null && !empty($pkg['id']) && is_numeric($pkg['id'])) {
                    $activeBooking = \App\Core\Database::first("
                        SELECT id, status, package_overall_status, package_booking_ref
                        FROM bookings
                        WHERE user_id = ?
                          AND listing_id = ?
                          AND (
                              status IN ('pending', 'confirmed')
                              OR package_overall_status IN ('pending', 'partially_confirmed', 'confirmed')
                          )
                          AND status NOT IN ('completed', 'cancelled', 'refunded')
                        ORDER BY id DESC
                        LIMIT 1
                    ", [$viewerId, (int) $pkg['id']]);

                    $pkg['has_active_booking'] = (bool) $activeBooking;
                    $pkg['active_booking'] = $activeBooking ? [
                        'id' => (int) $activeBooking['id'],
                        'status' => $activeBooking['status'],
                        'overall_status' => $activeBooking['package_overall_status'] ?? $activeBooking['status'],
                        'ref' => $activeBooking['package_booking_ref'] ?? ('BK-' . $activeBooking['id']),
                    ] : null;
                } else {
                    $pkg['has_active_booking'] = false;
                    $pkg['active_booking'] = null;
                }
                $this->json(['package' => $pkg]);
                return;
            }
        }
        $this->json(['error' => 'Package not found.'], 404);
    }

    /**
     * POST /api/packages/{id}/book
     */
    public function bookPackage(string $id): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $userId = \App\Core\Auth::id() ?? ($_SESSION['user_id'] ?? null);
            if ($userId === null) {
                $firstTourist = \App\Core\Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1");
                $userId = $firstTourist ? (int) $firstTourist['id'] : 1;
            }
        }

        $body = $this->jsonBody();
        $packages = $this->getPackagesCatalog();
        $target = null;
        foreach ($packages as $pkg) {
            if ((string)$pkg['id'] === (string)$id || ($pkg['slug'] ?? '') === $id) {
                $target = $pkg;
                break;
            }
        }

        if (!$target) {
            $this->json(['error' => 'Package not found.'], 404);
            return;
        }

        $guestsCount = max(1, (int) ($body['guestsCount'] ?? 1));
        $travelDate = (string) ($body['travelDate'] ?? date('Y-m-d', strtotime('+3 days')));

        // Min and Max Pax validation
        $minPax = (int) ($target['minPax'] ?? $target['min_pax'] ?? 2);
        $maxPax = (int) ($target['maxPax'] ?? $target['max_pax'] ?? $target['capacity'] ?? 8);

        if ($guestsCount < $minPax) {
            $this->json(['error' => "This package requires a minimum of {$minPax} participants."], 422);
            return;
        }
        if ($guestsCount > $maxPax) {
            $this->json(['error' => "This package allows a maximum of {$maxPax} participants."], 422);
            return;
        }

        // Pricing: Constant package base price (never multiplied by pax)
        $packageBasePrice = (float) ($target['price'] ?? 8499.00);
        $taxes = round($packageBasePrice * 0.05, 2);
        $environmentalFees = 250.00;
        $voucherCode = strtoupper(trim((string) ($body['voucherCode'] ?? '')));
        $voucherDiscount = 0.00;

        if ($voucherCode !== '') {
            $voucherCheck = PromoCode::validate($voucherCode, $packageBasePrice, $userId);
            if (!empty($voucherCheck['valid'])) {
                $voucherDiscount = (float) ($voucherCheck['discount'] ?? 0);
            }
        }

        $finalTotal = max(0.00, round($packageBasePrice + $taxes + $environmentalFees - $voucherDiscount, 2));
        $paymentMethod = (string) ($body['paymentMethod'] ?? 'ewallet');
        $contactName = (string) ($body['contactName'] ?? 'Tourist');
        $contactPhone = (string) ($body['contactPhone'] ?? '0917 123 4567');
        $notes = (string) ($body['notes'] ?? '');

        // Resolve guide host
        $guideId = (int) ($target['user_id'] ?? $target['host']['id'] ?? 3);
        $guideName = (string) ($target['host']['name'] ?? 'Marc Repuesto');
        $guideAvatar = (string) ($target['host']['avatar'] ?? '');
        $guidePhone = (string) ($target['host']['phone'] ?? '0917 123 4567');

        // Resolve package listing ID
        $listingId = (int) ($target['id'] ?? 0);
        $listingRecord = \App\Core\Database::first("SELECT id FROM listings WHERE id = ?", [$listingId]);
        if (!$listingRecord) {
            $existingPkg = \App\Core\Database::first("
                SELECT l.id FROM listings l
                JOIN categories c ON c.id = l.category_id
                WHERE c.slug IN ('packages', 'travel-packages')
                LIMIT 1
            ");
            $listingId = $existingPkg ? (int) $existingPkg['id'] : 1;
        }

        // Active booking prevention: prevent duplicate bookings while current booking is in progress
        $activeBooking = \App\Core\Database::first("
            SELECT id, status, package_overall_status, package_booking_ref
            FROM bookings
            WHERE user_id = ?
              AND listing_id = ?
              AND (
                  status IN ('pending', 'confirmed')
                  OR package_overall_status IN ('pending', 'partially_confirmed', 'confirmed')
              )
              AND status NOT IN ('completed', 'cancelled', 'refunded')
            ORDER BY id DESC
            LIMIT 1
        ", [$userId, $listingId]);

        if ($activeBooking) {
            $this->json([
                'error' => 'You already have an active booking for this travel package. You can book this package again once your current trip is completed.',
                'has_active_booking' => true,
                'active_booking' => [
                    'id' => (int) $activeBooking['id'],
                    'status' => $activeBooking['status'],
                    'overall_status' => $activeBooking['package_overall_status'] ?? $activeBooking['status'],
                    'ref' => $activeBooking['package_booking_ref'] ?? ('BK-' . $activeBooking['id']),
                ],
            ], 409);
            return;
        }

        try {
            $bookingRes = \App\Services\PackageBookingService::createPackageBooking((int) $listingId, (int) $userId, [
                'guestsCount' => $guestsCount,
                'travelDate' => $travelDate,
                'contactName' => $contactName,
                'contactPhone' => $contactPhone,
                'contactEmail' => (string) ($body['contactEmail'] ?? ''),
                'notes' => $notes,
                'paymentMethod' => $paymentMethod,
                'voucherDiscount' => $voucherDiscount,
            ]);

            $bookingId = $bookingRes['parent_booking_id'];
            $pkgRef = $bookingRes['package_booking_ref'];
            $finalTotal = (float) $bookingRes['total_amount'];

            // Increment promo code usage if applicable
            if ($voucherCode !== '' && $voucherDiscount > 0) {
                try {
                    $codeRow = \App\Core\Database::first("SELECT id FROM promo_codes WHERE code = ?", [$voucherCode]);
                    if ($codeRow) {
                        \App\Core\Database::run("UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?", [$codeRow['id']]);
                        \App\Core\Database::run("UPDATE bookings SET promo_code_id = ?, discount_amount = ? WHERE id = ?", [
                            $codeRow['id'],
                            $voucherDiscount,
                            $bookingId,
                        ]);
                    }
                } catch (\Throwable) {}
            }

            $this->json([
                'success' => true,
                'bookingId' => 'BK-' . $bookingId,
                'rawBookingId' => (int) $bookingId,
                'packageBookingRef' => $pkgRef,
                'packageTitle' => $target['title'],
                'totalPrice' => $finalTotal,
                'basePrice' => $packageBasePrice,
                'taxes' => $taxes,
                'environmentalFees' => $environmentalFees,
                'voucherDiscount' => $voucherDiscount,
                'status' => 'pending',
                'overallStatus' => 'pending',
                'travelDate' => $travelDate,
                'guestsCount' => $guestsCount,
                'guideName' => $guideName,
                'paymentReference' => $pkgRef,
                'allocations' => $bookingRes['allocations'] ?? [],
                'message' => 'Your travel package booking has been submitted (Ref #' . $pkgRef . ')! All participating providers have received reservation requests.',
            ]);
            return;
        } catch (\Throwable) {}

        $txnRef = 'TXN-PKG-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        // Build rich package details JSON for storage in bookings.notes
        $packageNotesData = [
            'type' => 'package_booking',
            'package_id' => $target['id'],
            'package_title' => $target['title'],
            'travel_date' => $travelDate,
            'guests_count' => $guestsCount,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'special_notes' => $notes,
            'payment_method' => $paymentMethod,
            'payment_reference' => $txnRef,
            'package_base_price' => $packageBasePrice,
            'taxes' => $taxes,
            'environmental_fees' => $environmentalFees,
            'voucher_code' => $voucherCode,
            'voucher_discount' => $voucherDiscount,
            'final_total' => $finalTotal,
            'inclusions' => $target['inclusions'] ?? [],
            'exclusions' => $target['exclusions'] ?? [],
            'itinerary' => $target['itinerary'] ?? [],
            'accommodation' => $target['accommodationDetail'] ?? null,
            'rental' => $target['rentalDetail'] ?? null,
            'guide' => [
                'id' => $guideId,
                'name' => $guideName,
                'avatar' => $guideAvatar,
                'phone' => $guidePhone,
            ],
            'destination' => $target['destination'] ?? 'Moalboal & Badian, South Cebu',
            'duration' => $target['duration'] ?? '3D2N',
        ];

        $notesJson = json_encode($packageNotesData, JSON_UNESCAPED_SLASHES);

        // Insert into bookings table
        $bookingId = \App\Core\Database::insert(
            "INSERT INTO bookings (
                listing_id, user_id, booking_date, guests,
                total_amount, deposit_amount, balance_amount,
                payment_stage, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'settled', 'confirmed', ?, NOW(), NOW())",
            [
                $listingId,
                $userId,
                $travelDate,
                $guestsCount,
                $finalTotal,
                $finalTotal,
                0.00,
                $notesJson,
            ]
        );

        // Insert payment record
        \App\Core\Database::insert(
            "INSERT INTO payments (booking_id, amount, method, status, reference, paid_at, created_at)
             VALUES (?, ?, ?, 'paid', ?, NOW(), NOW())",
            [
                $bookingId,
                $finalTotal,
                $paymentMethod,
                $txnRef,
            ]
        );

        // Increment promo code usage if applicable
        if ($voucherCode !== '' && $voucherDiscount > 0) {
            try {
                $codeRow = \App\Core\Database::first("SELECT id FROM promo_codes WHERE code = ?", [$voucherCode]);
                if ($codeRow) {
                    \App\Core\Database::run("UPDATE promo_codes SET uses_count = uses_count + 1 WHERE id = ?", [$codeRow['id']]);
                    \App\Core\Database::run("UPDATE bookings SET promo_code_id = ?, discount_amount = ? WHERE id = ?", [
                        $codeRow['id'],
                        $voucherDiscount,
                        $bookingId,
                    ]);
                }
            } catch (\Throwable) {}
        }

        $this->json([
            'success' => true,
            'bookingId' => 'BK-' . $bookingId,
            'rawBookingId' => (int) $bookingId,
            'packageTitle' => $target['title'],
            'totalPrice' => $finalTotal,
            'basePrice' => $packageBasePrice,
            'taxes' => $taxes,
            'environmentalFees' => $environmentalFees,
            'voucherDiscount' => $voucherDiscount,
            'status' => 'confirmed',
            'travelDate' => $travelDate,
            'guestsCount' => $guestsCount,
            'guideName' => $guideName,
            'paymentReference' => $txnRef,
            'message' => 'Your travel package has been booked and confirmed! Guide ' . $guideName . ' has received your reservation details and will coordinate your itinerary with you.',
        ]);
    }

    /**
     * Combined catalog of database travel packages created by guides + curated defaults.
     * @return array<int, array<string, mixed>>
     */
    private function getPackagesCatalog(): array
    {
        $dbPackages = $this->getDatabasePackages();
        $staticPackages = $this->getStaticPackages();

        $seen = [];
        $merged = [];
        foreach (array_merge($dbPackages, $staticPackages) as $pkg) {
            $key = (string) $pkg['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $merged[] = $pkg;
            }
        }
        return $merged;
    }

    /**
     * Query travel packages created by guides from the database.
     * Matches listings under the packages category or with package keywords.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDatabasePackages(): array
    {
        try {
            $rows = \App\Core\Database::all("
                SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                       u.id AS guide_id, u.name AS guide_name, u.avatar AS guide_avatar, u.bio AS guide_bio,
                       COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                LEFT JOIN reviews r ON r.listing_id = l.id
                WHERE (c.slug = 'packages' OR c.slug = 'travel-packages' OR l.title LIKE '%Package%' OR l.title LIKE '%Travel Package%')
                  AND l.status = 'approved'
                GROUP BY l.id
                ORDER BY l.id DESC
            ");

            $packages = [];
            foreach ($rows as $row) {
                // Sourced from database: tourists can only view Agreed packages
                $pkgApprovalStatus = \App\Models\PackageService::getOverallStatus(
                    (int) $row['id'],
                    isset($row['package_approval_status']) ? (string) $row['package_approval_status'] : null
                );
                if ($pkgApprovalStatus !== 'agreed') {
                    // Pending and Rejected packages are not visible to tourists
                    continue;
                }
                $packages[] = $this->formatListingAsPackage($row);
            }
            return $packages;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Format a database listing into a full travel package object for the app.
     * Parses real dynamic data from the database using AdminController::buildPackageInspectionData.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatListingAsPackage(array $row): array
    {
        $guide = [
            'id' => (int) ($row['guide_id'] ?? $row['user_id'] ?? 1),
            'name' => (string) (!empty($row['guide_name']) ? $row['guide_name'] : 'Marc Repuesto'),
            'avatar' => (string) ($row['guide_avatar'] ?? ''),
            'bio' => (string) ($row['guide_bio'] ?? ''),
        ];

        $inspected = \App\Controllers\AdminController::buildPackageInspectionData($row, $guide);

        $id = (string) $row['id'];
        $title = (string) $inspected['title'];
        $slug = (string) ($row['slug'] ?? 'pkg-' . $id);
        $summary = (string) ($row['summary'] ?? '');
        $description = (string) $inspected['description'];
        $destination = (string) $inspected['destination'];
        $durationLabel = (string) $inspected['duration_code'];
        $days = (int) $inspected['duration_days'];
        $nights = (int) $inspected['duration_nights'];

        $cover = !empty($row['cover_image'])
            ? (string) $row['cover_image']
            : 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200';

        $gallery = [];
        try {
            $rawGallery = \App\Models\Listing::gallery((int) $row['id']);
            if (is_array($rawGallery)) {
                foreach ($rawGallery as $g) {
                    $imgPath = is_array($g) ? ($g['image_path'] ?? '') : (string) $g;
                    if ($imgPath !== '') {
                        $gallery[] = $imgPath;
                    }
                }
            }
        } catch (\Throwable $e) {
            $gallery = [];
        }
        if (empty($gallery)) {
            $gallery = [$cover];
        }

        // Build flat inclusions array matching the customer app reference screen
        $inclusions = [];

        // 1. Attractions
        if (!empty($inspected['inclusions']['attractions'])) {
            foreach ($inspected['inclusions']['attractions'] as $attr) {
                $inclusions[] = [
                    'type' => 'attraction',
                    'title' => (string) $attr['title'],
                    'subtitle' => !empty($attr['summary']) ? (string) $attr['summary'] : (!empty($attr['area']) ? (string) $attr['area'] : 'Attraction access'),
                    'icon' => 'water-outline',
                    'badge' => 'ATTRACTION',
                ];
            }
        }

        // 2. Things to Do
        if (!empty($inspected['inclusions']['things_to_do'])) {
            foreach ($inspected['inclusions']['things_to_do'] as $todo) {
                $inclusions[] = [
                    'type' => 'activity',
                    'title' => (string) $todo['title'],
                    'subtitle' => !empty($todo['summary']) ? (string) $todo['summary'] : (!empty($todo['area']) ? (string) $todo['area'] : 'Guided experience'),
                    'icon' => 'compass-outline',
                    'badge' => 'THINGS TO DO',
                ];
            }
        }

        // 3. Hotel Stay (if bundled)
        if ($inspected['hotel_included'] && !empty($inspected['hotel'])) {
            $h = $inspected['hotel'];
            $nightsText = $nights > 0 ? "{$nights} Night" . ($nights > 1 ? 's' : '') : '';
            $inclusions[] = [
                'type' => 'accommodation',
                'title' => (string) ($h['hotel_name'] ?? 'Resort Accommodation'),
                'subtitle' => ($nightsText !== '' ? "{$nightsText} • " : '') . ($h['room_name'] ?? 'Deluxe Room'),
                'icon' => 'bed-outline',
                'badge' => 'HOTEL & STAYS',
            ];
        }

        // 4. Vehicle Rental (if bundled)
        if ($inspected['rental_included'] && !empty($inspected['rental'])) {
            $v = $inspected['rental'];
            $vTitle = (string) (!empty($v['vehicle_name']) ? $v['vehicle_name'] : ($v['make'] . ' ' . $v['model']));
            $specsSummary = !empty($inspected['rental_specs']) ? ' with ' . implode(', ', array_slice($inspected['rental_specs'], 0, 2)) : '';
            $inclusions[] = [
                'type' => 'rental',
                'title' => $vTitle,
                'subtitle' => "{$days}-Day rental" . $specsSummary,
                'icon' => 'car-sport-outline',
                'badge' => 'VEHICLE RENTAL',
            ];
        }

        // 5. Custom Perks
        if (!empty($inspected['custom_perks'])) {
            foreach ($inspected['custom_perks'] as $perk) {
                $inclusions[] = [
                    'type' => 'perk',
                    'title' => (string) $perk,
                    'subtitle' => 'Included in single bundle',
                    'icon' => 'shield-checkmark-outline',
                    'badge' => 'INCLUDED PERK',
                ];
            }
        }

        if (empty($inclusions)) {
            $inclusions = [
                [
                    'type' => 'activity',
                    'title' => 'Curated Experience Activities',
                    'subtitle' => 'Included guided tour sessions',
                    'icon' => 'compass-outline',
                    'badge' => 'THINGS TO DO',
                ],
                [
                    'type' => 'perk',
                    'title' => 'Licensed Local Guide & Trip Host',
                    'subtitle' => 'Professional on-site coordination',
                    'icon' => 'shield-checkmark-outline',
                    'badge' => 'INCLUDED PERK',
                ],
            ];
        }

        // Accommodation Detail
        $accommodationDetail = null;
        if ($inspected['hotel_included'] && !empty($inspected['hotel'])) {
            $h = $inspected['hotel'];
            $accommodationDetail = [
                'hotelName' => (string) ($h['hotel_name'] ?? 'Resort & Hotel'),
                'roomType' => (string) ($h['room_name'] ?? 'Deluxe Room'),
                'nights' => $nights,
                'amenities' => !empty($inspected['hotel_amenities']) ? $inspected['hotel_amenities'] : ['Free High-Speed Wi-Fi', 'Swimming Pool Access', 'Air Conditioning'],
                'partnerName' => (string) ($h['partner_name'] ?? 'Hotel Partner'),
                'checkIn' => (string) ($h['check_in_time'] ?? '02:00 PM'),
                'checkOut' => (string) ($h['check_out_time'] ?? '12:00 PM'),
                'coverImage' => (string) ($h['hotel_cover'] ?? ($h['room_image'] ?? '')),
            ];
        }

        // Rental Detail
        $rentalDetail = null;
        if ($inspected['rental_included'] && !empty($inspected['rental'])) {
            $v = $inspected['rental'];
            $rentalDetail = [
                'vehicleType' => (string) ($v['vehicle_type'] ?? 'Scooter'),
                'model' => (string) (!empty($v['vehicle_name']) ? $v['vehicle_name'] : ($v['make'] . ' ' . $v['model'])),
                'duration' => "{$days} Days Rental",
                'features' => !empty($inspected['rental_specs']) ? $inspected['rental_specs'] : ['Automatic Transmission'],
                'partnerName' => (string) ($v['partner_name'] ?? 'Rental Partner'),
                'location' => (string) ($v['location'] ?? $destination),
                'image' => (string) ($v['vehicle_image'] ?? ''),
                'handover' => 'Day 1 - 01:00 PM',
                'dropoff' => "Day {$days} - 12:00 PM",
            ];
        }

        $hostInfo = $inspected['host'];

        return [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'tagline' => $summary !== '' ? $summary : substr($description, 0, 140),
            'description' => $description,
            'destination' => $destination,
            'duration' => $durationLabel,
            'durationDays' => $days,
            'durationNights' => $nights,
            'category' => 'adventure',
            'categoryLabel' => (string) $inspected['category_label'],
            'assurance' => (string) $inspected['assurance'],
            'price' => (float) $inspected['price'],
            'originalPrice' => round(((float) $inspected['price']) * 1.15),
            'badge' => (string) $inspected['badge'],
            'featured' => !empty($row['is_featured']),
            'coverImage' => $cover,
            'gallery' => $gallery,
            'rating' => (float) $inspected['rating'],
            'reviewCount' => (int) $inspected['review_count'],
            'maxGroupSize' => max(2, (int) ($row['capacity'] ?? 6)),
            'meetingPoint' => (string) $inspected['meeting_point'],
            'tags' => !empty($inspected['tags']) ? $inspected['tags'] : ['guide 1', 'guide 2', 'guide 3'],
            'host' => [
                'id' => (int) $hostInfo['id'],
                'name' => (string) $hostInfo['name'],
                'avatar' => (string) $hostInfo['avatar'],
                'role' => (string) $hostInfo['role'],
                'badge' => (string) $hostInfo['badge'],
                'rating' => (float) $hostInfo['rating'],
                'reviewCount' => (int) $hostInfo['reviews_count'],
                'toursCount' => (int) $hostInfo['tours_count'],
                'bio' => (string) $hostInfo['bio'],
                'responseRate' => '100% (within 10 mins)',
                'verified' => true,
            ],
            'inclusions' => $inclusions,
            'exclusions' => !empty($inspected['exclusions']) ? $inspected['exclusions'] : [
                'Airfare to and from Cebu (Mactan)',
                'Personal souvenir shopping & alcoholic drinks',
                'Optional zip-line upgrade at Kawasan starting point (₱600)',
            ],
            'itinerary' => $inspected['itinerary'],
            'hotelIncluded' => (bool) $inspected['hotel_included'],
            'accommodationDetail' => $accommodationDetail,
            'rentalIncluded' => (bool) $inspected['rental_included'],
            'rentalDetail' => $rentalDetail,
            'approval_status' => 'agreed',
            'package_approval_status' => 'agreed',
            'reviews' => (function() use ($id) {
                if ((int)$id <= 0) return [];
                try {
                    $rawRev = \App\Models\Review::forListing((int)$id);
                    $out = [];
                    foreach ($rawRev as $rv) {
                        $out[] = [
                            'id' => (int) $rv['id'],
                            'userName' => (string) ($rv['user_name'] ?? 'Traveler'),
                            'userAvatar' => (string) ($rv['user_avatar'] ?? ''),
                            'rating' => (float) ($rv['rating'] ?? 5),
                            'comment' => (string) ($rv['comment'] ?? ''),
                            'createdAt' => (string) ($rv['created_at'] ?? ''),
                        ];
                    }
                    return $out;
                } catch (\Throwable $e) {
                    return [];
                }
            })(),
            'policy' => [
                'cancellation' => (string) $inspected['cancellation_policy'],
                'confirmation' => "Instant confirmation. Guide {$hostInfo['name']} will coordinate your itinerary.",
                'requirements' => !empty($inspected['requirements']) ? $inspected['requirements'] : [
                    'Valid Government ID or Passport for all travelers',
                    'Valid Motorcycle / Driver’s license for scooter rider',
                ],
            ],
        ];
    }

    /**
     * Internal catalog of guide-curated travel packages.
     * @return array<int, array<string, mixed>>
     */
    private function getStaticPackages(): array
    {
        return [
            [
                'id' => 'pkg-south-cebu-3d2n',
                'slug' => 'south-cebu-expedition-3d2n',
                'title' => '3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter',
                'tagline' => 'The ultimate all-inclusive South Cebu adventure curated by DOT-certified local guide Marc.',
                'description' => 'Immerse yourself in the thrill of South Cebu. This comprehensive 3-day travel bundle combines the world-famous Kawasan Falls Canyoneering, the hypnotic Moalboal Sardine Run and sea turtle sanctuary, 2 nights at a beachfront resort in Moalboal, and a dedicated 155cc Yamaha NMAX scooter for ultimate freedom across coastal vistas. Every permit, entrance, safety gear, and licensed guide assistance is completely covered.',
                'destination' => 'Moalboal & Badian, South Cebu',
                'duration' => '3D2N',
                'durationDays' => 3,
                'durationNights' => 2,
                'category' => 'adventure',
                'categoryLabel' => 'Adventure & Nature',
                'price' => 8499,
                'originalPrice' => 9900,
                'badge' => 'Best Seller',
                'featured' => true,
                'coverImage' => 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
                'localImageKey' => 'kawasan',
                'gallery' => [
                    'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
                    'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
                    'https://images.unsplash.com/photo-1558981806-ec527fa84c39?w=1200',
                ],
                'rating' => 4.9,
                'reviewCount' => 38,
                'maxGroupSize' => 8,
                'meetingPoint' => 'Sea Breeze Resort Lobby or Panagsama Beach Tourism Center, Moalboal',
                'host' => [
                    'id' => 2,
                    'name' => 'Marc Repuesto',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
                    'role' => 'Licensed Regional Tour Guide',
                    'badge' => 'DOT Accredited',
                    'rating' => 4.9,
                    'reviewCount' => 48,
                    'toursCount' => 112,
                    'bio' => 'Native Moalboal local with 7+ years of professional outdoor guiding experience. Specializes in canyoneering safety, marine photography, and authentic Cebuano travel experiences.',
                    'responseRate' => '100% (within 10 mins)',
                    'verified' => true,
                ],
                'inclusions' => [
                    [
                        'type' => 'attraction',
                        'title' => 'Kawasan Falls Natural Park',
                        'subtitle' => 'Badian Ecological Park entrance & conservation ticket',
                        'icon' => 'water-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'activity',
                        'title' => 'Full Kawasan Canyoneering & Cliff Jumps',
                        'subtitle' => 'Guided river trek with 7 cliff jumps (3m to 12m) & natural water slides',
                        'icon' => 'compass-outline',
                        'badge' => 'Things to Do',
                    ],
                    [
                        'type' => 'activity',
                        'title' => 'Moalboal Sardine Run & Sea Turtle Snorkel',
                        'subtitle' => 'Panagsama reef marine sanctuary with mask, snorkel, and life vest',
                        'icon' => 'fish-outline',
                        'badge' => 'Things to Do',
                    ],
                    [
                        'type' => 'accommodation',
                        'title' => 'Sea Breeze Oceanfront Resort',
                        'subtitle' => '2 Nights • Deluxe Queen Room with balcony & pool access',
                        'icon' => 'bed-outline',
                        'badge' => 'Hotel & Stays',
                    ],
                    [
                        'type' => 'rental',
                        'title' => 'Yamaha NMAX 155cc Automatic Scooter',
                        'subtitle' => '3-Day rental with 2 helmets, phone mount, and insurance',
                        'icon' => 'car-sport-outline',
                        'badge' => 'Vehicle Rental',
                    ],
                    [
                        'type' => 'perk',
                        'title' => 'All Environmental & Local Tourism Fees',
                        'subtitle' => 'Includes river rescue gear, wet shoes, and recovery hot lunch',
                        'icon' => 'shield-checkmark-outline',
                        'badge' => 'Included Perk',
                    ],
                ],
                'exclusions' => [
                    'Airfare to and from Cebu (Mactan)',
                    'Personal souvenir shopping & alcoholic drinks',
                    'Optional zip-line upgrade at Kawasan starting point (₱600)',
                ],
                'itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary',
                        'description' => 'Meet your guide host Marc, pick up your scooter, check in to your resort, and snorkel with turtles.',
                        'highlights' => [
                            'Pick up your Yamaha NMAX scooter in Moalboal (or complimentary town greeting)',
                            'Check-in at Sea Breeze Oceanfront Resort and freshen up',
                            'Afternoon guided snorkeling: Swim alongside millions of sardines and resident sea turtles',
                            'Sunset seafood dinner recommendation along Panagsama beachfront strip',
                        ],
                        'schedule' => [
                            ['time' => '01:00 PM', 'activity' => 'Resort check-in & scooter handover briefing'],
                            ['time' => '03:00 PM', 'activity' => 'Guided snorkeling session at Panagsama marine sanctuary'],
                            ['time' => '05:30 PM', 'activity' => 'Golden hour sunset viewing and leisure dinner'],
                        ],
                    ],
                    [
                        'day' => 2,
                        'title' => 'Day 2: Full Kawasan Canyoneering Adrenaline Expedition',
                        'description' => 'The highlight adventure! Trek, leap, swim, and slide through the turquoise river canyon of Badian.',
                        'highlights' => [
                            'Hearty breakfast at resort before morning departure',
                            'Scenic 25-minute coastal scooter ride to Badian registration outpost',
                            'Complete safety briefing, fitting of certified helmets and life vests',
                            'Trek through lush tropical jungle into the canyon headwaters',
                            'Leap into crystalline turquoise pools (jumps optional, safe paths available)',
                            'Feast on traditional Filipino boodle lunch beside the final waterfall cascade',
                        ],
                        'schedule' => [
                            ['time' => '07:30 AM', 'activity' => 'Complimentary resort breakfast'],
                            ['time' => '08:30 AM', 'activity' => 'Scenic convoy ride to Badian Canyoneering base'],
                            ['time' => '09:15 AM', 'activity' => 'Canyon descent begins with certified master guide'],
                            ['time' => '01:30 PM', 'activity' => 'Finish at Main Kawasan cascade & hot boodle fight lunch'],
                            ['time' => '03:30 PM', 'activity' => 'Return to resort for swimming and evening rest'],
                        ],
                    ],
                    [
                        'day' => 3,
                        'title' => 'Day 3: Pescador Island Boat Tour & Farewell Cebu',
                        'description' => 'Morning marine reserve boat ride, scenic cliff coastal cruise, and flexible check-out.',
                        'highlights' => [
                            'Morning outrigger boat cruise to Pescador Island marine park',
                            'Coral reef drift snorkeling over underwater drop-offs',
                            'Return to resort for shower and packing',
                            'Scooter return inspection and travel assistance for your next destination',
                        ],
                        'schedule' => [
                            ['time' => '07:00 AM', 'activity' => 'Early morning Pescador Island boat expedition'],
                            ['time' => '10:00 AM', 'activity' => 'Return to resort, pack, and relax by the pool'],
                            ['time' => '12:00 PM', 'activity' => 'Check out and hand back scooter'],
                        ],
                    ],
                ],
                'accommodationDetail' => [
                    'hotelName' => 'Sea Breeze Oceanfront Resort',
                    'roomType' => 'Deluxe Queen Room (Balcony with sea & pool view)',
                    'nights' => 2,
                    'amenities' => [
                        'Free High-Speed Wi-Fi',
                        'Swimming Pool Access',
                        'Complimentary Daily Breakfast',
                        'Air Conditioning & Hot Water Shower',
                        'Beachfront Lounge with Sunset View',
                    ],
                    'partnerName' => 'Sea Breeze Hospitality Group',
                ],
                'rentalDetail' => [
                    'vehicleType' => 'Automatic Scooter',
                    'model' => 'Yamaha NMAX 155cc ABS',
                    'duration' => '3 Full Days (Unlimited South Cebu mileage)',
                    'features' => [
                        '2 DOT-Certified Safety Helmets',
                        'Handlebar Phone Mount & USB Charger Port',
                        'Full Tank on Pickup',
                        'Comprehensive 24/7 Roadside Assistance',
                    ],
                    'partnerName' => 'Cebu Swift Rentals',
                ],
                'policy' => [
                    'cancellation' => 'Free cancellation up to 48 hours before departure. 50% refund within 24-48 hours.',
                    'confirmation' => 'Instant confirmation. Guide Marc will reach out via in-app chat immediately after booking.',
                    'requirements' => [
                        'Valid Government ID or Passport for all travelers',
                        'Valid Motorcycle / Driver’s license for scooter rider',
                        'Moderate physical fitness for canyoneering (swimming skills not mandatory as vests are required)',
                    ],
                ],
            ],
            [
                'id' => 'pkg-oslob-sumilon-2d1n',
                'slug' => 'oslob-whale-shark-sumilon-2d1n',
                'title' => '2D1N Oslob Whale Shark & Sumilon Island Sandbar Retreat',
                'tagline' => 'Swim alongside gentle ocean giants and lounge on private turquoise coral sandbars.',
                'description' => 'Experience the magic of South-Eastern Cebu in pure comfort. Hosted by marine conservation advocate Maria Santos, this curated 2-day escape features the world-renowned Oslob Whale Shark interaction, an exclusive boat crossing to Sumilon Island’s shifting sandbar, 1 night stay at Bluewater Sumilon Resort, and private roundtrip van transportation from Cebu City or Mactan.',
                'destination' => 'Oslob & Sumilon Island, Cebu',
                'duration' => '2D1N',
                'durationDays' => 2,
                'durationNights' => 1,
                'category' => 'island-escape',
                'categoryLabel' => 'Island & Marine',
                'price' => 6200,
                'originalPrice' => 7450,
                'badge' => 'Featured',
                'featured' => true,
                'coverImage' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
                'localImageKey' => 'whaleshark',
                'gallery' => [
                    'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
                    'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
                ],
                'rating' => 4.9,
                'reviewCount' => 29,
                'maxGroupSize' => 10,
                'meetingPoint' => 'Cebu City / Mactan Hotel Lobby (Private Door-to-Door Pickup Included)',
                'host' => [
                    'id' => 3,
                    'name' => 'Maria Santos',
                    'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300',
                    'role' => 'Marine Biologist & Tour Guide',
                    'badge' => 'DOT Accredited',
                    'rating' => 5.0,
                    'reviewCount' => 32,
                    'toursCount' => 84,
                    'bio' => 'Passionate marine conservationist born in Oslob. Dedicated to ethical eco-tourism, respectful wildlife encounters, and warm Cebuano hospitality.',
                    'responseRate' => '98% (within 15 mins)',
                    'verified' => true,
                ],
                'inclusions' => [
                    [
                        'type' => 'attraction',
                        'title' => 'Sumilon Island Protected Sandbar',
                        'subtitle' => 'Round-trip private boat transfers and marine reserve access',
                        'icon' => 'sunny-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'activity',
                        'title' => 'Oslob Whale Shark Eco Encounter',
                        'subtitle' => 'Early priority boat slot, briefing, snorkel gear & underwater photography guidance',
                        'icon' => 'water-outline',
                        'badge' => 'Things to Do',
                    ],
                    [
                        'type' => 'attraction',
                        'title' => 'Tumalog Falls Enchanted Cascade',
                        'subtitle' => 'Visit the ethereal curtain waterfall nestled in pristine limestone cliffs',
                        'icon' => 'leaf-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'accommodation',
                        'title' => 'Bluewater Sumilon Island Resort',
                        'subtitle' => '1 Night • Deluxe Island Villa with breakfast buffet',
                        'icon' => 'bed-outline',
                        'badge' => 'Hotel & Stays',
                    ],
                    [
                        'type' => 'rental',
                        'title' => 'Toyota HiAce Commuter Van Transfer',
                        'subtitle' => 'Private round-trip door-to-door transfer with professional chauffeur',
                        'icon' => 'bus-outline',
                        'badge' => 'Vehicle Rental',
                    ],
                    [
                        'type' => 'perk',
                        'title' => 'Sumilon Island Picnic Lunch Buffet',
                        'subtitle' => 'Fresh grilled Cebuano specialties and tropical juices',
                        'icon' => 'restaurant-outline',
                        'badge' => 'Included Perk',
                    ],
                ],
                'exclusions' => [
                    'Personal scuba equipment rental (optional upgrade)',
                    'Gratuities for driver and boatmen',
                ],
                'itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Day 1: Southbound Coastline Cruise & Sumilon Island Sandbar',
                        'description' => 'Morning van pickup, scenic coastal drive, boat ride to Sumilon, and resort relaxation.',
                        'highlights' => [
                            'Early morning pickup from your Cebu City or Mactan hotel in a private Toyota HiAce van',
                            'Arrive at Oslob mainland and board the scenic 15-minute boat to Sumilon Island',
                            'Check-in at Bluewater Sumilon Resort',
                            'Lounge on the shifting white sandbar surrounded by turquoise lagoons',
                            'Lagoon kayaking and nature trail walk to the historic 19th-century Spanish watchtower',
                        ],
                        'schedule' => [
                            ['time' => '06:00 AM', 'activity' => 'Private door-to-door van pickup'],
                            ['time' => '09:30 AM', 'activity' => 'Boat crossing to Sumilon Island'],
                            ['time' => '12:00 PM', 'activity' => 'Sumilon beachfront buffet lunch'],
                            ['time' => '02:00 PM', 'activity' => 'Sandbar snorkeling and kayak adventure'],
                        ],
                    ],
                    [
                        'day' => 2,
                        'title' => 'Day 2: Whale Shark Encounter & Tumalog Falls',
                        'description' => 'Early morning interaction with whale sharks followed by Tumalog Falls mist.',
                        'highlights' => [
                            'Pre-dawn priority boat back to Oslob marine sanctuary for first-light whale shark viewing',
                            'Snorkel alongside the gentle giants with certified marine guide Maria',
                            'Short scenic ride up the mountain to Tumalog Falls curtain cascades',
                            'Return van trip to Cebu City with optional Carcar Lechon lunch stop',
                        ],
                        'schedule' => [
                            ['time' => '06:30 AM', 'activity' => 'Priority whale shark briefing and snorkeling slot'],
                            ['time' => '08:30 AM', 'activity' => 'Tumalog Falls exploration & bamboo raft photography'],
                            ['time' => '11:00 AM', 'activity' => 'Check out and board return van transfer'],
                            ['time' => '03:30 PM', 'activity' => 'Arrival back at Cebu City hotel'],
                        ],
                    ],
                ],
                'accommodationDetail' => [
                    'hotelName' => 'Bluewater Sumilon Island Resort',
                    'roomType' => 'Deluxe Island Room (Air-conditioned, private balcony)',
                    'nights' => 1,
                    'amenities' => [
                        'Infinity Pool overlooking the Cebu Strait',
                        'Private Island Beach & Sandbar Access',
                        'Complimentary Island Breakfast & Welcome Drinks',
                        'Kayaks and Snorkeling Gear Included',
                    ],
                    'partnerName' => 'Bluewater Resorts Group',
                ],
                'rentalDetail' => [
                    'vehicleType' => 'Private Passenger Van',
                    'model' => 'Toyota HiAce Grandia / Commuter',
                    'duration' => 'Round-trip door-to-door service (Cebu City <-> Oslob)',
                    'features' => [
                        'Dedicated professional driver & fuel included',
                        'Spacious captain seats & dual air-conditioning',
                        'Complimentary cold bottled waters on board',
                    ],
                    'partnerName' => 'Cebu Island Transport Co.',
                ],
                'policy' => [
                    'cancellation' => 'Free cancellation up to 72 hours prior to scheduled pickup date.',
                    'confirmation' => 'Instant confirmation with QR travel voucher issued immediately.',
                    'requirements' => [
                        'Guests must follow strict Oslob marine wildlife guidelines (no sunscreen with oxybenzone, 3m distance)',
                    ],
                ],
            ],
            [
                'id' => 'pkg-cebu-highlands-1day',
                'slug' => 'cebu-highlands-cultural-heritage-1day',
                'title' => '1-Day Cebu Highlands & Cultural Heritage Showcase',
                'tagline' => 'Discover panoramic mountain crests, blooming flower gardens, and historic landmark treasures.',
                'description' => 'The definitive cultural and mountain ridge day tour of Cebu. Curated and guided by heritage specialist Carlos Dizon, this seamless full-day experience takes you from ancient Spanish fortresses in downtown Cebu to the breathtaking Temple of Leah and colorful Sirao Flower Gardens perched 2,000 feet above the city in the Transcentral Highway mountains.',
                'destination' => 'Busay Mountain Ridge & Cebu City',
                'duration' => 'Full Day (8h)',
                'durationDays' => 1,
                'durationNights' => 0,
                'category' => 'day-tour',
                'categoryLabel' => 'Culture & Scenic',
                'price' => 2450,
                'originalPrice' => 2950,
                'badge' => 'Day Trip',
                'featured' => false,
                'coverImage' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=1200',
                'localImageKey' => 'templeofleah',
                'gallery' => [
                    'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?w=1200',
                    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=1200',
                ],
                'rating' => 4.8,
                'reviewCount' => 44,
                'maxGroupSize' => 6,
                'meetingPoint' => 'Hotel Pickup anywhere in Cebu City or Mandaue',
                'host' => [
                    'id' => 4,
                    'name' => 'Carlos Dizon',
                    'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300',
                    'role' => 'Heritage & Architectural Guide',
                    'badge' => 'Master Guide',
                    'rating' => 4.8,
                    'reviewCount' => 65,
                    'toursCount' => 140,
                    'bio' => 'Lifelong Cebuano historian with a deep passion for architectural heritage, colonial folklore, and local mountain coffee culture.',
                    'responseRate' => '95% (within 30 mins)',
                    'verified' => true,
                ],
                'inclusions' => [
                    [
                        'type' => 'attraction',
                        'title' => 'Temple of Leah (Cebu’s Taj Mahal)',
                        'subtitle' => 'Neoclassical Roman temple with panoramic skyline views of Cebu City',
                        'icon' => 'business-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'attraction',
                        'title' => 'Sirao Pictorial Flower Garden',
                        'subtitle' => 'Vibrant celosia blooms and mountain photo spots (Little Amsterdam)',
                        'icon' => 'rose-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'activity',
                        'title' => 'Historic Fort San Pedro & Magellan’s Cross',
                        'subtitle' => 'Private guided walking tour through 450 years of Cebuano history',
                        'icon' => 'ribbon-outline',
                        'badge' => 'Things to Do',
                    ],
                    [
                        'type' => 'rental',
                        'title' => 'Private Chauffeur Sedan (Toyota Vios)',
                        'subtitle' => 'Full 8-hour air-conditioned private vehicle with all gas & parking included',
                        'icon' => 'car-outline',
                        'badge' => 'Vehicle Rental',
                    ],
                    [
                        'type' => 'perk',
                        'title' => 'All Entrance Fees & Bottled Refreshments',
                        'subtitle' => 'Skip-the-line express admission to all attractions',
                        'icon' => 'sparkles-outline',
                        'badge' => 'Included Perk',
                    ],
                ],
                'exclusions' => ['Lunch meals and snacks', 'Personal souvenir purchases'],
                'itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Full Day Itinerary (8:30 AM – 4:30 PM)',
                        'description' => 'Complete historic colonial district in the morning, followed by cooling mountain heights.',
                        'highlights' => [
                            '08:30 AM: Doorstep hotel pickup in comfortable air-conditioned private sedan',
                            '09:00 AM: Historic walking tour: Magellan’s Cross, Basilica Minore del Santo Niño & Fort San Pedro',
                            '11:30 AM: Ascent into Busay Mountain Ridge with lunch break at a scenic overlook restaurant',
                            '01:00 PM: Explore the grand Roman pillars and museum of Temple of Leah',
                            '02:30 PM: Walk among colorful celosia blooms and giant hand sculptures at Sirao Garden',
                            '04:30 PM: Relaxing descent back to your hotel',
                        ],
                    ],
                ],
                'rentalDetail' => [
                    'vehicleType' => 'Sedan with Driver',
                    'model' => 'Toyota Vios 1.5G AT',
                    'duration' => '8 Hours (Full Tour Duration)',
                    'features' => ['Air-conditioned comfort', 'Private dedicated driver', 'Fuel and parking fees included'],
                    'partnerName' => 'Cebu City Executive Wheels',
                ],
                'policy' => [
                    'cancellation' => 'Cancel up to 24 hours in advance for a full refund.',
                    'confirmation' => 'Instant booking confirmation.',
                    'requirements' => ['Comfortable walking shoes and camera/phone for stunning photos.'],
                ],
            ],
            [
                'id' => 'pkg-bantayan-island-3d2n',
                'slug' => 'bantayan-island-paradise-3d2n',
                'title' => '3D2N Bantayan Tropical Paradise: Virgin Island, White Beaches & E-Trike',
                'tagline' => 'Unwind on northern Cebu’s powdered sands with fresh seafood and island outrigger cruising.',
                'description' => 'Step into the laid-back rhythm of Bantayan Island. Featuring powdery white beaches, crystal clear shallows, Virgin Island cliff jumping and snorkeling, 2 nights beachfront in Santa Fe, and your own electric trike / scooter to cruise sleepy fishing villages and sunset cafes.',
                'destination' => 'Bantayan Island & Santa Fe, North Cebu',
                'duration' => '3D2N',
                'durationDays' => 3,
                'durationNights' => 2,
                'category' => 'island-escape',
                'categoryLabel' => 'Tropical Island',
                'price' => 7650,
                'originalPrice' => 8900,
                'badge' => 'Popular',
                'featured' => false,
                'coverImage' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
                'localImageKey' => 'bantayan',
                'gallery' => [
                    'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200',
                    'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
                ],
                'rating' => 4.9,
                'reviewCount' => 22,
                'maxGroupSize' => 8,
                'meetingPoint' => 'Santa Fe Port Ferry Terminal, Bantayan Island',
                'host' => [
                    'id' => 2,
                    'name' => 'Marc Repuesto',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300',
                    'role' => 'Licensed Regional Tour Guide',
                    'badge' => 'DOT Accredited',
                    'rating' => 4.9,
                    'reviewCount' => 48,
                    'toursCount' => 112,
                    'bio' => 'Expert island hopper and explorer passionate about showcasing Cebu’s untouched natural beauty.',
                    'responseRate' => '100% (within 10 mins)',
                    'verified' => true,
                ],
                'inclusions' => [
                    [
                        'type' => 'attraction',
                        'title' => 'Virgin Island Marine Reserve & Cliff Jump',
                        'subtitle' => 'Round-trip private motorized banca cruise and island entry',
                        'icon' => 'boat-outline',
                        'badge' => 'Attraction',
                    ],
                    [
                        'type' => 'activity',
                        'title' => 'Kota Beach Sandbar & Ogtong Cave Exploration',
                        'subtitle' => 'Swim in the natural freshwater cave and relax on the curved sandbar',
                        'icon' => 'sparkles-outline',
                        'badge' => 'Things to Do',
                    ],
                    [
                        'type' => 'accommodation',
                        'title' => 'Amihan Beach Cabanas',
                        'subtitle' => '2 Nights • Beachfront Deluxe Bamboo Cottage with daily breakfast',
                        'icon' => 'bed-outline',
                        'badge' => 'Hotel & Stays',
                    ],
                    [
                        'type' => 'rental',
                        'title' => 'Electric Trike / Island Scooter',
                        'subtitle' => '3-Day rental for effortless island exploration at your own pace',
                        'icon' => 'flash-outline',
                        'badge' => 'Vehicle Rental',
                    ],
                    [
                        'type' => 'perk',
                        'title' => 'Port Greeting & Luggage Transfers',
                        'subtitle' => 'Hassle-free meet-and-greet at Santa Fe port',
                        'icon' => 'checkmark-circle-outline',
                        'badge' => 'Included Perk',
                    ],
                ],
                'exclusions' => ['Ferry tickets from Hagnaya to Santa Fe', 'Dinner and personal expenses'],
                'itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Day 1: Port Welcome, Scooter Pickup & Sunset at Kota Beach',
                        'description' => 'Arrive at Santa Fe port, check in to beach cabana, and witness legendary Bantayan sunset.',
                        'highlights' => [
                            'Port meet and greet with Guide Marc’s local team',
                            'Check in to your beachfront cabana at Amihan Beach Resort',
                            'Pick up your electric scooter or e-trike',
                            'Sunset stroll along the world-famous curved sandbar of Kota Beach',
                        ],
                    ],
                    [
                        'day' => 2,
                        'title' => 'Day 2: Virgin Island Banca Expedition & Cliff Jumping',
                        'description' => 'Private outrigger boat cruise to Virgin Island for pristine reef snorkeling.',
                        'highlights' => [
                            'Board private motorized banca for scenic 20-minute ride to Virgin Island',
                            'Swim in powdery crystal shallows and snorkel with clownfish',
                            'Optional 20-foot wooden cliff plunge into deep azure waters',
                            'Fresh grilled seafood lunch on the island beach',
                            'Afternoon dip in Ogtong Cave’s subterranean freshwater spring',
                        ],
                    ],
                    [
                        'day' => 3,
                        'title' => 'Day 3: Bantayan Heritage Town, Dried Fish Market & Departure',
                        'description' => 'Morning ride to the centuries-old Saints Peter and Paul Church and port drop-off.',
                        'highlights' => [
                            'Ride to Bantayan town proper to visit the 1863 Coral Stone Church',
                            'Stop at the famous Bantayan Danggit market for authentic local souvenirs',
                            'Return scooter and transfer back to Santa Fe port for your mainland ferry',
                        ],
                    ],
                ],
                'accommodationDetail' => [
                    'hotelName' => 'Amihan Beach Cabanas',
                    'roomType' => 'Deluxe Beachfront Bamboo Cottage',
                    'nights' => 2,
                    'amenities' => [
                        'Direct Step-Out Beach Access',
                        'Tropical Artisan Breakfast Included',
                        'Air Conditioning & Hot Water',
                        'High-Speed Beach Wi-Fi',
                    ],
                    'partnerName' => 'Amihan Hospitality',
                ],
                'rentalDetail' => [
                    'vehicleType' => 'E-Scooter / Trike',
                    'model' => 'Island Eco-Scooter 125',
                    'duration' => '3 Full Days',
                    'features' => ['Easy automatic drive', '2 safety helmets included', 'Free recharge at resort'],
                    'partnerName' => 'Bantayan Eco-Wheels',
                ],
                'policy' => [
                    'cancellation' => 'Free cancellation up to 48 hours prior to arrival.',
                    'confirmation' => 'Instant confirmation with guide contact info provided immediately.',
                    'requirements' => ['Beach-ready clothing and waterproof bag for island hopping.'],
                ],
            ],
        ];
    }
}

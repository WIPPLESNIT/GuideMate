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
use App\Models\RentalRequest;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
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
        $alreadyBooked = $userId !== null && Booking::hasActivePaidBooking($listingId, $userId);
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

        $this->json([
            'listing' => $listingPayload,
            'already_booked' => $alreadyBooked,
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
     * POST /api/auth/social — sign in with Google or Facebook.
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
            // A real provider token was supplied — always verify it server-side.
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
     * GET /api/auth/google/start — begin the Expo Go-compatible Google login.
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
     * GET /api/auth/google/pending?session= — phone polls the LAN for the
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
     * POST /api/auth/google/exchange — the mobile WebView intercepted Google's
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
     * GET /api/auth/google/callback — Google redirects here with an auth code.
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
     * This project’s Console entry is the static ngrok URL (localhost is not registered).
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
     * GET /api/auth/facebook/start — begin the Expo Go-compatible Facebook
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
     * GET /api/auth/facebook/callback — exchange the code, read the app-scoped
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
            . '<p>Signing you in…</p>'
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
     * local part (e.g. "mole.kraine" / "mole_kraine" → "Mole Kraine"). Returns
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

        // Build the response the same way whether or not the email exists, so we
        // never reveal which addresses are registered.
        $response = [
            'message' => 'If that email is registered, we sent a password reset link. '
                . 'Please check your inbox (and your spam folder).',
        ];

        $user = User::findByEmail($email);
        if ($user !== null && ($user['role'] ?? '') !== 'admin') {
            $token = PasswordReset::create($email);
            // Build the link from the host the phone actually connected to (e.g. the
            // LAN IP), so the link is reachable from the device rather than localhost.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $link = rtrim($scheme . '://' . $host . App::basePath(), '/') . '/reset-password/' . $token;
            NotificationService::passwordReset($email, $token, $link);

            $mailConfigured = trim((string) (App::config('mail')['from'] ?? '')) !== '';
            $debug = (bool) (App::config('app')['debug'] ?? false);
            // Dev fallback: if no SMTP is configured yet, hand the link back so the
            // reset flow is testable end-to-end without a mail server.
            if ($debug && !$mailConfigured) {
                $response['dev_reset_url'] = $link;
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
            $address = trim((string) ($b['address'] ?? ''));
            $area = (string) ($b['area'] ?? '');
            $title = (string) ($b['listing_title'] ?? '');
            $googleQuery = $title . ($address !== '' ? ', ' . $address : ', ' . ($area !== '' ? $area : 'Cebu') . ', Philippines');
            $date = (string) ($b['booking_date'] ?? '');

            $pins[] = [
                'id' => (int) $b['id'],
                'title' => $title,
                'area' => $area,
                'address' => $address,
                'googleQuery' => $googleQuery,
                'date' => $date,
                'dateLabel' => $date !== '' ? date('M j, Y', strtotime($date)) : '',
                'lat' => (float) $b['latitude'],
                'lng' => (float) $b['longitude'],
                'upcoming' => $date !== '' && $date >= $today,
                'approximate' => !empty($b['approximate']),
            ];
        }

        $maps = App::config('maps') ?? [];
        $this->json([
            'bookings' => $pins,
            'mapbox_token' => trim((string) ($maps['mapbox_access_token'] ?? '')),
            'mapillary_token' => trim((string) ($maps['mapillary_access_token'] ?? '')),
        ]);
    }

    /** GET /api/maps — public Mapbox token for live traffic (no login). */
    public function mapsConfig(): void
    {
        $maps = App::config('maps') ?? [];
        $this->json([
            'mapbox_token' => trim((string) ($maps['mapbox_access_token'] ?? '')),
        ]);
    }

    /** GET /api/vouchers — Sale tab catalog plus which codes this account already used. */
    public function vouchers(): void
    {
        $userId = ApiAuth::id();
        $this->json([
            'vouchers' => PromoCode::catalogForUser($userId),
        ]);
    }

    /** POST /api/vouchers/validate — check a code before checkout. */
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
        $time = substr(trim((string) ($body['booking_time'] ?? '')), 0, 5);
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
        if ($date === '') {
            $this->json(['error' => 'Booking date is required.'], 422);
            return;
        }
        // A tourist can't hold two active bookings for the same experience.
        if (Booking::hasActivePaidBooking($listingId, $userId)) {
            $this->json(['error' => 'You already have a booking for this experience.'], 409);
            return;
        }
        // Guide-level conflict: a guide can only run one tour at a time, so if
        // ANY tourist already booked this guide (on any of their listings) for
        // this date + time, no one else can take that same slot.
        $guideId = (int) ($listing['user_id'] ?? 0);
        if ($time !== '' && Booking::isGuideSlotBooked($guideId, $date, $time)) {
            $this->json(['error' => 'This guide is already booked for that date and time. Please pick another slot.'], 409);
            return;
        }
        if ($time === '' && Booking::isGuideDateBooked($guideId, $date)) {
            $this->json(['error' => 'This guide is already booked on that date. Please pick another day.'], 409);
            return;
        }

        $price = Listing::effectivePrice($listing);
        $subtotal = $price * $guests;
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
        $paymentNote = 'Paid via ' . $methodLabel;
        if ($cardLast4 !== '') {
            $paymentNote .= ' ****' . substr($cardLast4, -4);
        }
        if ($paymentReference !== '') {
            $paymentNote .= ' · Ref: ' . $paymentReference;
        }
        $fullNotes = trim($notes === '' ? $paymentNote : ($notes . "\n" . $paymentNote));

        $bookingId = Booking::create($listingId, $userId, $date, $guests, $total, $fullNotes, $time !== '' ? $time : null);
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
                NotificationService::bookingAwaitingConfirmation(
                    (string) $customer['email'],
                    (string) ($listing['title'] ?? ''),
                    $date
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

    // ── Chat / messaging ────────────────────────────────────────────────────

    /** GET /api/messages — conversation list (inbox or archived). */
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

    /** GET /api/messages/thread?partner=&lang= — full conversation. */
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

    /** GET /api/messages/poll?partner=&since=&lang= — new messages + receipts. */
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

    /** POST /api/messages/send — body { partner_id, body, listing_id? }. */
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

    /** POST /api/messages/archive — body { partner_id }. */
    public function archiveConversation(): void
    {
        $this->setArchivedState(true);
    }

    /** POST /api/messages/unarchive — body { partner_id }. */
    public function unarchiveConversation(): void
    {
        $this->setArchivedState(false);
    }

    /** POST /api/messages/delete — body { partner_id }. */
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

    /** GET /api/guides — approved tour guides available to message. */
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
                    'name' => (string) $v['name'],
                    'type' => (string) $v['type'],
                    'price_per_day' => (float) $v['price_per_day'],
                    'specs' => $v['specs'],
                    'shop' => (string) $v['shop'],
                    'location' => (string) $v['location'],
                    'rating' => (float) $v['rating'],
                    'image' => api_img_src('uploads/rentals/' . $v['image'], 'rental-' . $v['id']),
                ],
                RentalRequest::vehicleCatalog()
            ),
        ]);
    }

    /**
     * POST /api/rentals — reserve a vehicle with FULL up-front payment.
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

        // ── Verification: a valid ID is required and held for the owner ──
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

        // ── Payment: the tourist pays the whole amount to reserve ──
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
     * GET /api/rentals/mine — the signed-in tourist's reservations.
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
     * POST /api/rentals/report — report a problem with a reserved unit so the
     * rental owner can review and refund. JSON body.
     */
    public function reportRental(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $rentalId = (int) ($body['rental_id'] ?? 0);
        $type = trim((string) ($body['problem_type'] ?? ''));
        $message = trim((string) ($body['description'] ?? ''));

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
        $fresh = RentalRequest::find($rentalId);
        $this->json(['request' => $fresh !== null ? $this->rentalPayload($fresh) : null]);
    }

    /**
     * Shape a rental row for the mobile app. The ID document is intentionally
     * NOT exposed here — it is held only for the rental owner.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function rentalPayload(array $r): array
    {
        $reportStatus = (string) ($r['report_status'] ?? 'none');
        $paymentStatus = (string) ($r['payment_status'] ?? 'unpaid');
        $status = (string) $r['status'];

        return [
            'id' => (int) $r['id'],
            'vehicle_name' => (string) $r['vehicle_name'],
            'vehicle_type' => (string) $r['vehicle_type'],
            'shop_name' => (string) $r['shop_name'],
            'location' => (string) ($r['location'] ?? ''),
            'pickup_date' => (string) $r['pickup_date'],
            'rental_days' => (int) $r['rental_days'],
            'total_amount' => (float) $r['total_amount'],
            'status' => $status,
            'status_label' => RentalRequest::statusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_method' => (string) ($r['payment_method'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'report_status' => $reportStatus,
            'report_type' => (string) ($r['report_type'] ?? ''),
            'report_label' => RentalRequest::reportTypeLabel($r['report_type'] ?? null),
            'report_message' => (string) ($r['report_message'] ?? ''),
            'owner_report_note' => (string) ($r['owner_report_note'] ?? ''),
            'can_report' => $paymentStatus === 'paid' && $reportStatus === 'none' && $status !== 'refunded',
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
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
        $payload = [
            'id' => (int) $listing['id'],
            'title' => (string) $listing['title'],
            'slug' => (string) $listing['slug'],
            'summary' => (string) ($listing['summary'] ?? ''),
            'area' => (string) ($listing['area'] ?? ''),
            'price' => Listing::effectivePrice($listing),
            'price_unit' => (string) ($listing['price_unit'] ?? 'person'),
            'currency' => 'PHP',
            'rating' => round((float) ($listing['avg_rating'] ?? 0), 1),
            'review_count' => (int) ($listing['review_count'] ?? 0),
            'category' => (string) ($listing['category_name'] ?? ''),
            'category_slug' => (string) ($listing['category_slug'] ?? ''),
            'image' => api_img_src((string) ($listing['cover_image'] ?? ''), 'listing' . $listing['id']),
            'featured' => (bool) ($listing['is_featured'] ?? false),
            'duration' => (string) ($listing['duration'] ?? ''),
            'owner_name' => (string) ($listing['owner_name'] ?? ''),
            'owner_id' => (int) ($listing['user_id'] ?? 0),
            'owner_role' => (string) ($listing['owner_role'] ?? ''),
            'favorited' => in_array((int) $listing['id'], $this->favoriteIdSet(), true),
            // Freshly published (within the last 7 days) — the app shows a
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

        // Already known to be in the reader's language → nothing to do.
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
            $recipients = Database::all("SELECT email, name FROM users WHERE role = 'admin'");
            $owner = User::find((int) ($listing['user_id'] ?? 0));
            if ($owner !== null && !empty($owner['email'])) {
                $recipients[] = ['email' => $owner['email'], 'name' => $owner['name']];
            }

            $subject = 'New booking awaiting your confirmation — ' . $title;
            $body = "{$customerName} booked \"{$title}\" and payment was received.\n\n"
                . "Date: {$date}\n"
                . "Guests: {$guests}\n"
                . 'Amount paid: PHP ' . number_format($total, 2) . "\n"
                . "Payment: {$methodLabel}\n\n"
                . "This booking is NOT confirmed yet. Open your dashboard → Bookings and confirm or decline it.\n"
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
                "SELECT email, name FROM users WHERE role IN ('rental_admin', 'admin')"
            );
            $subject = 'Confirm this rental — ' . $title;
            $body = "{$customerName} reserved \"{$title}\" and payment was received.\n\n"
                . "Pickup: {$pickupDate}\n"
                . 'Amount paid: PHP ' . number_format($total, 2) . "\n\n"
                . "This reservation is NOT confirmed yet. Open your dashboard → Rental requests and confirm or decline it.\n"
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

        $reviewed = $viewerId !== null && Review::userHasReviewed($listingId, $viewerId);
        // A tourist can review a finished tour once, and report a paid booking
        // that hasn't already been reported.
        $canReview = $status === 'completed' && !$reviewed;
        $canReport = $disputeStatus === '' && $paid && in_array($status, ['confirmed', 'completed'], true);

        return [
            'id' => (int) $booking['id'],
            'listing_id' => $listingId,
            'listing_title' => (string) ($booking['listing_title'] ?? ''),
            'listing_slug' => (string) ($booking['listing_slug'] ?? ''),
            'image' => api_img_src((string) ($booking['cover_image'] ?? ''), 'listing' . $booking['listing_id']),
            'booking_date' => (string) $booking['booking_date'],
            'booking_time' => (string) ($booking['booking_time'] ?? ''),
            'guests' => (int) $booking['guests'],
            'total_amount' => (float) $booking['total_amount'],
            'status' => $status,
            'status_label' => match ($status) {
                'pending' => 'Awaiting confirmation',
                'confirmed' => 'Approved',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded',
                'disputed' => 'In review',
                default => ucfirst($status),
            },
            'area' => (string) ($booking['area'] ?? ''),
            'reviewed' => $reviewed,
            'can_review' => $canReview,
            'can_report' => $canReport,
            'dispute_status' => $disputeStatus,
        ];
    }

    // ── Reviews & problem reports ─────────────────────────────────────────────

    /** POST /api/reviews — leave a rating/review for a completed experience. */
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

    /** GET /api/disputes?booking_id= — the tourist's existing report, if any. */
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
     * POST /api/disputes — report a problem with a guide/booking to the admin.
     * Sent as multipart/form-data so photo evidence can be attached.
     */
    public function submitDispute(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $type = trim((string) ($_POST['problem_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $amountRaw = trim((string) ($_POST['amount_requested'] ?? ''));

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
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private function disputePayload(array $d): array
    {
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
}

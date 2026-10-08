<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;

/**
 * Global helper functions available throughout the app and views.
 */

if (!function_exists('e')) {
    /**
     * Escape a value for safe HTML output.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Build an absolute-path URL respecting the app base path (subfolder support).
     */
    function url(string $path = '/'): string
    {
        $base = App::basePath();
        if ($base !== '') {
            $normalizedBase = '/' . trim($base, '/');
            $normalizedPath = '/' . ltrim($path, '/');
            if (str_starts_with($normalizedPath, $normalizedBase . '/') || $normalizedPath === $normalizedBase) {
                return $base . substr($normalizedPath, strlen($normalizedBase));
            }
            if (str_starts_with($path, $base . '/') || $path === $base) {
                return $path;
            }
        }
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * URL to a file in public/assets.
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    /**
     * Send a redirect and stop execution.
     */
    function redirect(string $path): void
    {
        $location = str_starts_with($path, 'http') ? $path : url($path);
        header('Location: ' . $location);
        exit;
    }
}

if (!function_exists('request_snapshot')) {
    /**
     * Snapshot the validation errors + old input from the session exactly once
     * per request, then clear them. This lets old() and errors() be called in
     * any order (controller or view) without one wiping the other.
     *
     * @return array{errors: array<string,string>, old: array<string,mixed>}
     */
    function request_snapshot(): array
    {
        static $snapshot = null;
        if ($snapshot === null) {
            $snapshot = [
                'errors' => $_SESSION['_errors'] ?? [],
                'old' => $_SESSION['_old'] ?? [],
            ];
            unset($_SESSION['_errors'], $_SESSION['_old']);
        }
        return $snapshot;
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previously submitted input after a validation redirect.
     */
    function old(string $key, string $default = ''): string
    {
        $value = request_snapshot()['old'][$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Set or get a one-time flash message.
     */
    function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('flash_keep_old')) {
    /**
     * Store submitted input for redisplay, plus an errors bag, then redirect back.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    function flash_keep_old(array $errors, array $input, string $back): void
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $input;
        redirect($back);
    }
}

if (!function_exists('errors')) {
    /**
     * Pull the validation errors bag (cleared after read).
     *
     * @return array<string, string>
     */
    function errors(): array
    {
        return request_snapshot()['errors'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render a hidden CSRF input for forms.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Auth::csrfToken()) . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Spoof a non-POST HTTP method (PUT/DELETE).
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth_user')) {
    /**
     * Currently authenticated user array, or null.
     *
     * @return array<string, mixed>|null
     */
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('abort')) {
    /**
     * Stop the request with an HTTP status and a friendly error page.
     */
    function abort(int $status, string $message = ''): void
    {
        http_response_code($status);
        \App\Core\View::render('errors/error', [
            'status' => $status,
            'message' => $message,
            'title' => "Error {$status}",
        ]);
        exit;
    }
}

if (!function_exists('money')) {
    /**
     * Format amount stored in PHP using the visitor's display currency (not checkout).
     */
    function money(float|int|string $amount): string
    {
        $php = (float) $amount;
        $code = strtoupper((string) ($_SESSION['_currency'] ?? 'USD'));
        $row = \App\Core\LocaleCatalog::currency($code);
        if ($row === null) {
            return '₱' . number_format($php, 2);
        }
        if ($code === 'PHP') {
            return $row['symbol'] . number_format($php, 2);
        }
        $rate = (float) $row['php_per_unit'];
        if ($rate <= 0) {
            return $row['symbol'] . number_format($php, 2);
        }
        $converted = $php / $rate;
        $decimals = in_array($code, ['JPY', 'KRW', 'VND', 'IDR', 'HUF'], true) ? 0 : 2;
        return $row['symbol'] . number_format($converted, $decimals);
    }
}

if (!function_exists('guide_badge')) {
    function guide_badge(int $guideId): string
    {
        return \App\Models\User::guideBadge($guideId);
    }
}

if (!function_exists('host_labels')) {
    /**
     * Tourist-facing copy for the listing owner (guide vs hotel vs rental).
     *
     * @return array{noun:string,Noun:string,message:string,sidebar:string,badge:string,flash:string}
     */
    function host_labels(?string $role): array
    {
        return match ((string) $role) {
            'hotel_admin' => [
                'noun' => 'hotel partner',
                'Noun' => 'Hotel partner',
                'message' => 'Message the hotel',
                'sidebar' => 'Your hotel partner',
                'badge' => 'Hotel Partner',
                'flash' => 'Message sent to the hotel partner.',
            ],
            'rental_admin' => [
                'noun' => 'rental partner',
                'Noun' => 'Rental partner',
                'message' => 'Message the rental partner',
                'sidebar' => 'Your rental partner',
                'badge' => 'Rental Partner',
                'flash' => 'Message sent to the rental partner.',
            ],
            default => [
                'noun' => 'guide',
                'Noun' => 'Guide',
                'message' => 'Message the guide',
                'sidebar' => 'Your guide',
                'badge' => 'Guide',
                'flash' => 'Message sent to the guide.',
            ],
        };
    }
}

if (!function_exists('__')) {
    function __(string $key, string $default = ''): string
    {
        return \App\Core\Lang::get($key, $default !== '' ? $default : $key);
    }
}

if (!function_exists('stars')) {
    /**
     * Render a star-rating string (filled/half/empty) for a 0-5 value.
     */
    function stars(float $rating): string
    {
        $rating = max(0.0, min(5.0, $rating));
        $full = (int) floor($rating);
        $half = ($rating - $full) >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;
        return str_repeat('★', $full) . str_repeat('⯨', $half) . str_repeat('☆', $empty);
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Build a full URL for mobile clients and external links.
     */
    function absolute_url(string $path = '/'): string
    {
        $configured = (string) (App::config('app')['url'] ?? '');
        if ($configured !== '') {
            return rtrim($configured, '/') . '/' . ltrim($path, '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return rtrim($scheme . '://' . $host . App::basePath(), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('api_img_src')) {
    /**
     * Absolute image URL for the mobile API.
     */
    function api_img_src(?string $path, string $fallbackSeed = 'guidemate'): string
    {
        if ($path === null || $path === '') {
            return "https://picsum.photos/seed/{$fallbackSeed}/1200/800";
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return absolute_url(ltrim($path, '/'));
    }
}

if (!function_exists('img_src')) {
    /**
     * Resolve an image path: external URLs pass through; otherwise treat as an
     * uploaded asset under public/. Falls back to a placeholder.
     */
    function img_src(?string $path, string $fallbackSeed = 'guidemate'): string
    {
        if ($path === null || $path === '') {
            return "https://picsum.photos/seed/{$fallbackSeed}/1200/800";
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return url(ltrim($path, '/'));
    }
}

if (!function_exists('active_when')) {
    /**
     * Echo "active" when the current path matches or an extra condition is true, for nav highlighting.
     */
    function active_when(string $path, bool $condition = false): string
    {
        if ($condition) {
            return 'active';
        }
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = App::basePath();
        if ($base !== '' && str_starts_with($current, $base)) {
            $current = substr($current, strlen($base)) ?: '/';
        }
        $currentPath = rtrim($current, '/');
        $targetPath = rtrim($path, '/');
        if ($currentPath === $targetPath) {
            return 'active';
        }
        if ($targetPath === '/profile' && (str_starts_with($currentPath, '/profile') || str_starts_with($currentPath, '/dashboard/verification'))) {
            return 'active';
        }
        if ($targetPath === '/admin/users' && str_starts_with($currentPath, '/admin/users')) {
            return 'active';
        }
        return '';
    }
}

if (!function_exists('admin_icon')) {
    /**
     * Inline, stroke-based SVG icons (Lucide-style) for the admin UI.
     * Uses currentColor so icons inherit the surrounding text color.
     */
    function admin_icon(string $name, int $size = 18): string
    {
        $paths = [
            'overview' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'listings' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h6M9 8h.01"/>',
            'guides' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
            'disputes' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/>',
            'rentals' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
            'feedback' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
            'messages' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/>',
            'analytics' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'security' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
            'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'bookings' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'verifyqr' => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/>',
            'transactions' => '<path d="M4 2h12l4 4v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'profile' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'map' => '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
            'saved' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
            'trips' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'revenue' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'trend' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'suspend' => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
            'unsuspend' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        ];
        $body = $paths[$name] ?? '';
        return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}

if (!function_exists('loc_date')) {
    /**
     * Locale-aware date formatting. Uses the PHP intl extension when available
     * so month names follow the visitor's chosen language; otherwise falls back
     * to a sensible English format.
     */
    function loc_date(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }
        // Use the intl extension when present for fully-localized dates.
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter(
                \App\Core\Lang::current(),
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE
            );
            $out = $fmt->format($ts);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }
        // Portable fallback: translate month names ourselves (intl not required).
        $lang = \App\Core\LocaleCatalog::langFileFor(\App\Core\Lang::current());
        $day = (int) date('j', $ts);
        $m = (int) date('n', $ts);
        $year = date('Y', $ts);
        $months = [
            'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'es' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
            'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            'de' => ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'],
            'pt' => ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
            'tl' => ['Ene', 'Peb', 'Mar', 'Abr', 'Mayo', 'Hun', 'Hul', 'Ago', 'Set', 'Okt', 'Nob', 'Dis'],
        ];
        if ($lang === 'ja' || $lang === 'zh') {
            return "{$year}年{$m}月{$day}日";
        }
        if ($lang === 'ko') {
            return "{$year}년 {$m}월 {$day}일";
        }
        $names = $months[$lang] ?? $months['en'];
        $mon = $names[$m - 1] ?? (string) $m;
        if ($lang === 'en') {
            return "{$mon} {$day}, {$year}";
        }
        if ($lang === 'de') {
            return "{$day}. {$mon} {$year}";
        }
        return "{$day} {$mon} {$year}";
    }
}

if (!function_exists('status_label')) {
    /**
     * Translate a booking/listing status code into the current language,
     * falling back to a human-readable English label.
     */
    function status_label(string $status): string
    {
        $status = strtolower(trim($status));
        return __('st_' . $status, ucfirst(str_replace('_', ' ', $status)));
    }
}

if (!function_exists('time_ago')) {
    /**
     * Human-friendly relative time, e.g. "3 days ago".
     */
    function time_ago(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
        foreach ($units as $secs => $label) {
            if ($diff >= $secs) {
                $count = (int) floor($diff / $secs);
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

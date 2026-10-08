<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Provider-agnostic text translation for the in-app chat.
 *
 * Two providers are supported and selected via config('translation'):
 *   - "google_free"   (default): keyless endpoint that auto-detects the source
 *                     language. Zero setup, ideal for development/demo.
 *   - "libretranslate": self-hosted LibreTranslate ({url}/translate, optional
 *                     api_key). Recommended for production so the data stays on
 *                     infrastructure you control.
 *
 * Every method fails soft: if the provider is unreachable or errors, we return
 * the original text so a chat message is never lost or blocked by translation.
 */
final class Translator
{
    /**
     * Map our in-app language codes to the codes each provider expects.
     * Anything not listed is passed through unchanged.
     */
    private const NORMALISE = [
        'zh' => 'zh-CN',
    ];

    private const TIMEOUT_SECONDS = 6;

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $config = App::config('translation');
        return is_array($config) ? $config : [];
    }

    public static function isEnabled(): bool
    {
        return (bool) (self::config()['enabled'] ?? true);
    }

    private static function provider(): string
    {
        return (string) (self::config()['provider'] ?? 'google_free');
    }

    /**
     * Translate $text into $target. When $source is null the provider detects it.
     *
     * @return array{body: string, source: ?string, translated: bool}
     */
    public static function translate(string $text, string $target, ?string $source = null): array
    {
        $text = trim($text);
        $target = self::normalise($target);
        $source = $source !== null ? self::normalise($source) : null;

        // Nothing worth translating, or already in the target language.
        if ($text === '' || mb_strlen($text) < 2 || !self::isEnabled()) {
            return ['body' => $text, 'source' => $source, 'translated' => false];
        }
        if ($source !== null && self::sameLanguage($source, $target)) {
            return ['body' => $text, 'source' => $source, 'translated' => false];
        }

        try {
            $result = self::provider() === 'libretranslate'
                ? self::viaLibreTranslate($text, $target, $source)
                : self::viaGoogleFree($text, $target, $source);
        } catch (\Throwable $e) {
            $result = null;
        }

        if ($result === null) {
            return ['body' => $text, 'source' => $source, 'translated' => false];
        }

        $detected = $result['source'] ?? $source;
        $translatedBody = trim($result['body']);
        $changed = $translatedBody !== '' && !self::sameText($translatedBody, $text);

        return [
            'body' => $changed ? $translatedBody : $text,
            'source' => $detected,
            'translated' => $changed && ($detected === null || !self::sameLanguage($detected, $target)),
        ];
    }

    /**
     * Keyless endpoint that returns both the translation and the detected
     * source language in a single request.
     *
     * @return array{body: string, source: ?string}|null
     */
    private static function viaGoogleFree(string $text, string $target, ?string $source): ?array
    {
        $query = http_build_query([
            'client' => 'gtx',
            'sl' => $source ?? 'auto',
            'tl' => $target,
            'dt' => 't',
            'q' => $text,
        ]);
        $raw = self::httpGet('https://translate.googleapis.com/translate_a/single?' . $query);
        if ($raw === null) {
            return null;
        }
        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            return null;
        }
        $body = '';
        foreach ($data[0] as $segment) {
            if (is_array($segment) && isset($segment[0])) {
                $body .= (string) $segment[0];
            }
        }
        $detected = isset($data[2]) && is_string($data[2]) ? $data[2] : $source;
        return ['body' => $body, 'source' => $detected];
    }

    /**
     * Self-hosted / hosted LibreTranslate. Uses source "auto" for detection.
     *
     * @return array{body: string, source: ?string}|null
     */
    private static function viaLibreTranslate(string $text, string $target, ?string $source): ?array
    {
        $config = self::config();
        $base = rtrim((string) ($config['url'] ?? 'http://localhost:5000'), '/');
        $payload = [
            'q' => $text,
            'source' => $source ?? 'auto',
            'target' => $target,
            'format' => 'text',
        ];
        if (!empty($config['api_key'])) {
            $payload['api_key'] = (string) $config['api_key'];
        }
        $raw = self::httpPostJson($base . '/translate', $payload);
        if ($raw === null) {
            return null;
        }
        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['translatedText'])) {
            return null;
        }
        $detected = $data['detectedLanguage']['language'] ?? $source;
        return [
            'body' => (string) $data['translatedText'],
            'source' => is_string($detected) ? $detected : $source,
        ];
    }

    private static function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'GuideMate/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($response === false || $status >= 400) ? null : (string) $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function httpPostJson(string $url, array $payload): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_USERAGENT => 'GuideMate/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($response === false || $status >= 400) ? null : (string) $response;
    }

    private static function normalise(string $code): string
    {
        $code = strtolower(trim($code));
        return self::NORMALISE[$code] ?? $code;
    }

    /** Compare language codes ignoring region (e.g. "zh-cn" == "zh"). */
    private static function sameLanguage(string $a, string $b): bool
    {
        $base = static fn (string $c): string => strtolower(explode('-', $c)[0]);
        return $base($a) === $base($b);
    }

    private static function sameText(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}

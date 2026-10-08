<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/** Optional SMS via Semaphore.ph — logs when API key is not set. */
final class SmsService
{
    public static function notifyIfConfigured(string $phoneOrEmail, string $message): void
    {
        $config = App::config('sms') ?? [];
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return;
        }

        if (!preg_match('/^\+?[0-9]{10,15}$/', $phoneOrEmail)) {
            return;
        }

        $payload = http_build_query([
            'apikey' => $apiKey,
            'number' => $phoneOrEmail,
            'message' => mb_substr($message, 0, 160),
            'sendername' => (string) ($config['sender'] ?? 'GuideMate'),
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
            ],
        ]);

        @file_get_contents('https://api.semaphore.co/api/v4/messages', false, $ctx);
    }
}

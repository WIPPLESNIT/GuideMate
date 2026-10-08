<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple mail sender — SMTP when configured, otherwise PHP mail() or log-only.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $config = App::config('mail') ?? [];
        $from = (string) ($config['from'] ?? 'noreply@guidemate.local');
        $fromName = (string) ($config['from_name'] ?? 'GuideMate');

        if (!self::isConfigured($config)) {
            if ((bool) (App::config('app')['debug'] ?? false)) {
                error_log("[GuideMate Mail] To: {$to} | Subject: {$subject}\n{$body}");
            }
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . self::formatAddress($fromName, $from),
        ];

        $host = trim((string) ($config['host'] ?? ''));
        if ($host !== '') {
            return self::sendSmtp($config, $to, $subject, $body, $from, $fromName);
        }

        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isConfigured(array $config): bool
    {
        return trim((string) ($config['from'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function sendSmtp(array $config, string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $host = (string) $config['host'];
        $port = (int) ($config['port'] ?? 587);
        $user = (string) ($config['username'] ?? '');
        $pass = (string) ($config['password'] ?? '');

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if ($socket === false) {
            error_log("GuideMate SMTP connect failed: {$errstr} ({$errno})");
            return false;
        }

        self::smtpExpect($socket, '220');
        fwrite($socket, "EHLO guidemate\r\n");
        self::smtpExpect($socket, '250');

        if ($user !== '') {
            fwrite($socket, "AUTH LOGIN\r\n");
            self::smtpExpect($socket, '334');
            fwrite($socket, base64_encode($user) . "\r\n");
            self::smtpExpect($socket, '334');
            fwrite($socket, base64_encode($pass) . "\r\n");
            self::smtpExpect($socket, '235');
        }

        fwrite($socket, 'MAIL FROM:<' . $from . ">\r\n");
        self::smtpExpect($socket, '250');
        fwrite($socket, 'RCPT TO:<' . $to . ">\r\n");
        self::smtpExpect($socket, '250');
        fwrite($socket, "DATA\r\n");
        self::smtpExpect($socket, '354');

        $message = "From: " . self::formatAddress($fromName, $from) . "\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$subject}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $body . "\r\n.\r\n";
        fwrite($socket, $message);
        self::smtpExpect($socket, '250');
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    /** @param resource $socket */
    private static function smtpExpect($socket, string $code): void
    {
        $response = fgets($socket, 512);
        if ($response === false || !str_starts_with($response, $code)) {
            throw new \RuntimeException('SMTP error: ' . (string) $response);
        }
    }

    private static function formatAddress(string $name, string $email): string
    {
        return $name . ' <' . $email . '>';
    }
}

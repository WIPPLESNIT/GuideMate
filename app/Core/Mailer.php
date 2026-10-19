<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Mail sender supporting SMTP (including Gmail with TLS/SSL), PHP mail(), or local file/error logging.
 */
final class Mailer
{
    /** @var array<string, mixed>|null */
    private static ?array $lastSent = null;

    /** @var bool Whether to force failure for testing */
    private static bool $simulateFailure = false;

    /**
     * Send an email to a recipient.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body Plain-text body
     * @param string|null $htmlBody Optional HTML version for multipart/alternative
     */
    public static function send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        if (self::$simulateFailure) {
            self::logMailFailure($to, $subject, 'Simulated email delivery failure');
            return false;
        }

        $config = App::config('mail') ?? [];
        $from = trim((string) ($config['from'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $fromName = (string) ($config['from_name'] ?? 'GuideMate');

        // Default the sender address to the username (e.g. Gmail address) if from is not explicitly set
        if ($from === '' && $username !== '') {
            $from = $username;
        }
        if ($from === '') {
            $from = 'noreply@guidemate.local';
        }

        // Track last sent email for testing / inspection
        self::$lastSent = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'html' => $htmlBody,
            'timestamp' => time(),
        ];

        // If SMTP is configured, attempt SMTP delivery
        $host = trim((string) ($config['host'] ?? ''));
        $user = trim((string) ($config['username'] ?? ''));
        if ($host !== '' && $user !== '') {
            return self::sendSmtp($config, $to, $subject, $body, $htmlBody, $from, $fromName);
        }

        // If native mail sender is configured (non-empty explicit from address)
        if (trim((string) ($config['from'] ?? '')) !== '') {
            return self::sendNativeMail($to, $subject, $body, $htmlBody, $from, $fromName);
        }

        // Local / development mock mode: log the email content safely
        self::logMailLocally($to, $subject, $body, $htmlBody);
        return true;
    }

    /**
     * Check if email delivery is configured (either SMTP credentials or custom sender address).
     *
     * @param array<string, mixed> $config
     */
    public static function isConfigured(array $config): bool
    {
        $host = trim((string) ($config['host'] ?? ''));
        $user = trim((string) ($config['username'] ?? ''));
        $from = trim((string) ($config['from'] ?? ''));

        if ($host !== '' && $user !== '') {
            return true;
        }

        return $from !== '';
    }

    /**
     * Retrieve the last recorded email payload (useful for unit/integration tests).
     *
     * @return array<string, mixed>|null
     */
    public static function lastSent(): ?array
    {
        return self::$lastSent;
    }

    /**
     * Clear the recorded last sent payload.
     */
    public static function clearLastSent(): void
    {
        self::$lastSent = null;
    }

    /**
     * Force email delivery to fail (for failure testing).
     */
    public static function setSimulateFailure(bool $simulate): void
    {
        self::$simulateFailure = $simulate;
    }

    /**
     * Send email via SMTP (Gmail, standard TLS 587, or SSL 465).
     *
     * @param array<string, mixed> $config
     */
    private static function sendSmtp(
        array $config,
        string $to,
        string $subject,
        string $body,
        ?string $htmlBody,
        string $from,
        string $fromName
    ): bool {
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 587);
        $user = (string) ($config['username'] ?? '');
        $pass = (string) ($config['password'] ?? '');
        $encryption = strtolower((string) ($config['encryption'] ?? ($port === 465 ? 'ssl' : 'tls')));
        $timeout = (int) ($config['timeout'] ?? 15);

        $socket = null;

        try {
            // Determine host scheme: for SSL (port 465), prepend ssl://
            $connectHost = $host;
            if ($encryption === 'ssl' && !str_starts_with($connectHost, 'ssl://')) {
                $connectHost = 'ssl://' . $connectHost;
            }

            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($connectHost, $port, $errno, $errstr, $timeout);
            if ($socket === false) {
                throw new \RuntimeException("Could not connect to SMTP host {$host}:{$port} - {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, $timeout);

            // 1. Initial greeting
            self::smtpReadReply($socket, '220');

            // 2. EHLO handshake
            $clientHost = gethostname() ?: 'localhost';
            fwrite($socket, "EHLO {$clientHost}\r\n");
            self::smtpReadReply($socket, '250');

            // 3. STARTTLS upgrade if TLS requested and not already an SSL wrapper
            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                self::smtpReadReply($socket, '220');

                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new \RuntimeException('Failed to establish TLS encryption with SMTP server.');
                }

                // Re-send EHLO after TLS negotiation
                fwrite($socket, "EHLO {$clientHost}\r\n");
                self::smtpReadReply($socket, '250');
            }

            // 4. Authentication (AUTH LOGIN)
            if ($user !== '') {
                fwrite($socket, "AUTH LOGIN\r\n");
                self::smtpReadReply($socket, '334');

                fwrite($socket, base64_encode($user) . "\r\n");
                self::smtpReadReply($socket, '334');

                fwrite($socket, base64_encode($pass) . "\r\n");
                self::smtpReadReply($socket, '235');
            }

            // 5. Envelope sender and recipient
            fwrite($socket, "MAIL FROM:<{$from}>\r\n");
            self::smtpReadReply($socket, '250');

            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            self::smtpReadReply($socket, '250');

            // 6. Message DATA
            fwrite($socket, "DATA\r\n");
            self::smtpReadReply($socket, '354');

            $message = self::buildMimeMessage($to, $subject, $body, $htmlBody, $from, $fromName, $host);
            fwrite($socket, $message);
            self::smtpReadReply($socket, '250');

            // 7. QUIT
            fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            $socket = null;

            return true;
        } catch (\Throwable $e) {
            self::logMailFailure($to, $subject, $e->getMessage());
            return false;
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    /**
     * Send email via PHP native mail() function.
     */
    private static function sendNativeMail(
        string $to,
        string $subject,
        string $body,
        ?string $htmlBody,
        string $from,
        string $fromName
    ): bool {
        try {
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'From: ' . self::formatAddress($fromName, $from);

            if ($htmlBody !== null) {
                $boundary = 'bnd_' . bin2hex(random_bytes(16));
                $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

                $content = "--{$boundary}\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $body . "\r\n\r\n"
                    . "--{$boundary}\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $htmlBody . "\r\n\r\n"
                    . "--{$boundary}--\r\n";
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $content = $body;
            }

            $success = @mail($to, $subject, $content, implode("\r\n", $headers));
            if (!$success) {
                self::logMailFailure($to, $subject, 'PHP mail() returned false.');
            }
            return $success;
        } catch (\Throwable $e) {
            self::logMailFailure($to, $subject, $e->getMessage());
            return false;
        }
    }

    /**
     * Read a multi-line SMTP reply and assert the expected response code.
     *
     * @param resource $socket
     * @throws \RuntimeException If the server response code does not match expected
     */
    private static function smtpReadReply($socket, string $expectedCode): string
    {
        $lines = [];
        while (!feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $lines[] = trim($line);

            // RFC 5321: If 4th char is space or end of line, this is the final line of multi-line reply
            if (strlen($line) >= 3 && (strlen($line) === 3 || $line[3] === ' ' || $line[3] === "\r" || $line[3] === "\n")) {
                break;
            }
        }

        $lastLine = end($lines) ?: '';
        if (!str_starts_with($lastLine, $expectedCode)) {
            throw new \RuntimeException(
                "SMTP response error (expected code {$expectedCode}): " . implode(' | ', $lines)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Build the raw MIME message string with dot-stuffing.
     */
    private static function buildMimeMessage(
        string $to,
        string $subject,
        string $body,
        ?string $htmlBody,
        string $from,
        string $fromName,
        string $host
    ): string {
        $msgId = bin2hex(random_bytes(16)) . '@' . ($host ?: 'guidemate.local');
        $date = date('r');

        $headers = [
            'Date: ' . $date,
            'From: ' . self::formatAddress($fromName, $from),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . $msgId . '>',
            'MIME-Version: 1.0',
        ];

        // Canonical RFC 5321 CRLF line ending normalization
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);

        if ($htmlBody !== null) {
            $htmlBody = str_replace(["\r\n", "\r"], "\n", $htmlBody);
            $htmlBody = str_replace("\n", "\r\n", $htmlBody);

            $boundary = 'bnd_' . bin2hex(random_bytes(16));
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $payload = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $body . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $htmlBody . "\r\n\r\n"
                . "--{$boundary}--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $payload = $body;
        }

        // Dot-stuffing RFC 5321: prepend an extra dot to any line starting with a dot
        $lines = explode("\r\n", $payload);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);
        $payload = implode("\r\n", $lines);

        return implode("\r\n", $headers) . "\r\n\r\n" . $payload . "\r\n.\r\n";
    }

    /**
     * Format email display address.
     */
    private static function formatAddress(string $name, string $email): string
    {
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /**
     * Safely encode email header values if they contain non-ASCII characters.
     */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /**
     * Log email locally for dev/mock mode.
     */
    private static function logMailLocally(string $to, string $subject, string $body, ?string $htmlBody = null): void
    {
        $logDir = BASE_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . 'mail.log';
        $entry = sprintf(
            "[%s] LOCAL MAIL\nTo: %s\nSubject: %s\nBody:\n%s\n%s\n----------------------------------------\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body,
            $htmlBody !== null ? "\n[HTML Version Attached]" : ''
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if ((bool) (App::config('app')['debug'] ?? false)) {
            error_log("[GuideMate Mail Log] To: {$to} | Subject: {$subject}");
        }
    }

    /**
     * Log an email delivery failure without exposing sensitive credentials or passwords.
     */
    private static function logMailFailure(string $to, string $subject, string $error): void
    {
        // Sanitize error message to ensure no passwords or secrets are leaked
        $sanitizedError = preg_replace('/pass(word)?\s*[:=]\s*\S+/i', 'password=***', $error);

        $logDir = BASE_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . 'mail_error.log';
        $entry = sprintf(
            "[%s] MAIL FAILURE\nTo: %s\nSubject: %s\nError: %s\n----------------------------------------\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $sanitizedError
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        error_log("[GuideMate Mail Failure] To: {$to} | Error: {$sanitizedError}");
    }
}

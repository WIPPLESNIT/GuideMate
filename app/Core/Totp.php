<?php

declare(strict_types=1);

namespace App\Core;

/** Minimal TOTP (RFC 6238) for admin two-factor authentication. */
final class Totp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= self::BASE32[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function code(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice ??= (int) floor(time() / 30);
        $secretKey = self::decodeBase32($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function decodeBase32(string $secret): string
    {
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos(self::BASE32, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }
}

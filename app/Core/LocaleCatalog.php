<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Region/language and currency options for international tourists (display-only rates vs PHP).
 */
final class LocaleCatalog
{
    /**
     * @return array<int, array{code: string, region: string, language: string, short: string}>
     */
    public static function locales(): array
    {
        return [
            ['code' => 'en', 'region' => 'United States', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-gb', 'region' => 'United Kingdom', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-au', 'region' => 'Australia', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-ca', 'region' => 'Canada', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-sg', 'region' => 'Singapore', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-in', 'region' => 'India', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-ae', 'region' => 'United Arab Emirates', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'en-nz', 'region' => 'New Zealand', 'language' => 'English', 'short' => 'EN'],
            ['code' => 'fr-ca', 'region' => 'Canada', 'language' => 'Français', 'short' => 'FR'],
            ['code' => 'fr', 'region' => 'France', 'language' => 'Français', 'short' => 'FR'],
            ['code' => 'de', 'region' => 'Germany', 'language' => 'Deutsch', 'short' => 'DE'],
            ['code' => 'es', 'region' => 'Spain', 'language' => 'Español', 'short' => 'ES'],
            ['code' => 'es-mx', 'region' => 'Mexico', 'language' => 'Español', 'short' => 'ES'],
            ['code' => 'it', 'region' => 'Italy', 'language' => 'Italiano', 'short' => 'IT'],
            ['code' => 'nl', 'region' => 'Netherlands', 'language' => 'Nederlands', 'short' => 'NL'],
            ['code' => 'pt-br', 'region' => 'Brazil', 'language' => 'Português', 'short' => 'PT'],
            ['code' => 'ja', 'region' => 'Japan', 'language' => '日本語', 'short' => 'JA'],
            ['code' => 'ko', 'region' => 'South Korea', 'language' => '한국어', 'short' => 'KO'],
            ['code' => 'zh', 'region' => 'China', 'language' => '中文 (简体)', 'short' => 'ZH'],
            ['code' => 'zh-tw', 'region' => 'Taiwan', 'language' => '中文 (繁體)', 'short' => 'ZH'],
            ['code' => 'zh-hk', 'region' => 'Hong Kong', 'language' => '中文 (繁體)', 'short' => 'ZH'],
            ['code' => 'th', 'region' => 'Thailand', 'language' => 'ไทย', 'short' => 'TH'],
            ['code' => 'vi', 'region' => 'Vietnam', 'language' => 'Tiếng Việt', 'short' => 'VI'],
            ['code' => 'id', 'region' => 'Indonesia', 'language' => 'Bahasa Indonesia', 'short' => 'ID'],
            ['code' => 'ms', 'region' => 'Malaysia', 'language' => 'Bahasa Melayu', 'short' => 'MS'],
            ['code' => 'ru', 'region' => 'Russia', 'language' => 'Русский', 'short' => 'RU'],
            ['code' => 'ar', 'region' => 'Saudi Arabia', 'language' => 'العربية', 'short' => 'AR'],
            ['code' => 'hi', 'region' => 'India', 'language' => 'हिन्दी', 'short' => 'HI'],
            ['code' => 'tl', 'region' => 'Philippines', 'language' => 'Tagalog', 'short' => 'TL'],
            ['code' => 'en-ph', 'region' => 'Philippines', 'language' => 'English', 'short' => 'EN'],
        ];
    }

    /**
     * @return array<int, array{code: string, region: string, label: string, symbol: string, php_per_unit: float}>
     */
    public static function currencies(): array
    {
        return [
            ['code' => 'USD', 'region' => 'United States', 'label' => 'US Dollar', 'symbol' => '$', 'php_per_unit' => 56.0],
            ['code' => 'EUR', 'region' => 'Eurozone', 'label' => 'Euro', 'symbol' => '€', 'php_per_unit' => 61.0],
            ['code' => 'GBP', 'region' => 'United Kingdom', 'label' => 'British Pound', 'symbol' => '£', 'php_per_unit' => 71.0],
            ['code' => 'AUD', 'region' => 'Australia', 'label' => 'Australian Dollar', 'symbol' => 'A$', 'php_per_unit' => 37.0],
            ['code' => 'CAD', 'region' => 'Canada', 'label' => 'Canadian Dollar', 'symbol' => 'C$', 'php_per_unit' => 41.0],
            ['code' => 'NZD', 'region' => 'New Zealand', 'label' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'php_per_unit' => 34.0],
            ['code' => 'JPY', 'region' => 'Japan', 'label' => 'Japanese Yen', 'symbol' => '¥', 'php_per_unit' => 0.37],
            ['code' => 'KRW', 'region' => 'South Korea', 'label' => 'Korean Won', 'symbol' => '₩', 'php_per_unit' => 0.042],
            ['code' => 'CNY', 'region' => 'China', 'label' => 'Chinese Yuan', 'symbol' => '¥', 'php_per_unit' => 7.8],
            ['code' => 'TWD', 'region' => 'Taiwan', 'label' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'php_per_unit' => 1.75],
            ['code' => 'HKD', 'region' => 'Hong Kong', 'label' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'php_per_unit' => 7.2],
            ['code' => 'SGD', 'region' => 'Singapore', 'label' => 'Singapore Dollar', 'symbol' => 'S$', 'php_per_unit' => 42.0],
            ['code' => 'MYR', 'region' => 'Malaysia', 'label' => 'Malaysian Ringgit', 'symbol' => 'RM', 'php_per_unit' => 12.5],
            ['code' => 'THB', 'region' => 'Thailand', 'label' => 'Thai Baht', 'symbol' => '฿', 'php_per_unit' => 1.6],
            ['code' => 'IDR', 'region' => 'Indonesia', 'label' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'php_per_unit' => 0.0035],
            ['code' => 'VND', 'region' => 'Vietnam', 'label' => 'Vietnamese Dong', 'symbol' => '₫', 'php_per_unit' => 0.0022],
            ['code' => 'INR', 'region' => 'India', 'label' => 'Indian Rupee', 'symbol' => '₹', 'php_per_unit' => 0.67],
            ['code' => 'AED', 'region' => 'United Arab Emirates', 'label' => 'UAE Dirham', 'symbol' => 'د.إ', 'php_per_unit' => 15.2],
            ['code' => 'SAR', 'region' => 'Saudi Arabia', 'label' => 'Saudi Riyal', 'symbol' => '﷼', 'php_per_unit' => 14.9],
            ['code' => 'QAR', 'region' => 'Qatar', 'label' => 'Qatari Riyal', 'symbol' => 'QR', 'php_per_unit' => 15.4],
            ['code' => 'CHF', 'region' => 'Switzerland', 'label' => 'Swiss Franc', 'symbol' => 'CHF', 'php_per_unit' => 64.0],
            ['code' => 'SEK', 'region' => 'Sweden', 'label' => 'Swedish Krona', 'symbol' => 'kr', 'php_per_unit' => 5.3],
            ['code' => 'NOK', 'region' => 'Norway', 'label' => 'Norwegian Krone', 'symbol' => 'kr', 'php_per_unit' => 5.2],
            ['code' => 'DKK', 'region' => 'Denmark', 'label' => 'Danish Krone', 'symbol' => 'kr', 'php_per_unit' => 8.2],
            ['code' => 'PLN', 'region' => 'Poland', 'label' => 'Polish Złoty', 'symbol' => 'zł', 'php_per_unit' => 14.0],
            ['code' => 'CZK', 'region' => 'Czech Republic', 'label' => 'Czech Koruna', 'symbol' => 'Kč', 'php_per_unit' => 2.4],
            ['code' => 'HUF', 'region' => 'Hungary', 'label' => 'Hungarian Forint', 'symbol' => 'Ft', 'php_per_unit' => 0.15],
            ['code' => 'ILS', 'region' => 'Israel', 'label' => 'Israeli Shekel', 'symbol' => '₪', 'php_per_unit' => 15.5],
            ['code' => 'TRY', 'region' => 'Turkey', 'label' => 'Turkish Lira', 'symbol' => '₺', 'php_per_unit' => 1.7],
            ['code' => 'BRL', 'region' => 'Brazil', 'label' => 'Brazilian Real', 'symbol' => 'R$', 'php_per_unit' => 10.5],
            ['code' => 'MXN', 'region' => 'Mexico', 'label' => 'Mexican Peso', 'symbol' => 'MX$', 'php_per_unit' => 3.2],
            ['code' => 'RUB', 'region' => 'Russia', 'label' => 'Russian Ruble', 'symbol' => '₽', 'php_per_unit' => 0.62],
            ['code' => 'ZAR', 'region' => 'South Africa', 'label' => 'South African Rand', 'symbol' => 'R', 'php_per_unit' => 3.0],
            ['code' => 'PHP', 'region' => 'Philippines', 'label' => 'Philippine Peso', 'symbol' => '₱', 'php_per_unit' => 1.0],
        ];
    }

    public static function isValidLocale(string $code): bool
    {
        $code = strtolower($code);
        foreach (self::locales() as $row) {
            if ($row['code'] === $code) {
                return true;
            }
        }
        return false;
    }

    public static function isValidCurrency(string $code): bool
    {
        $code = strtoupper($code);
        foreach (self::currencies() as $row) {
            if ($row['code'] === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{code: string, region: string, language: string, short: string}|null
     */
    public static function locale(string $code): ?array
    {
        $code = strtolower($code);
        foreach (self::locales() as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @return array{code: string, region: string, label: string, symbol: string, php_per_unit: float}|null
     */
    public static function currency(string $code): ?array
    {
        $code = strtoupper($code);
        foreach (self::currencies() as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }
        return null;
    }

    /** Language file slug (en-gb → en). */
    public static function langFileFor(string $code): string
    {
        $code = strtolower($code);
        if ($code === 'tl') {
            return 'tl';
        }
        if (str_starts_with($code, 'en')) {
            return 'en';
        }
        if (str_starts_with($code, 'fr')) {
            return 'fr';
        }
        if (str_starts_with($code, 'es')) {
            return 'es';
        }
        if (str_starts_with($code, 'pt')) {
            return 'pt';
        }
        if (str_starts_with($code, 'zh')) {
            return 'zh';
        }
        $base = explode('-', $code)[0];
        $supported = ['de', 'it', 'nl', 'ja', 'ko', 'th', 'vi', 'id', 'ms', 'ru', 'ar', 'hi'];
        return in_array($base, $supported, true) ? $base : 'en';
    }

    public static function suggestedLocale(): array
    {
        return self::locale('en') ?? self::locales()[0];
    }

    public static function suggestedCurrency(): array
    {
        return self::currency('USD') ?? self::currencies()[0];
    }

    public static function headerLabel(string $localeCode, string $currencyCode): string
    {
        $loc = self::locale($localeCode) ?? self::suggestedLocale();
        $cur = self::currency($currencyCode) ?? self::currency('USD') ?? self::currencies()[0];
        return $loc['short'] . ' · ' . $cur['symbol'];
    }
}

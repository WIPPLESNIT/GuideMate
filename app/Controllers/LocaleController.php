<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Core\LocaleCatalog;

final class LocaleController extends Controller
{
    public function setLocale(string $locale): void
    {
        Lang::setLocale(strtolower($locale));
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    public function setCurrency(string $currency): void
    {
        $code = strtoupper($currency);
        $_SESSION['_currency'] = LocaleCatalog::isValidCurrency($code) ? $code : 'USD';
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

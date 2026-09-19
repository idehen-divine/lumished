<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class MoneyHelper extends Helper
{
    /**
     * Currency symbols.
     */
    protected static array $symbols = [
        'NGN' => '₦',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    /**
     * Converts a major currency amount to its minor currency unit.
     */
    public static function toMinor(int|float|null $amount = null)
    {
        if (is_null($amount) || ! is_numeric($amount) || $amount < 0) {
            return null;
        }

        $amount = (int) round($amount * 100);

        return $amount;
    }

    /**
     * Converts a minor currency unit amount to its major currency amount.
     */
    public static function fromMinor(int|float|string|null $amount = null, $format = false)
    {
        if (is_null($amount) || ! is_numeric($amount) || $amount < 0) {
            return null;
        }

        $amount = round($amount / 100, 2);
        if ($format) {
            return self::addCurrency($amount);
        }

        return $amount;
    }

    /**
     * Adds currency symbol to amount.
     */
    public static function addCurrency(int|float|string $amount, string $currency = 'NGN')
    {
        $symbol = self::$symbols[strtoupper($currency)];

        return $symbol.number_format($amount, 2);

    }
}

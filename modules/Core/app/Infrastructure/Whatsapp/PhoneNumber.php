<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

/**
 * Phone numbers as schools type them (`0812-3456-7890`, `+62 812…`) turned
 * into what WhatsApp wants: digits in international format, no `+`.
 */
final class PhoneNumber
{
    /**
     * `628…` digits, or null when the input cannot be a mobile number. A
     * number written without a country code is taken as Indonesian; one
     * written with `+` keeps its own country code.
     */
    public static function normalize(?string $input): ?string
    {
        $input = trim((string) $input);
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! str_starts_with($input, '+')) {
            $digits = match (true) {
                str_starts_with($digits, '62') => $digits,
                str_starts_with($digits, '0') => '62'.substr($digits, 1),
                str_starts_with($digits, '8') => '62'.$digits,
                default => '',
            };
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * The number with its middle hidden, for lists: `62812••••7890`.
     */
    public static function mask(?string $digits): string
    {
        if ($digits === null || strlen($digits) < 8) {
            return '—';
        }

        return substr($digits, 0, 5).str_repeat('•', strlen($digits) - 9).substr($digits, -4);
    }
}

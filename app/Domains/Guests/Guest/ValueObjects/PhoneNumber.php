<?php

namespace App\Domains\Guests\Guest\ValueObjects;

/** Phone numbers are stored in E.164. Ugandan local formats become +256. */
final class PhoneNumber
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        $international = str_starts_with(trim($raw), '+') || str_starts_with(trim($raw), '00');

        return match (true) {
            str_starts_with(trim($raw), '00') => '+'.substr($digits, 2),
            $international => '+'.$digits,
            str_starts_with($digits, '256') && strlen($digits) === 12 => '+'.$digits,
            str_starts_with($digits, '0') && strlen($digits) === 10 => '+256'.substr($digits, 1),
            strlen($digits) === 9 => '+256'.$digits,
            default => '+'.$digits,
        };
    }

    public static function isValid(?string $normalized): bool
    {
        return $normalized === null || (bool) preg_match('/^\+\d{9,15}$/', $normalized);
    }
}

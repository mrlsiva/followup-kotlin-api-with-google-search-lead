<?php

namespace App\Support;

/** Phone number normalisation used for duplicate detection. */
final class Phone
{
    /**
     * Comparable digits of an Indian phone number: "+91 98765 43210", "098765 43210" and
     * "9876543210" all give "9876543210". Returns null when there are fewer than 6 digits.
     */
    public static function digits(?string $phone): ?string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if (strlen($d) > 10 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        }
        $d = ltrim($d, '0');

        return strlen($d) >= 6 ? substr($d, -15) : null;
    }
}

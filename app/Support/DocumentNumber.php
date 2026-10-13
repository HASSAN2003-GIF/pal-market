<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Human-readable, collision-resistant document numbers, e.g.
 * REQ-261013-K7Q2XM. The previous scheme (prefix + timestamp to the second +
 * supplier id) produced duplicates when one supplier quoted twice in the same
 * second, which surfaced as a 500 error from the unique index.
 */
class DocumentNumber
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function make(string $prefix): string
    {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return Str::upper($prefix).'-'.now()->format('ymd').'-'.$suffix;
    }
}

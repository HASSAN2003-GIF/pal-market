<?php

namespace App\Support;

/**
 * Tanzania's administrative regions, spelled as the rest of the app stores
 * them (request delivery regions, supplier locations). Mirrors
 * resources/js/lib/regions.js.
 */
class Regions
{
    public const ALL = [
        'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Katavi', 'Kigoma', 'Kilimanjaro',
        'Lindi', 'Manyara', 'Mara', 'Mbeya', 'Morogoro', 'Mtwara', 'Mwanza', 'Njombe', 'Pemba North',
        'Pemba South', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga', 'Simiyu', 'Singida', 'Songwe', 'Tabora',
        'Tanga', 'Unguja North', 'Unguja South', 'Mjini Magharibi',
    ];

    /** The canonical spelling of a region name, or null if it is not one. */
    public static function normalise(?string $region): ?string
    {
        if ($region === null) {
            return null;
        }

        $needle = mb_strtolower(trim($region));

        foreach (self::ALL as $name) {
            if (mb_strtolower($name) === $needle) {
                return $name;
            }
        }

        return null;
    }
}

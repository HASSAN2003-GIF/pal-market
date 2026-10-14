<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalises product names and search queries so that "SIMBA cement 50KG",
 * "simba-cement 50 kg" and "Simba Cement (50kgs)" compare equal.
 */
class SearchText
{
    /** Unit spellings folded to one form. */
    private const UNITS = [
        'kgs' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg', 'kilogramu' => 'kg',
        'grams' => 'g', 'gram' => 'g', 'gramu' => 'g',
        'mtr' => 'm', 'mtrs' => 'm', 'meter' => 'm', 'meters' => 'm', 'metre' => 'm', 'metres' => 'm', 'mita' => 'm',
        'millimeter' => 'mm', 'millimeters' => 'mm', 'millimetre' => 'mm', 'millimetres' => 'mm',
        'ltr' => 'l', 'ltrs' => 'l', 'litre' => 'l', 'litres' => 'l', 'liter' => 'l', 'liters' => 'l', 'lita' => 'l',
        'pcs' => 'pc', 'piece' => 'pc', 'pieces' => 'pc', 'kipande' => 'pc', 'vipande' => 'pc',
        'ft' => 'feet', 'foot' => 'feet', 'futi' => 'feet',
        'inches' => 'inch',
        'geji' => 'gauge', 'gage' => 'gauge',
        'mfuko' => 'bag', 'mifuko' => 'bag', 'bags' => 'bag',
    ];

    /** Words that carry no meaning in a product search (English and Swahili). */
    private const STOPWORDS = [
        'the', 'of', 'for', 'and', 'with', 'a', 'an', 'to', 'in', 'on',
        'na', 'ya', 'wa', 'za', 'la', 'kwa', 'cha', 'vya',
    ];

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = Str::ascii(mb_strtolower($text));
        // "50kg" -> "50 kg", "gauge28" -> "gauge 28"
        $text = preg_replace('/(\d)([a-z])/', '$1 $2', $text);
        $text = preg_replace('/([a-z])(\d)/', '$1 $2', $text);
        $text = preg_replace('/[^a-z0-9.]+/', ' ', $text);
        // Keep decimal points ("42.5"), drop every other dot.
        $text = preg_replace('/(?<!\d)\.|\.(?!\d)/', ' ', $text);

        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_map(fn (string $w) => self::UNITS[$w] ?? $w, $words));
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $normalized): array
    {
        $words = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => ! in_array($w, self::STOPWORDS, true),
        )));
    }

    /**
     * Very small English plural folding: "sheets" -> "sheet", "pipes" -> "pipe".
     */
    public static function singular(string $word): string
    {
        if (strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss') && ! is_numeric($word)) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    public static function containsWord(string $haystack, string $word): bool
    {
        return str_contains(" {$haystack} ", " {$word} ");
    }

    public static function containsPrefix(string $haystack, string $prefix): bool
    {
        return str_contains(" {$haystack}", " {$prefix}");
    }
}

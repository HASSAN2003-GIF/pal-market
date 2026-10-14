<?php

namespace App\Services;

use App\Models\Product;
use App\Support\SearchText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Product matching for the marketplace search box and the supplier product
 * picker. Works on an in-memory index of the active catalog (names, Swahili
 * names, aliases, category, brand, start of description), cached until the
 * catalog changes.
 *
 * Results come back in one of four modes so the interface can be honest about
 * what it is showing:
 *  - exact:   every word matched (directly, by prefix, plural or synonym)
 *  - close:   every word matched after a spelling correction ("cemnt")
 *  - related: only some words matched, shown as related products
 *  - none:    nothing matched
 *
 * Sized for a curated catalog of up to a few thousand products. A larger
 * catalog should move to a search engine (see docs/DECISIONS.md).
 */
class ProductSearch
{
    private const CACHE_KEY = 'catalog.search-index.v1';

    private ?array $groups = null;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{mode: string, ids: list<int>, scores: array<int, float>, suggestion: ?string}
     */
    public function search(string $query, int $limit = 500): array
    {
        $normalizedQuery = SearchText::normalize($query);
        $concepts = $this->concepts($normalizedQuery);

        if ($concepts === []) {
            return ['mode' => 'none', 'ids' => [], 'scores' => [], 'suggestion' => null];
        }

        $exact = $close = $related = [];
        $corrections = [];

        foreach ($this->index() as $entry) {
            $matched = 0;
            $score = 0.0;
            $fuzzy = false;
            $entryCorrections = [];

            foreach ($concepts as $concept) {
                [$conceptScore, $kind, $correction] = $this->matchConcept($concept, $entry);

                if ($conceptScore <= 0) {
                    continue;
                }

                $matched++;
                $score += $conceptScore;

                if ($kind === 'fuzzy') {
                    $fuzzy = true;
                    $entryCorrections[$concept['text']] = $correction;
                }
            }

            if ($matched === 0) {
                continue;
            }

            if ($normalizedQuery !== '' && $entry['name'] === $normalizedQuery) {
                $score += 5;
            } elseif ($normalizedQuery !== '' && str_starts_with($entry['name'], $normalizedQuery)) {
                $score += 3;
            }

            if ($matched === count($concepts) && ! $fuzzy) {
                $exact[$entry['id']] = $score;
            } elseif ($matched === count($concepts)) {
                $close[$entry['id']] = $score;
                $corrections += $entryCorrections;
            } else {
                // Rank by how much of the query matched first.
                $related[$entry['id']] = $matched * 10 + $score;
            }
        }

        [$mode, $scores] = match (true) {
            $exact !== [] => ['exact', $exact],
            $close !== [] => ['close', $close],
            $related !== [] => ['related', $related],
            default => ['none', []],
        };

        arsort($scores);
        $scores = array_slice($scores, 0, $limit, true);

        return [
            'mode' => $mode,
            'ids' => array_keys($scores),
            'scores' => $scores,
            'suggestion' => $mode === 'close' ? $this->applyCorrections($normalizedQuery, $corrections) : null,
        ];
    }

    /**
     * Short list for type-ahead boxes, with the alias that matched when the
     * product was found under another name ("saruji" -> Simba Cement).
     *
     * @return array{mode: string, items: list<array{id: int, matched: ?string}>}
     */
    public function suggest(string $query, int $limit = 8): array
    {
        $result = $this->search($query, $limit);
        $index = collect($this->index())->keyBy('id');
        $concepts = $this->concepts(SearchText::normalize($query));

        $items = collect($result['ids'])->map(function (int $id) use ($index, $concepts) {
            $entry = $index[$id];
            $matched = null;

            foreach ($entry['labels'] as $i => $label) {
                if ($i === 0) {
                    continue; // the product name itself
                }
                $normalizedLabel = SearchText::normalize($label);
                foreach ($concepts as $concept) {
                    foreach ($concept['alternatives'] as $alternative) {
                        if (SearchText::containsPrefix($normalizedLabel, $alternative)) {
                            $matched = $label;
                            break 3;
                        }
                    }
                }
            }

            return ['id' => $id, 'matched' => $matched];
        })->all();

        return ['mode' => $result['mode'], 'items' => $items];
    }

    /**
     * Turns a normalised query into concepts: a multi-word synonym phrase or a
     * single word, each with the alternatives that satisfy it.
     *
     * @return list<array{text: string, alternatives: list<string>, numeric: bool, corrected: ?string}>
     */
    public function concepts(string $normalized): array
    {
        $concepts = [];

        foreach ($this->groups() as $group) {
            foreach ($group as $term) {
                if (str_contains($term, ' ') && SearchText::containsWord($normalized, $term)) {
                    $concepts[] = ['text' => $term, 'alternatives' => $group, 'numeric' => false, 'corrected' => null];
                    $normalized = trim(str_replace(" {$term} ", ' ', " {$normalized} "));
                    break;
                }
            }
        }

        foreach (SearchText::tokens($normalized) as $token) {
            $alternatives = array_values(array_unique([$token, SearchText::singular($token)]));
            $corrected = null;

            foreach ($this->groups() as $group) {
                if (array_intersect($alternatives, $group)) {
                    $alternatives = array_merge($alternatives, $group);
                }
            }

            // A misspelt vocabulary word ("sarujy") still reaches its group.
            if (count($alternatives) <= 2 && strlen($token) >= 4 && ! is_numeric($token)) {
                foreach ($this->groups() as $group) {
                    foreach ($group as $term) {
                        if (! str_contains($term, ' ') && $this->isTypo($token, $term)) {
                            $alternatives = array_merge($alternatives, $group);
                            $corrected = $term;
                            break 2;
                        }
                    }
                }
            }

            $concepts[] = [
                'text' => $token,
                'alternatives' => array_values(array_unique($alternatives)),
                'numeric' => is_numeric($token),
                'corrected' => $corrected,
            ];
        }

        return $concepts;
    }

    /**
     * @return array{0: float, 1: ?string, 2: ?string}
     */
    private function matchConcept(array $concept, array $entry): array
    {
        $best = 0.0;
        $bestIsOriginal = false;

        foreach ($concept['alternatives'] as $alternative) {
            $original = $alternative === $concept['text'] || $alternative === SearchText::singular($concept['text']);
            $weight = $original ? 1.0 : 0.9;
            $canPrefix = ! $concept['numeric'] && strlen($alternative) >= 2;

            $score = match (true) {
                SearchText::containsWord($entry['primary'], $alternative) => 3.0,
                $canPrefix && SearchText::containsPrefix($entry['primary'], $alternative) => 2.2,
                SearchText::containsWord($entry['secondary'], $alternative) => 1.2,
                $canPrefix && strlen($alternative) >= 3 && SearchText::containsPrefix($entry['secondary'], $alternative) => 0.9,
                default => 0.0,
            };

            if ($score * $weight > $best) {
                $best = $score * $weight;
                $bestIsOriginal = $original;
            }
        }

        if ($best > 0) {
            return $concept['corrected'] && ! $bestIsOriginal
                ? [$best * 0.8, 'fuzzy', $concept['corrected']]
                : [$best, 'exact', null];
        }

        if ($concept['numeric'] || strlen($concept['text']) < 4) {
            return [0.0, null, null];
        }

        foreach ($entry['words'] as $word) {
            if ($this->isTypo($concept['text'], $word)) {
                return [1.0, 'fuzzy', $word];
            }
        }

        return [0.0, null, null];
    }

    private function isTypo(string $typed, string $word): bool
    {
        if (is_numeric($word) || abs(strlen($typed) - strlen($word)) > 2 || strlen($word) < 4) {
            return false;
        }

        $allowed = strlen($typed) >= 7 ? 2 : 1;

        return levenshtein($typed, $word) <= $allowed;
    }

    private function applyCorrections(string $query, array $corrections): ?string
    {
        if ($corrections === []) {
            return null;
        }

        $words = explode(' ', $query);

        return implode(' ', array_map(fn ($w) => $corrections[$w] ?? $w, $words));
    }

    /**
     * @return list<list<string>>
     */
    private function groups(): array
    {
        return $this->groups ??= array_map(
            fn (array $group) => array_values(array_unique(array_map([SearchText::class, 'normalize'], $group))),
            config('search.synonyms', []),
        );
    }

    /**
     * @return list<array{id: int, name: string, primary: string, secondary: string, words: list<string>, labels: list<string>}>
     */
    public function index(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Product::query()
                ->where('is_active', true)
                ->with(['category:id,name', 'brand:id,name', 'aliases:id,product_id,alias'])
                ->get(['id', 'name', 'name_sw', 'description', 'category_id', 'brand_id'])
                ->map(function (Product $product) {
                    $labels = array_values(array_filter([
                        $product->name,
                        $product->name_sw,
                        ...$product->aliases->pluck('alias')->all(),
                    ]));
                    $primary = SearchText::normalize(implode(' ', $labels));

                    return [
                        'id' => $product->id,
                        'name' => SearchText::normalize($product->name),
                        'primary' => $primary,
                        'secondary' => SearchText::normalize(implode(' ', [
                            $product->category?->name,
                            $product->brand?->name,
                            Str::limit((string) $product->description, 300, ''),
                        ])),
                        'words' => array_values(array_unique(explode(' ', $primary))),
                        'labels' => $labels,
                    ];
                })
                ->all();
        });
    }
}

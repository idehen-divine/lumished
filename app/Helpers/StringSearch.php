<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class StringSearch extends Helper
{
    /**
     * Check if a search string fuzzy-prefix matches a value.
     *
     * Matches when the search string appears anywhere in the value
     * or is a prefix of any word in the value (case-insensitive).
     *
     * @param  string  $search  The search query
     * @param  string  $value  The value to check against
     * @return bool True if the value matches the search query
     */
    public function matchesWithFuzzyPrefix(string $search, string $value): bool
    {
        $search = mb_strtolower(trim($search));
        $value = mb_strtolower(trim($value));

        if ($search === '' || $value === '') {
            return false;
        }

        if (str_contains($value, $search)) {
            return true;
        }

        foreach (preg_split('/\s+/', $value) as $word) {
            if (str_starts_with($word, $search)) {
                return true;
            }
        }

        return false;
    }
}

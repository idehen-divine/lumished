<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class StringHelper extends Helper
{
    /**
     * Check if query matches text using fuzzy matching.
     * Characters must appear in order but can be non-consecutive.
     *
     * Example: "jhn" matches "John" (j-o-h-n, skip the o)
     *
     * @param  string  $query  The search query
     * @param  string  $text  The text to search within
     * @return bool True if query matches using fuzzy logic
     */
    public function fuzzyMatches(string $query, string $text): bool
    {
        $q = mb_strtolower($query);
        $t = mb_strtolower($text);
        $qIndex = 0;
        $qLength = strlen($q);
        $tLength = strlen($t);

        for ($i = 0; $i < $tLength && $qIndex < $qLength; $i++) {
            if ($t[$i] === $q[$qIndex]) {
                $qIndex++;
            }
        }

        return $qIndex === $qLength;
    }

    /**
     * Calculate how well a query matches text on a scale of 0.0 to 1.0.
     *
     * Returns the proportion of query characters that appear in order.
     * Example: "jhn" in "jonathan" = 3/3 = 1.0, "jzn" in "jonathan" = 2/3 = 0.67
     *
     * @param  string  $query  The search query
     * @param  string  $text  The text to match against
     * @return float Match score from 0.0 (no match) to 1.0 (perfect match)
     */
    public function fuzzyScore(string $query, string $text): float
    {
        $q = mb_strtolower($query);
        $t = mb_strtolower($text);
        $qLength = strlen($q);

        if ($qLength === 0) {
            return 1.0;
        }

        $matched = 0;
        $tIndex = 0;
        $tLength = strlen($t);

        for ($i = 0; $i < $qLength; $i++) {
            while ($tIndex < $tLength && $t[$tIndex] !== $q[$i]) {
                $tIndex++;
            }
            if ($tIndex < $tLength) {
                $matched++;
                $tIndex++;
            }
        }

        return $matched / $qLength;
    }

    /**
     * Match query against multiple text fields using progressive strategies.
     *
     * Applies three strategies in order of strictness:
     *   1. Substring match      — "abc" in "abcdef"                    (fastest)
     *   2. Word-prefix match    — "jo do" matches "John Doe"           (exact prefix per word)
     *   3. Fuzzy match          — chars in order with threshold coverage (most forgiving)
     *
     * Short queries (1–2 chars) require full fuzzy match to avoid noise.
     * Longer queries use configurable threshold (default 0.75 = 75% coverage).
     *
     * @param  string  $query  The search query
     * @param  float  $threshold  Minimum fuzzy score (0.0–1.0). Default 0.75. Short queries (≤2 chars) always require 1.0.
     * @param  string  ...$textFields  One or more fields to check (name, email, etc.)
     * @return bool True if query matches any field using any strategy
     */
    public function matchesWithFuzzyPrefix(string $query, float $threshold = 0.75, string ...$textFields): bool
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '') {
            return false;
        }

        foreach ($textFields as $field) {
            $haystack = mb_strtolower($field);

            // 1. Fast substring check
            if (str_contains($haystack, $normalized)) {
                return true;
            }

            // 2. Every word in the query must prefix-match a word in the text
            if ($this->wordPrefixMatches($normalized, $haystack)) {
                return true;
            }

            // 3. Fuzzy character-order match with a minimum coverage threshold
            //    Short queries (1–2 chars) require a full match to avoid noise.
            $minThreshold = strlen($normalized) <= 2 ? 1.0 : $threshold;
            if ($this->fuzzyScore($normalized, $haystack) >= $minThreshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if every whitespace-separated word in query is a prefix of some word in text.
     *
     * Example: "jo do" → query words ["jo", "do"]
     *          "John Doe" → text words ["john", "doe"]
     *          Result: true (jo- matches john, do- matches doe)
     *
     * Both strings must be lowercased before calling.
     *
     * @param  string  $query  The lowercased query (space-separated words)
     * @param  string  $text  The lowercased text (space-separated words)
     * @return bool True if every query word is a prefix of some text word
     */
    protected function wordPrefixMatches(string $query, string $text): bool
    {
        $queryWords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $textWords = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($queryWords as $qWord) {
            $found = false;
            foreach ($textWords as $tWord) {
                if (str_starts_with($tWord, $qWord)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }
}

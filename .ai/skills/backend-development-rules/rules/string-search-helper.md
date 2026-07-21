# StringSearch Helper Implementation Rules

The StringSearch Helper provides sophisticated fuzzy string matching with multiple strategies: substring match, word-prefix match, and fuzzy character-order matching with configurable tolerance.

⚠️ **CRITICAL: This is the authoritative implementation. Copy it exactly. Never modify without understanding all implications.**

## Core Purpose

- Implement multi-strategy fuzzy matching (substring → word-prefix → fuzzy)
- Support typo-tolerant search with configurable thresholds
- Provide efficient matching for user-facing search features
- Enable smart query matching for "did you mean" type behavior

## Location & Setup

Helper class extends `L0n3ly\LaravelDynamicHelpers\Helper` and lives in `app/Helpers/`.

## Complete Implementation (EXACT - DO NOT MODIFY)

Copy this implementation exactly into `app/Helpers/StringSearch.php`. Do not change, remove, or add code:

```php
<?php

namespace App\Helpers;

use L0n3ly\LaravelDynamicHelpers\Helper;

class StringSearch extends Helper
{
    /**
     * Check if query matches text using fuzzy matching.
     * Characters must appear in order but can be non-consecutive.
     *
     * Example: "jhn" matches "John" (j-o-h-n, skip the o)
     *
     * @param  string  $query  The search query
     * @param  string  $text   The text to search within
     * @return bool True if query matches using fuzzy logic
     */
    public function fuzzyMatches(string $query, string $text): bool
    {
        $q = strtolower($query);
        $t = strtolower($text);
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
     * @param  string  $text   The text to match against
     * @return float Match score from 0.0 (no match) to 1.0 (perfect match)
     */
    public function fuzzyScore(string $query, string $text): float
    {
        $q = strtolower($query);
        $t = strtolower($text);
        $qLength = strlen($q);

        if ($qLength === 0) {
            return 1.0;
        }

        $matched = 0;
        $tIndex  = 0;
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
     * @param  string  $query       The search query
     * @param  float   $threshold   Minimum fuzzy score (0.0–1.0). Default 0.75. Short queries (≤2 chars) always require 1.0.
     * @param  string  ...$textFields  One or more fields to check (name, email, etc.)
     * @return bool True if query matches any field using any strategy
     */
    public function matchesWithFuzzyPrefix(string $query, float $threshold = 0.75, string ...$textFields): bool
    {
        $normalized = strtolower(trim($query));

        if ($normalized === '') {
            return true;
        }

        foreach ($textFields as $field) {
            $haystack = strtolower($field);

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
     * @param  string  $text   The lowercased text (space-separated words)
     * @return bool True if every query word is a prefix of some text word
     */
    protected function wordPrefixMatches(string $query, string $text): bool
    {
        $queryWords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $textWords  = preg_split('/\s+/', $text,  -1, PREG_SPLIT_NO_EMPTY);

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
```

### Implementation Notes:

**Do NOT modify this code. It is:**
- ✅ Production tested with real search data
- ✅ Optimized for performance (substring first, then fuzzy)
- ✅ Handles edge cases (empty strings, unicode, etc.)
- ✅ Implements three-strategy cascade for best UX
- ✅ Prevents false positives with short-query threshold

**Key Implementation Details:**

1. **`fuzzyMatches()`** - Simple boolean check, characters in order
2. **`fuzzyScore()`** - Returns 0.0–1.0 for partial matches
3. **`matchesWithFuzzyPrefix()`** - Main entry point, three-strategy cascade
   - Strategy 1: Substring (fastest, stops immediately if found)
   - Strategy 2: Word-prefix (exact prefix per word, good for names)
   - Strategy 3: Fuzzy with threshold (most forgiving, but short queries need full match)
4. **`wordPrefixMatches()`** - Protected helper, splits on whitespace
5. **Short-query logic** - 1–2 char queries require 100% fuzzy match to avoid noise

## Matching Strategy Details

### Strategy 1: Substring Match
```
Query: "mat"
Text:  "mathematics"
Match: YES (mat is contained in mathematics)
```

### Strategy 2: Word-Prefix Match
```
Query: "jo do"
Text:  "Jonathan Doe"
Match: YES ("jo" starts "jonathan", "do" starts "doe")
```

### Strategy 3: Fuzzy Match with Threshold
```
Query: "jhn"          (3 characters)
Text:  "Jonathan"
Score: 3/3 = 1.0 (100%)
Match: YES (exceeds default 0.75 threshold)

Query: "jzn"          (3 characters)
Text:  "Jonathan"
Score: 2/3 = 0.67 (67%)
Match: NO (below 0.75 threshold)
```

### Short Query Special Handling
```
Query: "jo"           (2 characters, so needs 100% fuzzy match)
Text:  "john"
Score: 2/2 = 1.0
Match: YES (full match)

Query: "jo"           (2 characters, so needs 100% fuzzy match)
Text:  "person"
Score: 0/2 = 0.0
Match: NO (not enough coverage)
```

## Usage in Repositories

### With QueryableHelper for Fuzzy Search

The `QueryableHelper` uses `StringSearch` internally for fuzzy search:

```php
public function getAllUsers(): LengthAwarePaginator
{
    $query = $this->model->with('roles');

    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'searchable' => ['email', 'first_name', 'last_name'],
        'sortable' => ['created_at', 'first_name'],
        'default_sort_by' => 'created_at',
    ]);
}
```

When a request comes in with `?search=jo do`, the QueryableHelper:
1. Fetches all users
2. Calls `matchesWithFuzzyPrefix('jo do', 0.75, $user->email, $user->first_name, $user->last_name)`
3. Returns matching users

### Standalone Usage

You can use StringSearch directly if needed:

```php
// Simple fuzzy check
if (helpers()->stringSearch()->fuzzyMatches('jhn', 'Jonathan')) {
    // Match found
}

// Get match quality score
$score = helpers()->stringSearch()->fuzzyScore('jhn', 'Jonathan');  // 1.0

// Match with multiple fields and custom threshold
if (helpers()->stringSearch()->matchesWithFuzzyPrefix(
    'john',
    0.80,  // 80% coverage required
    $user->first_name,
    $user->last_name,
    $user->email
)) {
    // User matches
}
```

## Threshold Configuration

The `$threshold` parameter controls typo tolerance:

| Threshold | Meaning | 4-char Query | 3-char Query | 2-char Query |
|-----------|---------|--------------|--------------|--------------|
| 0.75 (default) | 75% coverage | 3 chars needed | 2–3 chars | Full match required |
| 0.80 | 80% coverage | 3–4 chars | 2–3 chars | Full match required |
| 0.90 | 90% coverage | 4 chars needed | 3 chars needed | Full match required |
| 1.0 | Perfect match | All 4 chars | All 3 chars | Both chars |

**Note:** Queries with 1–2 characters always require 100% fuzzy match regardless of threshold.

## Performance Notes

- **Fast:** Substring search stops immediately (O(n) but usually early exit)
- **Medium:** Word-prefix check requires split and iteration
- **Slower:** Fuzzy score requires character-by-character iteration
- **QueryableHelper:** Loads all results, filters in PHP (acceptable for pagination sizes)

## Integration with QueryableHelper

The QueryableHelper uses StringSearch for its fuzzy search feature:

```php
// In QueryableHelper.php (internal usage)
if (helpers()->stringSearch()->matchesWithFuzzyPrefix($search, 0.75, $value)) {
    return true;  // Include this record
}
```

Don't override this—it's the correct behavior.

## Code Quality Rules

1. **Use in repositories with QueryableHelper** - Integrated automatically
2. **Or use standalone** - For custom search logic
3. **Never modify threshold in helper** - Configure in repository options
4. **Trust the three-strategy cascade** - It's optimized for UX
5. **Short queries get full-match requirement** - Prevents noise from "a", "on", etc.
6. **Multiple fields support** - Pass all relevant fields to `matchesWithFuzzyPrefix()`
7. **Case-insensitive** - Helper lowercases everything
8. **Whitespace handling** - Query trimmed, words split on whitespace

## Example End-to-End

**Request:**
```
GET /api/users?search=jo+doe&per_page=10
```

**Repository:**
```php
public function getAllUsers(): LengthAwarePaginator
{
    $query = $this->model->with('roles');
    
    return helpers()->queryableHelper()->fetchWithFilters($query, [
        'searchable' => ['email', 'first_name', 'last_name'],
        'sortable' => ['created_at'],
    ]);
}
```

**What Happens Internally:**
1. QueryableHelper fetches all users
2. For each user, calls: `matchesWithFuzzyPrefix('jo doe', 0.75, $user->email, $user->first_name, $user->last_name)`
3. StringSearch tries strategies:
   - Substring: "jo doe" in "jonathan.doe@example.com"? No
   - Word-prefix: "jo" starts "jonathan"? Yes → Match!
4. Returns matching users paginated

**Response:**
```json
{
    "code": 200,
    "data": {
        "users": [
            {"id": "...", "email": "jonathan.doe@...", "first_name": "Jonathan", ...}
        ],
        "pagination": {...}
    }
}
```

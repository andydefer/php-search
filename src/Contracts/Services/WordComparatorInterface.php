<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for word comparison operations using n-gram analysis.
 *
 * Defines the contract for comparing words, calculating scores,
 * and finding best matches between query words and candidate words.
 */
interface WordComparatorInterface
{
    /**
     * Counts matching letters between two words.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return int Number of matching characters
     */
    public function countMatchingLetters(string $word1, string $word2): int;

    /**
     * Applies quick length-based pre-filtering.
     *
     * @param  string  $queryWord  The query word
     * @param  string  $itemWord  The candidate word
     * @return bool True if the candidate passes the length filter
     */
    public function passesLengthFilter(string $queryWord, string $itemWord): bool;

    /**
     * Calculates match score using precomputed word data.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<string, mixed>  $itemData  Precomputed item word data
     * @return float The calculated score
     */
    public function calculateScore(array $queryData, array $itemData): float;

    /**
     * Finds the best matching word for a query from a list of candidates.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<int, array<string, mixed>>  $itemWords  List of precomputed item words
     * @return array<string, float> Best match results with score and percentage
     */
    public function findBestMatch(array $queryData, array $itemWords): array;
}

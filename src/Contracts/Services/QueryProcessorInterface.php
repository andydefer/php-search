<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for query processing operations.
 *
 * Defines the contract for processing search queries, computing match scores,
 * and sorting results for fuzzy string matching.
 */
interface QueryProcessorInterface
{
    /**
     * Processes a query string into word data.
     *
     * @param  string  $query  The search query
     * @return array<int, array<string, mixed>> Processed query words
     */
    public function process(string $query): array;

    /**
     * Computes the match score between query words and item words.
     *
     * @param  array<int, array<string, mixed>>  $queryWords  Processed query words
     * @param  array<int, array<string, mixed>>  $itemWords  Processed item words
     * @return array<string, float>|null Match results or null if no match
     */
    public function computeScore(array $queryWords, array $itemWords): ?array;

    /**
     * Sorts results by relevance.
     *
     * Sorts by percentage descending, then score descending.
     *
     * @param  array<int, array<string, mixed>>  &$results  Results to sort
     */
    public function sortResults(array &$results): void;
}

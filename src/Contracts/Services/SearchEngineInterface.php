<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for the main fuzzy search engine.
 *
 * Defines the contract for orchestrating all components to provide
 * a complete fuzzy search solution with preprocessing, filtering, and scoring.
 */
interface SearchEngineInterface
{
    /**
     * Replaces the entire dataset with a new one.
     *
     * @param  array<int, string>  $data  New dataset
     */
    public function setData(array $data): self;

    /**
     * Returns the current dataset.
     *
     * @return array<int, string>
     */
    public function getData(): array;

    /**
     * Clears all internal caches to free memory.
     */
    public function clearCache(): void;

    /**
     * Searches the dataset for items matching the query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results to return
     * @return array<int, array<string, mixed>> Array of results with scores
     */
    public function search(string $query, int $limit = 5): array;
}

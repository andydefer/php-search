<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\DatasetPreprocessorInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Contracts\Services\SearchEngineInterface;

/**
 * Main fuzzy search engine.
 *
 * Orchestrates all components to provide a complete fuzzy search solution
 * with preprocessing, filtering, and scoring.
 *
 * @example
 * $engine = new SearchEngine(['John Doe', 'Jane Smith']);
 * $results = $engine->search('Jon Doe', 3);
 */
class SearchEngineService implements SearchEngineInterface
{
    /**
     * @param  array<int, string>  $data  Initial dataset
     */
    public function __construct(
        private DatasetPreprocessorInterface $preprocessor,
        private QueryProcessorInterface $queryProcessor,
        private PreFilterInterface $preFilter,
        array $data = []
    ) {
        if (! empty($data)) {
            $this->preprocessor->setData($data);
        }
    }

    /**
     * Replaces the entire dataset with a new one.
     *
     * @param  array<int, string>  $data  New dataset
     */
    public function setData(array $data): self
    {
        $this->preprocessor->setData($data);

        return $this;
    }

    /**
     * Returns the current dataset.
     *
     * @return array<int, string>
     */
    public function getData(): array
    {
        return $this->preprocessor->getRawData();
    }

    /**
     * Clears all internal caches to free memory.
     */
    /**
     * Clears all internal caches to free memory.
     */
    public function clearCache(): void
    {
        $this->preprocessor->clearCache();
    }

    /**
     * Searches the dataset for items matching the query.
     *
     * @param  string  $query  The search query
     * @param  int  $limit  Maximum number of results to return
     * @return array<int, array<string, mixed>> Array of results with scores
     */
    public function search(string $query, int $limit = 5): array
    {
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $results = [];

        foreach ($this->preprocessor->getPreprocessed() as $item => $itemWords) {
            if (! $this->preFilter->passes($item, $query)) {
                continue;
            }

            $score = $this->queryProcessor->computeScore($processedQuery, $itemWords);

            if ($score !== null) {
                $results[] = array_merge(['name' => $item], $score);
            }
        }

        $this->queryProcessor->sortResults($results);

        return array_slice($results, 0, $limit);
    }
}

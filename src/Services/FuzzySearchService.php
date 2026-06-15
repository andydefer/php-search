<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\FuzzySearchInterface;

/**
 * Main fuzzy search engine for CLI usage.
 */
class FuzzySearchService implements FuzzySearchInterface
{
    public function __construct(
        private readonly SearchEngineService $searchEngine
    ) {}

    /**
     * Load data from JSON file and search
     *
     * @param  string  $jsonFile  Path to JSON file
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results
     * @return array<int, array<string, mixed>>
     */
    public function searchFromFile(string $jsonFile, string $query, int $limit = 5): array
    {
        if (! file_exists($jsonFile)) {
            throw new \InvalidArgumentException("JSON file not found: {$jsonFile}");
        }

        $jsonContent = file_get_contents($jsonFile);
        $data = json_decode($jsonContent, true);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON format. Expected array of strings.');
        }

        // Validate data is array of strings
        foreach ($data as $item) {
            if (! is_string($item)) {
                throw new \InvalidArgumentException('JSON must contain only strings. Found invalid type.');
            }
        }

        $this->searchEngine->setData($data);

        return $this->searchEngine->search($query, $limit);
    }

    /**
     * Search directly from array
     *
     * @param  array<int, string>  $data  Dataset
     * @param  string  $query  Search query
     * @param  int  $limit  Maximum results
     * @return array<int, array<string, mixed>>
     */
    public function searchFromArray(array $data, string $query, int $limit = 5): array
    {
        $this->searchEngine->setData($data);

        return $this->searchEngine->search($query, $limit);
    }

    /**
     * Format results for CLI output
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public function formatResults(array $results, string $query): string
    {
        if (empty($results)) {
            return "No results found for '{$query}'.\n";
        }

        $output = 'Top '.count($results)." results for '{$query}':\n";
        $output .= str_repeat('=', 80)."\n";

        foreach ($results as $index => $result) {
            $isMax = ($result['percentage'] == 100) ? ' - [MAX POSSIBLE]' : '';
            $output .= ($index + 1).'. '.$result['name'].
                ' (score: '.$result['score'].
                ' / max: '.$result['max_possible'].
                ') - Relevance: '.$result['percentage'].'%'.
                $isMax."\n";
        }

        return $output;
    }
}

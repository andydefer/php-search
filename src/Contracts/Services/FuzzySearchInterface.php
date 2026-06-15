<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for fuzzy search operations.
 * 
 * Defines the contract for performing fuzzy searches on datasets
 * either from JSON files or arrays, with result formatting.
 */
interface FuzzySearchInterface
{
    /**
     * Load data from JSON file and search
     *
     * @param string $jsonFile Path to JSON file
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException If file not found or invalid JSON format
     */
    public function searchFromFile(string $jsonFile, string $query, int $limit = 5): array;

    /**
     * Search directly from array
     *
     * @param array<int, string> $data Dataset
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array<int, array<string, mixed>>
     */
    public function searchFromArray(array $data, string $query, int $limit = 5): array;

    /**
     * Format results for CLI output
     *
     * @param array<int, array<string, mixed>> $results
     * @param string $query
     * @return string
     */
    public function formatResults(array $results, string $query): string;
}

<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for dataset preprocessing operations.
 *
 * Defines the contract for preprocessing datasets by converting items
 * into precomputed word data structures for optimized searching.
 */
interface DatasetPreprocessorInterface
{
    /**
     * Sets and preprocesses a new dataset.
     *
     * @param  array<int, string>  $data  The dataset to process
     */
    public function setData(array $data): void;

    /**
     * Returns the raw dataset.
     *
     * @return array<int, string>
     */
    public function getRawData(): array;

    /**
     * Returns the preprocessed dataset.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getPreprocessed(): array;

    /**
     * Clears the cache for this dataset.
     */
    public function clearCache(): void;
}

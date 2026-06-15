<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for n-gram generation and weighting operations.
 *
 * Defines the contract for generating character n-grams (2, 3, and 4-grams)
 * and calculating weighted scores for string comparison.
 */
interface NGramEngineInterface
{
    /**
     * Generates all n-grams for a word.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generate(string $word): array;

    /**
     * Generates n-grams with caching.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generateWithCache(string $word): array;

    /**
     * Calculates the weight of an n-gram based on its length.
     *
     * @param  int  $length  The n-gram length (2-4)
     * @return float The weight value
     */
    public function getWeight(int $length): float;

    /**
     * Calculates the maximum possible score for a word.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScore(string $word): float;

    /**
     * Gets maximum possible score with caching.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScoreWithCache(string $word): float;

    /**
     * Clears all internal caches.
     */
    public function clearCache(): void;
}

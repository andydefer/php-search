<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;

/**
 * Generates and weights n-grams for string comparison.
 *
 * Creates character sequences of lengths 2, 3, and 4 (bigrams, trigrams, 4-grams)
 * and assigns weights based on gram length for scoring.
 */
class NGramEngineService implements NGramEngineInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SearchConfigInterface $config
    ) {}

    /**
     * Stores a key in the keys registry.
     *
     * @param  string  $key  The cache key to track
     */
    private function trackKey(string $key): void
    {
        $keys = $this->cache->get($this->config->getCacheKeyKeys(), []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->cache->setWithDefaultTtl($this->config->getCacheKeyKeys(), $keys);
        }
    }

    /**
     * Generates all n-grams for a word.
     *
     * Creates overlapping character sequences of lengths 2, 3, and 4.
     * Returns unique grams only.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generate(string $word): array
    {
        // Convertir en minuscules pour l'insensibilité à la casse
        $word = strtolower($word);
        $length = strlen($word);
        $grams = [];

        for (
            $gramLength = $this->config->getMinNgramLength();
            $gramLength <= $this->config->getMaxNgramLength();
            $gramLength++
        ) {
            if ($gramLength > $length) {
                continue;
            }

            for ($i = 0; $i <= $length - $gramLength; $i++) {
                $grams[] = substr($word, $i, $gramLength);
            }
        }

        return array_values(array_unique($grams));
    }

    /**
     * Generates n-grams with caching.
     *
     * @param  string  $word  The word to analyze
     * @return array<int, string> Array of unique n-grams
     */
    public function generateWithCache(string $word): array
    {
        // Normaliser le mot pour la clé de cache
        $normalizedWord = strtolower($word);
        $key = $this->config->getCacheKeyGrams().$normalizedWord;
        $cached = $this->cache->get($key);

        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $grams = $this->generate($word);
        $this->cache->setWithDefaultTtl($key, $grams);
        $this->trackKey($key);

        return $grams;
    }

    /**
     * Calculates the weight of an n-gram based on its length.
     *
     * Longer grams are weighted more heavily as they are more specific.
     *
     * @param  int  $length  The n-gram length (2-4)
     * @return float The weight value
     */
    public function getWeight(int $length): float
    {
        return $length + (($length - 1) * 0.5);
    }

    /**
     * Calculates the maximum possible score for a word.
     *
     * Sum of weights for all unique n-grams in the word.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScore(string $word): float
    {
        $grams = $this->generate($word);
        $maxScore = 0.0;

        foreach ($grams as $gram) {
            $maxScore += $this->getWeight(strlen($gram));
        }

        return round($maxScore, 1);
    }

    /**
     * Gets maximum possible score with caching.
     *
     * @param  string  $word  The word to evaluate
     * @return float The maximum possible score
     */
    public function getMaxScoreWithCache(string $word): float
    {
        // Normaliser le mot pour la clé de cache
        $normalizedWord = strtolower($word);
        $key = $this->config->getCacheKeyScores().$normalizedWord;
        $cached = $this->cache->get($key);

        if ($cached !== null && is_float($cached)) {
            return $cached;
        }

        $score = $this->getMaxScore($word);
        $this->cache->setWithDefaultTtl($key, $score);
        $this->trackKey($key);

        return $score;
    }

    /**
     * Clears all internal caches.
     */
    public function clearCache(): void
    {
        $keys = $this->cache->get($this->config->getCacheKeyKeys(), []);

        foreach ($keys as $key) {
            $this->cache->delete($key);
        }

        $this->cache->delete($this->config->getCacheKeyKeys());
    }
}

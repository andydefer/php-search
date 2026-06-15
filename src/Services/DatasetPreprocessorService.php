<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\DatasetPreprocessorInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Services\StringNormalizerService;

/**
 * Preprocesses and stores dataset for optimized searching.
 *
 * Converts all items into precomputed word data structures
 * to avoid redundant processing during searches.
 */
class DatasetPreprocessorService implements DatasetPreprocessorInterface
{
    public function __construct(
        private readonly StringNormalizerService $normalizer,
        private readonly NGramEngineInterface $ngramEngine,
        private readonly CacheInterface $cache,
        private readonly SearchConfigInterface $config
    ) {}

    /**
     * Sets and preprocesses a new dataset.
     *
     * @param  array<int, string>  $data  The dataset to process
     */
    public function setData(array $data): void
    {
        $preprocessed = [];

        foreach ($data as $item) {
            $cleaned = $this->normalizer->clean($item);
            $words = $this->splitWords($cleaned);

            $processedWords = [];
            foreach ($words as $word) {
                $normalized = strtolower($word);
                $processedWords[] = [
                    'original' => $word,
                    'normalized' => $normalized,
                    'max_score' => $this->ngramEngine->getMaxScoreWithCache($normalized),
                    'ngrams' => $this->ngramEngine->generateWithCache($normalized),
                ];
            }
            $preprocessed[$item] = $processedWords;
        }

        // Utiliser setWithDefaultTtl car set() nécessite 3 arguments (key, value, ttl)
        $this->cache->setWithDefaultTtl($this->config->getCacheKeyRawData(), $data);
        $this->cache->setWithDefaultTtl($this->config->getCacheKeyPreprocessed(), $preprocessed);
    }

    /**
     * Returns the raw dataset.
     *
     * @return array<int, string>
     */
    public function getRawData(): array
    {
        $rawData = $this->cache->get($this->config->getCacheKeyRawData());
        return is_array($rawData) ? $rawData : [];
    }

    /**
     * Returns the preprocessed dataset.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getPreprocessed(): array
    {
        $preprocessed = $this->cache->get($this->config->getCacheKeyPreprocessed());
        return is_array($preprocessed) ? $preprocessed : [];
    }

    /**
     * Splits a cleaned string into individual words.
     *
     * @param  string  $string  The cleaned string
     * @return array<int, string> Array of words
     */
    private function splitWords(string $string): array
    {
        $words = explode(' ', $string);

        return array_values(array_filter($words, fn(string $word): bool => $word !== ''));
    }

    /**
     * Clears the cache for this dataset.
     */
    public function clearCache(): void
    {
        $this->cache->delete($this->config->getCacheKeyRawData());
        $this->cache->delete($this->config->getCacheKeyPreprocessed());
    }
}

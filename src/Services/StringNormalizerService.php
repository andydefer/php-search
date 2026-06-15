<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\StringNormalizerInterface;

/**
 * Handles string normalization and cleaning operations.
 *
 * Removes accents, diacritics, and special characters while normalizing whitespace.
 * Results are cached for performance when processing the same string multiple times.
 */
class StringNormalizerService implements StringNormalizerInterface
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
     * Removes special characters from a string.
     *
     * Keeps only alphanumeric characters, spaces, apostrophes, and hyphens.
     *
     * @param  string  $input  The string to clean
     * @return string The string with special characters replaced by spaces
     */
    public function removeSpecialChars(string $input): string
    {
        return preg_replace('/[^a-zA-Z0-9\s\'-]/u', ' ', $input) ?? '';
    }

    /**
     * Converts accented characters to their ASCII equivalents.
     *
     * @param  string  $input  The string to normalize
     * @return string The string with accents removed
     */
    public function removeAccents(string $input): string
    {
        return strtr($input, $this->config->getDiacritics());
    }

    /**
     * Completely cleans a string with caching.
     *
     * Applies accent removal, special character filtering, and whitespace normalization.
     * Results are cached for identical input strings.
     *
     * @param  string  $input  The string to clean
     * @return string The fully cleaned string
     */
    public function clean(string $input): string
    {
        $cacheKey = $this->config->getCacheKeyNormalized().$input;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_string($cached)) {
            return $cached;
        }

        $noAccents = $this->removeAccents($input);
        $noSpecialChars = $this->removeSpecialChars($noAccents);
        $normalized = preg_replace('/\s+/', ' ', trim($noSpecialChars)) ?? '';

        $this->cache->setWithDefaultTtl($cacheKey, $normalized);
        $this->trackKey($cacheKey);

        return $normalized;
    }

    /**
     * Clears the cache for this service.
     * Deletes all cached normalized strings.
     */
    public function clearCache(): void
    {
        $keys = $this->cache->get($this->config->getCacheKeyKeys(), []);
        $prefix = $this->config->getCacheKeyNormalized();

        foreach ($keys as $key) {
            if (str_starts_with($key, $prefix)) {
                $this->cache->delete($key);
            }
        }
    }
}

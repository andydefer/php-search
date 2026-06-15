<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for string normalization and cleaning operations.
 * 
 * Defines the contract for removing accents, diacritics, special characters,
 * and normalizing whitespace with caching support.
 */
interface StringNormalizerInterface
{
    /**
     * Removes special characters from a string.
     *
     * Keeps only alphanumeric characters, spaces, apostrophes, and hyphens.
     *
     * @param string $input The string to clean
     * @return string The string with special characters replaced by spaces
     */
    public function removeSpecialChars(string $input): string;

    /**
     * Converts accented characters to their ASCII equivalents.
     *
     * @param string $input The string to normalize
     * @return string The string with accents removed
     */
    public function removeAccents(string $input): string;

    /**
     * Completely cleans a string with caching.
     *
     * Applies accent removal, special character filtering, and whitespace normalization.
     * Results are cached for identical input strings.
     *
     * @param string $input The string to clean
     * @return string The fully cleaned string
     */
    public function clean(string $input): string;

    /**
     * Clears the internal cache.
     */
    public function clearCache(): void;
}

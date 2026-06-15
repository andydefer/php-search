<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for pre-filtering operations.
 * 
 * Defines the contract for applying letter frequency analysis
 * to quickly eliminate unlikely matches before detailed comparison.
 */
interface PreFilterInterface
{
    /**
     * Checks if an item passes the letter frequency filter.
     *
     * @param string $item The item text
     * @param string $query The search query
     * @return bool True if the item has enough matching letters
     */
    public function passes(string $item, string $query): bool;
}

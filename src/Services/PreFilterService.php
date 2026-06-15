<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\StringNormalizerInterface;

/**
 * Applies pre-filtering to reduce candidates before detailed comparison.
 *
 * Uses letter frequency analysis to quickly eliminate unlikely matches.
 */
class PreFilterService implements PreFilterInterface
{
    public function __construct(
        private readonly StringNormalizerInterface $normalizer,
        private readonly SearchConfigInterface $config
    ) {}

    /**
     * Checks if an item passes the letter frequency filter.
     *
     * @param  string  $item  The item text
     * @param  string  $query  The search query
     * @return bool True if the item has enough matching letters
     */
    public function passes(string $item, string $query): bool
    {
        $cleanedItem = $this->normalizer->clean($item);
        $cleanedQuery = $this->normalizer->clean($query);

        // Convertir en minuscules pour l'insensibilité à la casse
        $cleanedItem = strtolower($cleanedItem);
        $cleanedQuery = strtolower($cleanedQuery);

        $itemLetters = array_unique(str_split(preg_replace('/\s+/', '', $cleanedItem)));
        $queryLetters = array_unique(str_split(preg_replace('/\s+/', '', $cleanedQuery)));

        if (empty($queryLetters)) {
            return false;
        }

        $matchingLetters = 0;
        foreach ($queryLetters as $letter) {
            if (in_array($letter, $itemLetters, true)) {
                $matchingLetters++;
            }
        }

        $percentage = ($matchingLetters / count($queryLetters)) * 100;

        return $percentage >= $this->config->getMinLetterMatchPercentage();
    }
}

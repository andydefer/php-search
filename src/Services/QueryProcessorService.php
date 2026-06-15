<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Contracts\Services\StringNormalizerInterface;
use AndyDefer\PhpSearch\Contracts\Services\WordComparatorInterface;

/**
 * Processes search queries and computes match scores.
 *
 * Handles query preprocessing, score calculation, and result sorting.
 */
class QueryProcessorService implements QueryProcessorInterface
{

    public function __construct(
        private readonly StringNormalizerInterface $normalizer,
        private readonly  NGramEngineInterface $ngramEngine,
        private readonly WordComparatorInterface $comparator
    ) {}

    /**
     * Processes a query string into word data.
     *
     * @param  string  $query  The search query
     * @return array<int, array<string, mixed>> Processed query words
     */
    public function process(string $query): array
    {
        $cleaned = $this->normalizer->clean($query);
        $words = $this->splitWords($cleaned);

        if (empty($words)) {
            return [];
        }

        $processedWords = [];
        foreach ($words as $word) {
            $processedWords[] = [
                'original' => $word,
                'normalized' => strtolower($word),
                'ngrams' => $this->ngramEngine->generateWithCache(strtolower($word)),
            ];
        }

        return $processedWords;
    }

    /**
     * Computes the match score between query words and item words.
     *
     * @param  array<int, array<string, mixed>>  $queryWords  Processed query words
     * @param  array<int, array<string, mixed>>  $itemWords  Processed item words
     * @return array<string, float>|null Match results or null if no match
     */
    public function computeScore(array $queryWords, array $itemWords): ?array
    {
        $totalScore = 0.0;
        $totalMaxPossible = 0.0;
        $totalPercentage = 0.0;
        $wordCount = 0;

        foreach ($queryWords as $queryData) {
            $bestMatch = $this->comparator->findBestMatch($queryData, $itemWords);

            if ($bestMatch['score'] > 0) {
                $totalScore += $bestMatch['score'];
                $totalMaxPossible += $bestMatch['max_possible'];
                $totalPercentage += $bestMatch['percentage'];
                $wordCount++;
            }
        }

        if ($totalScore === 0.0 || $wordCount === 0) {
            return null;
        }

        return [
            'score' => $totalScore,
            'max_possible' => $totalMaxPossible,
            'percentage' => round($totalPercentage / $wordCount, 2),
        ];
    }

    /**
     * Sorts results by relevance.
     *
     * Sorts by percentage descending, then score descending.
     *
     * @param  array<int, array<string, mixed>>  &$results  Results to sort
     */
    public function sortResults(array &$results): void
    {
        usort($results, function (array $a, array $b): int {
            if ($b['percentage'] === $a['percentage']) {
                return $b['score'] <=> $a['score'];
            }

            return $b['percentage'] <=> $a['percentage'];
        });
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
}

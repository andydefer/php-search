<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\WordComparatorInterface;

/**
 * Compares words using n-gram analysis with performance optimizations.
 *
 * Implements pre-filtering, candidate selection, and early stopping
 * to efficiently find the best matching word.
 */
class WordComparatorService implements WordComparatorInterface
{
    public function __construct(
        private readonly NGramEngineInterface $ngramEngine,
        private readonly SearchConfigInterface $config
    ) {}

    /**
     * Counts matching letters between two words.
     *
     * @param  string  $word1  First word
     * @param  string  $word2  Second word
     * @return int Number of matching characters
     */
    public function countMatchingLetters(string $word1, string $word2): int
    {
        $letters1 = count_chars($word1, 1);
        $letters2 = count_chars($word2, 1);
        $matches = 0;

        foreach ($letters1 as $char => $count) {
            if (isset($letters2[$char])) {
                $matches += min($count, $letters2[$char]);
            }
        }

        return $matches;
    }

    /**
     * Applies quick length-based pre-filtering.
     *
     * @param  string  $queryWord  The query word
     * @param  string  $itemWord  The candidate word
     * @return bool True if the candidate passes the length filter
     */
    public function passesLengthFilter(string $queryWord, string $itemWord): bool
    {
        $queryLength = strlen($queryWord);
        $itemLength = strlen($itemWord);

        if ($itemLength < $queryLength * $this->config->getMinLengthRatio()) {
            return false;
        }

        if ($itemLength < 2 || $queryLength < 2) {
            return false;
        }

        return true;
    }

    /**
     * Calculates match score using precomputed word data.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<string, mixed>  $itemData  Precomputed item word data
     * @return float The calculated score
     */
    public function calculateScore(array $queryData, array $itemData): float
    {
        $queryWord = $queryData['normalized'];
        $itemWord = $itemData['normalized'];
        $maxPossible = $itemData['max_score'];

        // Perfect match detection
        if (str_contains($itemWord, $queryWord)) {
            return $maxPossible;
        }

        $score = 0.0;

        foreach ($queryData['ngrams'] as $gram) {
            if (str_contains($itemWord, $gram)) {
                $score += $this->ngramEngine->getWeight(strlen($gram));
            }

            // Early stop for high scores
            if ($score >= $maxPossible * $this->config->getEarlyStopThreshold()) {
                break;
            }
        }

        return $score;
    }

    /**
     * Finds the best matching word for a query from a list of candidates.
     *
     * @param  array<string, mixed>  $queryData  Precomputed query word data
     * @param  array<int, array<string, mixed>>  $itemWords  List of precomputed item words
     * @return array<string, float> Best match results with score and percentage
     */
    public function findBestMatch(array $queryData, array $itemWords): array
    {
        $queryWord = $queryData['normalized'];
        $candidates = [];

        // Pre-filter candidates
        foreach ($itemWords as $index => $itemData) {
            if (! $this->passesLengthFilter($queryWord, $itemData['normalized'])) {
                continue;
            }

            $letterMatches = $this->countMatchingLetters($queryWord, $itemData['normalized']);
            if ($letterMatches > 0) {
                $candidates[] = ['index' => $index, 'matches' => $letterMatches];
            }
        }

        if (empty($candidates)) {
            return ['score' => 0.0, 'max_possible' => 0.0, 'percentage' => 0.0];
        }

        // Keep only top candidates
        usort($candidates, fn ($a, $b) => $b['matches'] <=> $a['matches']);
        $candidates = array_slice($candidates, 0, $this->config->getMaxCandidates());

        $bestScore = 0.0;
        $bestMaxPossible = 0.0;
        $bestPercentage = 0.0;

        foreach ($candidates as $candidate) {
            $itemData = $itemWords[$candidate['index']];
            $score = $this->calculateScore($queryData, $itemData);
            $maxPossible = $itemData['max_score'];

            $queryLength = max(strlen($queryWord), 1);
            $wordLength = max(strlen($itemData['normalized']), 1);

            if ($maxPossible > 0) {
                $percentage = ($score / $queryLength) * 100 / ($maxPossible / $wordLength);
                $percentage = min(round($percentage, 2), 100.0);
            } else {
                $percentage = 0.0;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMaxPossible = $maxPossible;
                $bestPercentage = $percentage;
            }
        }

        return [
            'score' => $bestScore,
            'max_possible' => $bestMaxPossible,
            'percentage' => $bestPercentage,
        ];
    }
}

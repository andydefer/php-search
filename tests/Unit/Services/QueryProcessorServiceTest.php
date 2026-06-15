<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Contracts\Services\StringNormalizerInterface;
use AndyDefer\PhpSearch\Contracts\Services\WordComparatorInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class QueryProcessorServiceTest extends TestCase
{
    private QueryProcessorInterface $service;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(QueryProcessorInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_process_returns_processed_words(): void
    {
        // Arrange
        $query = 'John Doe';

        // Act
        $result = $this->service->process($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('original', $result[0]);
        $this->assertArrayHasKey('normalized', $result[0]);
        $this->assertArrayHasKey('ngrams', $result[0]);
    }

    public function test_process_handles_empty_query(): void
    {
        // Arrange
        $query = '';

        // Act
        $result = $this->service->process($query);

        // Assert
        $this->assertSame([], $result);
    }

    public function test_process_handles_special_characters(): void
    {
        // Arrange
        $query = 'Jean-Luc Mélenchon!';

        // Act
        $result = $this->service->process($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('jean-luc', $result[0]['normalized']);
        $this->assertEquals('melenchon', $result[1]['normalized']);
    }

    public function test_process_normalizes_to_lowercase(): void
    {
        // Arrange
        $query = 'JOHN DOE';

        // Act
        $result = $this->service->process($query);

        // Assert
        $this->assertEquals('john', $result[0]['normalized']);
        $this->assertEquals('doe', $result[1]['normalized']);
    }

    public function test_process_generates_ngrams(): void
    {
        // Arrange
        $query = 'ab';

        // Act
        $result = $this->service->process($query);

        // Assert
        $this->assertIsArray($result[0]['ngrams']);
        $this->assertContains('ab', $result[0]['ngrams']);
    }

    public function test_compute_score_returns_correct_structure(): void
    {
        // Arrange
        $queryWords = $this->service->process('John');
        $itemWords = $this->createItemWords('John');

        // Act
        $result = $this->service->computeScore($queryWords, $itemWords);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('max_possible', $result);
        $this->assertArrayHasKey('percentage', $result);
    }

    public function test_compute_score_returns_null_for_no_match(): void
    {
        // Arrange
        $queryWords = $this->service->process('XYZ');
        $itemWords = $this->createItemWords('ABC');

        // Act
        $result = $this->service->computeScore($queryWords, $itemWords);

        // Assert
        $this->assertNull($result);
    }

    public function test_compute_score_handles_multiple_words(): void
    {
        // Arrange
        $queryWords = $this->service->process('John Doe');
        $itemWords = $this->createItemWords('John Doe');

        // Act
        $result = $this->service->computeScore($queryWords, $itemWords);

        // Assert
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['score']);
    }

    public function test_sort_results_sorts_by_percentage_descending(): void
    {
        // Arrange
        $results = [
            ['name' => 'Item1', 'score' => 10, 'max_possible' => 100, 'percentage' => 80],
            ['name' => 'Item2', 'score' => 90, 'max_possible' => 100, 'percentage' => 90],
            ['name' => 'Item3', 'score' => 50, 'max_possible' => 100, 'percentage' => 70],
        ];

        // Act
        $this->service->sortResults($results);

        // Assert
        $this->assertEquals('Item2', $results[0]['name']);
        $this->assertEquals('Item1', $results[1]['name']);
        $this->assertEquals('Item3', $results[2]['name']);
    }

    public function test_sort_results_sorts_by_score_when_percentage_equal(): void
    {
        // Arrange
        $results = [
            ['name' => 'Item1', 'score' => 50, 'max_possible' => 100, 'percentage' => 80],
            ['name' => 'Item2', 'score' => 90, 'max_possible' => 100, 'percentage' => 80],
            ['name' => 'Item3', 'score' => 70, 'max_possible' => 100, 'percentage' => 80],
        ];

        // Act
        $this->service->sortResults($results);

        // Assert
        $this->assertEquals('Item2', $results[0]['name']);
        $this->assertEquals('Item3', $results[1]['name']);
        $this->assertEquals('Item1', $results[2]['name']);
    }

    public function test_sort_results_handles_empty_array(): void
    {
        // Arrange
        $results = [];

        // Act
        $this->service->sortResults($results);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_full_search_workflow(): void
    {
        // Arrange
        $items = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Albert Einstein'  // Remplacer Marie Curie par quelque chose sans lettres communes avec Leonard
        ];

        $itemWordsList = [];
        foreach ($items as $item) {
            $itemWordsList[$item] = $this->createItemWords($item);
        }

        // Act
        $query = 'Leonard';
        $queryWords = $this->service->process($query);

        $results = [];
        foreach ($itemWordsList as $item => $itemWords) {
            $score = $this->service->computeScore($queryWords, $itemWords);
            if ($score !== null) {
                $results[] = array_merge(['name' => $item], $score);
            }
        }

        $this->service->sortResults($results);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
        $this->assertEquals('Leonardo DiCaprio', $results[1]['name']);
    }

    public function test_search_with_typo(): void
    {
        // Arrange
        $itemWords = $this->createItemWords('Leonard Cohen');
        $queryWords = $this->service->process('Leonerd Coen');

        // Act
        $score = $this->service->computeScore($queryWords, $itemWords);

        // Assert
        $this->assertNotNull($score);
        $this->assertGreaterThan(0, $score['percentage']);
        $this->assertLessThan(100, $score['percentage']);
    }

    public function test_cache_usage_between_queries(): void
    {
        // Arrange
        $query = 'John Doe';

        // Act
        $this->service->process($query);

        // Assert
        $cacheKey = 'ngram.grams.john';
        $cachedValue = $this->cache->get($cacheKey);
        $this->assertNotNull($cachedValue);

        // Second traitement devrait utiliser le cache
        $result = $this->service->process($query);
        $this->assertIsArray($result);
    }

    /**
     * Helper method to create item words
     */
    private function createItemWords(string $item): array
    {
        $processedQuery = $this->service->process($item);
        $itemWords = [];

        foreach ($processedQuery as $wordData) {
            $normalized = $wordData['normalized'];
            $itemWords[] = [
                'original' => $normalized,
                'normalized' => $normalized,
                'max_score' => $this->createMockMaxScore($normalized),
                'ngrams' => $wordData['ngrams'],
            ];
        }

        return $itemWords;
    }

    private function createMockMaxScore(string $word): float
    {
        $length = strlen($word);
        if ($length === 0) return 0;
        if ($length === 1) return 0;
        if ($length === 2) return 2.5;
        if ($length === 3) return 9.0;
        return 15.5;
    }
}

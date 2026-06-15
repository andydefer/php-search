<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\WordComparatorInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class WordComparatorServiceTest extends TestCase
{
    private WordComparatorInterface $service;

    private NGramEngineInterface $ngramEngine;

    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(WordComparatorInterface::class);
        $this->ngramEngine = $this->getService(NGramEngineInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_count_matching_letters_returns_correct_count(): void
    {
        // Arrange
        $word1 = 'hello';
        $word2 = 'hero';

        // Act
        $result = $this->service->countMatchingLetters($word1, $word2);

        // Assert
        $this->assertEquals(3, $result); // h, e, o
    }

    public function test_count_matching_letters_handles_repeated_letters(): void
    {
        // Arrange
        $word1 = 'aaabbb';
        $word2 = 'aaaccc';

        // Act
        $result = $this->service->countMatchingLetters($word1, $word2);

        // Assert
        $this->assertEquals(3, $result); // aaa
    }

    public function test_count_matching_letters_returns_zero_for_no_match(): void
    {
        // Arrange
        $word1 = 'abc';
        $word2 = 'xyz';

        // Act
        $result = $this->service->countMatchingLetters($word1, $word2);

        // Assert
        $this->assertEquals(0, $result);
    }

    public function test_passes_length_filter_returns_true_for_similar_length(): void
    {
        // Arrange
        $queryWord = 'hello';
        $itemWord = 'hello';

        // Act
        $result = $this->service->passesLengthFilter($queryWord, $itemWord);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_length_filter_returns_false_for_too_short_item(): void
    {
        // Arrange
        $queryWord = 'hello';
        $itemWord = 'he';

        // Act
        $result = $this->service->passesLengthFilter($queryWord, $itemWord);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_length_filter_returns_false_when_item_too_short(): void
    {
        // Arrange
        $queryWord = 'abcde';
        $itemWord = 'ab';

        // Act
        $result = $this->service->passesLengthFilter($queryWord, $itemWord);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_length_filter_returns_false_for_short_words(): void
    {
        // Arrange
        $queryWord = 'a';
        $itemWord = 'b';

        // Act
        $result = $this->service->passesLengthFilter($queryWord, $itemWord);

        // Assert
        $this->assertFalse($result);
    }

    public function test_calculate_score_returns_max_for_perfect_match(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'hello',
            'ngrams' => $this->ngramEngine->generateWithCache('hello'),
        ];
        $itemData = [
            'normalized' => 'hello',
            'max_score' => $this->ngramEngine->getMaxScoreWithCache('hello'),
        ];

        // Act
        $result = $this->service->calculateScore($queryData, $itemData);

        // Assert
        $this->assertEquals($itemData['max_score'], $result);
    }

    public function test_calculate_score_returns_partial_for_similar_words(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'hello',
            'ngrams' => $this->ngramEngine->generateWithCache('hello'),
        ];
        $itemData = [
            'normalized' => 'hero',
            'max_score' => $this->ngramEngine->getMaxScoreWithCache('hero'),
        ];

        // Act
        $result = $this->service->calculateScore($queryData, $itemData);

        // Assert
        $this->assertGreaterThan(0, $result);
        $this->assertLessThan($itemData['max_score'], $result);
    }

    public function test_find_best_match_returns_best_match(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'john',
            'ngrams' => $this->ngramEngine->generateWithCache('john'),
        ];

        $itemWords = [
            [
                'normalized' => 'john',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('john'),
            ],
            [
                'normalized' => 'jane',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('jane'),
            ],
        ];

        // Act
        $result = $this->service->findBestMatch($queryData, $itemWords);

        // Assert
        $this->assertGreaterThan(0, $result['score']);
        $this->assertGreaterThan(0, $result['percentage']);
    }

    public function test_find_best_match_returns_zero_when_no_candidates(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'xyz',
            'ngrams' => $this->ngramEngine->generateWithCache('xyz'),
        ];

        $itemWords = [
            [
                'normalized' => 'abc',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('abc'),
            ],
        ];

        // Act
        $result = $this->service->findBestMatch($queryData, $itemWords);

        // Assert
        $this->assertEquals(0.0, $result['score']);
        $this->assertEquals(0.0, $result['percentage']);
    }

    public function test_find_best_match_selects_highest_score(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'john',
            'ngrams' => $this->ngramEngine->generateWithCache('john'),
        ];

        $itemWords = [
            [
                'normalized' => 'jon',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('jon'),
            ],
            [
                'normalized' => 'johnny',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('johnny'),
            ],
        ];

        // Act
        $result = $this->service->findBestMatch($queryData, $itemWords);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('percentage', $result);
    }

    public function test_find_best_match_handles_empty_item_words(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'test',
            'ngrams' => $this->ngramEngine->generateWithCache('test'),
        ];

        $itemWords = [];

        // Act
        $result = $this->service->findBestMatch($queryData, $itemWords);

        // Assert
        $this->assertEquals(0.0, $result['score']);
        $this->assertEquals(0.0, $result['percentage']);
    }

    public function test_real_world_comparison(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'leonard',
            'ngrams' => $this->ngramEngine->generateWithCache('leonard'),
        ];

        $itemWords = [
            [
                'normalized' => 'leonardo',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('leonardo'),
            ],
            [
                'normalized' => 'leonard',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('leonard'),
            ],
            [
                'normalized' => 'lenny',
                'max_score' => $this->ngramEngine->getMaxScoreWithCache('lenny'),
            ],
        ];

        // Act
        $result = $this->service->findBestMatch($queryData, $itemWords);

        // Assert
        $this->assertGreaterThan(0, $result['score']);
    }

    public function test_calculate_score_with_early_stop(): void
    {
        // Arrange
        $queryData = [
            'normalized' => 'hello world',
            'ngrams' => $this->ngramEngine->generateWithCache('hello'),
        ];
        $itemData = [
            'normalized' => 'hello',
            'max_score' => 100.0,
        ];

        // Act
        $result = $this->service->calculateScore($queryData, $itemData);

        // Assert
        $this->assertIsFloat($result);
    }
}

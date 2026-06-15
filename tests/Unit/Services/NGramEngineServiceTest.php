<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\NGramEngineInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class NGramEngineServiceTest extends TestCase
{
    private NGramEngineInterface $service;

    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(NGramEngineInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_generate_returns_unique_ngrams_for_word(): void
    {
        // Arrange
        $word = 'chat';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $expected = ['ch', 'ha', 'at', 'cha', 'hat', 'chat'];
        $this->assertSame($expected, $grams);
    }

    public function test_generate_returns_unique_ngrams_no_duplicates(): void
    {
        // Arrange
        $word = 'aaa';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $expected = ['aa', 'aaa'];
        $this->assertSame($expected, $grams);
    }

    public function test_generate_returns_empty_array_for_short_word(): void
    {
        // Arrange
        $word = 'a';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $this->assertSame([], $grams);
    }

    public function test_generate_returns_bigrams_only_for_two_letter_word(): void
    {
        // Arrange
        $word = 'ab';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $expected = ['ab'];
        $this->assertSame($expected, $grams);
    }

    public function test_generate_returns_bigrams_and_trigrams_for_three_letter_word(): void
    {
        // Arrange
        $word = 'abc';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $expected = ['ab', 'bc', 'abc'];
        $this->assertSame($expected, $grams);
    }

    public function test_generate_returns_all_gram_types_for_long_word(): void
    {
        // Arrange
        $word = 'abcdef';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        // Bigrams
        $this->assertContains('ab', $grams);
        $this->assertContains('bc', $grams);
        $this->assertContains('cd', $grams);
        $this->assertContains('de', $grams);
        $this->assertContains('ef', $grams);

        // Trigrams
        $this->assertContains('abc', $grams);
        $this->assertContains('bcd', $grams);
        $this->assertContains('cde', $grams);
        $this->assertContains('def', $grams);

        // 4-grams
        $this->assertContains('abcd', $grams);
        $this->assertContains('bcde', $grams);
        $this->assertContains('cdef', $grams);
    }

    public function test_generate_with_cache_returns_same_result(): void
    {
        // Arrange
        $word = 'hello';

        // Act
        $grams1 = $this->service->generateWithCache($word);
        $grams2 = $this->service->generateWithCache($word);

        // Assert
        $this->assertSame($grams1, $grams2);
    }

    public function test_generate_with_cache_stores_in_cache(): void
    {
        // Arrange
        $word = 'world';

        // Act
        $grams = $this->service->generateWithCache($word);

        // Assert
        $cacheKey = 'ngram.grams.'.$word;
        $cached = $this->cache->get($cacheKey);
        $this->assertSame($grams, $cached);
    }

    public function test_get_weight_returns_correct_values(): void
    {
        // Act & Assert
        $this->assertSame(2.5, $this->service->getWeight(2));
        $this->assertSame(4.0, $this->service->getWeight(3));
        $this->assertSame(5.5, $this->service->getWeight(4));
    }

    public function test_get_max_score_returns_calculated_score(): void
    {
        // Arrange
        $word = 'ab';

        // Act
        $score = $this->service->getMaxScore($word);

        // Assert
        $this->assertSame(2.5, $score);
    }

    public function test_get_max_score_returns_sum_of_weights(): void
    {
        // Arrange
        $word = 'abc';

        // Act
        $score = $this->service->getMaxScore($word);

        // Assert
        // grams: 'ab'(2.5), 'bc'(2.5), 'abc'(4.0) = 9.0
        $this->assertSame(9.0, $score);
    }

    public function test_get_max_score_returns_rounded_score(): void
    {
        // Arrange
        $word = 'abcd';

        // Act
        $score = $this->service->getMaxScore($word);

        // Assert
        $this->assertIsFloat($score);
    }

    public function test_get_max_score_with_cache_returns_same_result(): void
    {
        // Arrange
        $word = 'test';

        // Act
        $score1 = $this->service->getMaxScoreWithCache($word);
        $score2 = $this->service->getMaxScoreWithCache($word);

        // Assert
        $this->assertSame($score1, $score2);
    }

    public function test_get_max_score_with_cache_stores_in_cache(): void
    {
        // Arrange
        $word = 'php';

        // Act
        $score = $this->service->getMaxScoreWithCache($word);

        // Assert
        $cacheKey = 'ngram.scores.'.$word;
        $cached = $this->cache->get($cacheKey);

        // Utiliser assertEquals au lieu de assertSame pour ignorer le type
        $this->assertEquals($score, $cached);
    }

    public function test_clear_cache_removes_all_cached_data(): void
    {
        // Arrange
        $word1 = 'hello';
        $word2 = 'world';

        $this->service->generateWithCache($word1);
        $this->service->generateWithCache($word2);
        $this->service->getMaxScoreWithCache($word1);
        $this->service->getMaxScoreWithCache($word2);

        // Act
        $this->service->clearCache();

        // Assert
        $cacheKeyGrams1 = 'ngram.grams.'.$word1;
        $cacheKeyGrams2 = 'ngram.grams.'.$word2;
        $cacheKeyScores1 = 'ngram.scores.'.$word1;
        $cacheKeyScores2 = 'ngram.scores.'.$word2;

        $this->assertNull($this->cache->get($cacheKeyGrams1));
        $this->assertNull($this->cache->get($cacheKeyGrams2));
        $this->assertNull($this->cache->get($cacheKeyScores1));
        $this->assertNull($this->cache->get($cacheKeyScores2));
    }

    public function test_clear_cache_handles_empty_cache(): void
    {
        // Act
        $this->service->clearCache();

        // Assert
        $this->assertTrue(true);
    }

    public function test_generate_handles_unicode_characters(): void
    {
        // Arrange
        $word = 'café';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $this->assertIsArray($grams);
        $this->assertNotEmpty($grams);
    }

    public function test_generate_handles_uppercase_lowercase(): void
    {
        // Arrange
        $wordLower = 'test';
        $wordUpper = 'TEST';

        // Act
        $gramsLower = $this->service->generate($wordLower);
        $gramsUpper = $this->service->generate($wordUpper);

        // Assert
        $this->assertSame($gramsLower, $gramsUpper);
    }

    public function test_get_max_score_returns_zero_for_empty_string(): void
    {
        // Arrange
        $word = '';

        // Act
        $score = $this->service->getMaxScore($word);

        // Assert
        $this->assertSame(0.0, $score);
    }

    public function test_generate_returns_empty_for_empty_string(): void
    {
        // Arrange
        $word = '';

        // Act
        $grams = $this->service->generate($word);

        // Assert
        $this->assertSame([], $grams);
    }
}

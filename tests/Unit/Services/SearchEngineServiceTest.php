<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\SearchEngineInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class SearchEngineServiceTest extends TestCase
{
    private SearchEngineInterface $engine;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->getService(SearchEngineInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_set_data_stores_dataset(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];

        // Act
        $this->engine->setData($data);

        // Assert
        $this->assertSame($data, $this->engine->getData());
    }

    public function test_get_data_returns_empty_array_when_no_data(): void
    {
        // Act
        $result = $this->engine->getData();

        // Assert
        $this->assertSame([], $result);
    }

    public function test_search_returns_results_for_exact_match(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('John Doe', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('percentage', $results[0]);
    }

    public function test_search_returns_results_for_partial_match(): void
    {
        // Arrange
        $data = ['Leonard Cohen', 'Leonardo DiCaprio', 'Marie Curie'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Leonard', 5);

        // Assert - Vérifier que les résultats contenant 'Leonard' sont bien les premiers
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(2, count($results));
        $this->assertStringContainsStringIgnoringCase('leonard', $results[0]['name']);
        $this->assertStringContainsStringIgnoringCase('leonard', $results[1]['name']);
    }

    public function test_search_respects_limit(): void
    {
        // Arrange
        $data = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Leonard Nimoy',
            'Leonard Bernstein',
            'Leonard Mlodinow'
        ];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_returns_empty_array_when_no_match(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('XYZ', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_handles_empty_query(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_handles_accented_characters(): void
    {
        // Arrange
        $data = ['Léonard de Vinci', 'Leonard Cohen'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Leonard', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_search_handles_typos(): void
    {
        // Arrange
        $data = ['Leonard Cohen'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Leonerd Coen', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
        $this->assertLessThan(100, $results[0]['percentage']);
        $this->assertGreaterThan(0, $results[0]['percentage']);
    }

    public function test_search_returns_sorted_by_relevance(): void
    {
        // Arrange
        $data = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Lenny Kravitz'
        ];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Leonard', 5);

        // Assert
        $this->assertGreaterThanOrEqual($results[0]['percentage'], $results[1]['percentage']);
    }

    public function test_clear_cache_removes_all_cached_data(): void
    {
        // Arrange
        $data = ['John Doe'];
        $this->engine->setData($data);
        $this->engine->search('John', 5);

        // Act
        $this->engine->clearCache();

        // Récupérer un nouveau moteur avec le même container
        $newEngine = $this->getService(SearchEngineInterface::class);

        // Assert - Les données devraient être vides car le cache a été nettoyé
        $this->assertSame([], $newEngine->getData());
    }

    public function test_set_data_overwrites_previous_data(): void
    {
        // Arrange
        $firstData = ['John Doe'];
        $secondData = ['Jane Smith'];

        // Act
        $this->engine->setData($firstData);
        $this->engine->setData($secondData);

        // Assert
        $this->assertSame($secondData, $this->engine->getData());

        $results = $this->engine->search('Jane', 5);
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);
    }

    public function test_search_handles_special_characters_in_query(): void
    {
        // Arrange
        $data = ['Jean-Luc Mélenchon', 'Jean Luc Godard'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('Jean-Luc Melenchon', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_search_handles_multiple_words_query(): void
    {
        // Arrange
        $data = [
            'John Fitzgerald Kennedy',
            'John Lennon',
            'Johnny Cash'
        ];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('John Kennedy', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_search_with_limit_zero_returns_empty(): void
    {
        // Arrange
        $data = ['John Doe'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('John', 0);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_returns_percentage_scores(): void
    {
        // Arrange
        $data = ['John Doe'];
        $this->engine->setData($data);

        // Act
        $results = $this->engine->search('John', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertArrayHasKey('percentage', $results[0]);
        $this->assertIsFloat($results[0]['percentage']);
        $this->assertGreaterThanOrEqual(0, $results[0]['percentage']);
        $this->assertLessThanOrEqual(100, $results[0]['percentage']);
    }

    public function test_real_world_scenario_artists_search(): void
    {
        // Arrange
        $artists = [
            'Leonardo DiCaprio',
            'Leonard Cohen',
            'Leonardo da Vinci',
            'Leonard Nimoy',
            'Leonard Bernstein',
            'Leonard Mlodinow',
            'Léonard de Vinci',
            'Léonard Gautier',
            'Marie Curie',
            'Albert Einstein'
        ];
        $this->engine->setData($artists);

        // Act
        $results = $this->engine->search('Leonard', 5);

        // Assert
        $this->assertCount(5, $results);
        foreach ($results as $result) {
            $this->assertStringContainsStringIgnoringCase('leonard', $result['name']);
        }
    }

    public function test_real_world_scenario_with_typo(): void
    {
        // Arrange
        $artists = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Léonard de Vinci'
        ];
        $this->engine->setData($artists);

        // Act
        $results = $this->engine->search('Lenard', 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function test_chaining_set_data_returns_self(): void
    {
        // Act
        $result = $this->engine->setData(['John Doe']);

        // Assert
        $this->assertSame($this->engine, $result);
    }
}

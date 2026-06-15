<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\FuzzySearchInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class FuzzySearchServiceTest extends TestCase
{
    private FuzzySearchInterface $fuzzySearch;
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fuzzySearch = $this->getService(FuzzySearchInterface::class);
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_data_');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function test_search_from_array_returns_results(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $query = 'John';

        // Act
        $results = $this->fuzzySearch->searchFromArray($data, $query, 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function test_search_from_array_returns_empty_for_no_match(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $query = 'XYZ';

        // Act
        $results = $this->fuzzySearch->searchFromArray($data, $query, 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_from_file_returns_results(): void
    {
        // Arrange
        $data = ['Leonard Cohen', 'Leonardo DiCaprio'];
        file_put_contents($this->tempFile, json_encode($data));
        $query = 'Leonard';

        // Act
        $results = $this->fuzzySearch->searchFromFile($this->tempFile, $query, 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    public function test_search_from_file_throws_exception_when_file_not_found(): void
    {
        // Arrange
        $invalidFile = '/nonexistent/file.json';

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("JSON file not found: {$invalidFile}");

        // Act
        $this->fuzzySearch->searchFromFile($invalidFile, 'test');
    }

    public function test_search_from_file_throws_exception_for_invalid_json(): void
    {
        // Arrange
        file_put_contents($this->tempFile, 'invalid json');

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid JSON format. Expected array of strings.");

        // Act
        $this->fuzzySearch->searchFromFile($this->tempFile, 'test');
    }

    public function test_search_from_file_throws_exception_for_non_array_json(): void
    {
        // Arrange
        file_put_contents($this->tempFile, json_encode('not an array'));

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid JSON format. Expected array of strings.");

        // Act
        $this->fuzzySearch->searchFromFile($this->tempFile, 'test');
    }

    public function test_search_from_file_throws_exception_for_non_string_items(): void
    {
        // Arrange
        $data = ['string', 123, 'another string'];
        file_put_contents($this->tempFile, json_encode($data));

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("JSON must contain only strings. Found invalid type.");

        // Act
        $this->fuzzySearch->searchFromFile($this->tempFile, 'test');
    }

    public function test_format_results_returns_formatted_string(): void
    {
        // Arrange
        $results = [
            [
                'name' => 'John Doe',
                'score' => 45.5,
                'max_possible' => 56.8,
                'percentage' => 80.42
            ]
        ];
        $query = 'John';

        // Act
        $output = $this->fuzzySearch->formatResults($results, $query);

        // Assert
        $this->assertStringContainsString("Top 1 results for 'John':", $output);
        $this->assertStringContainsString("1. John Doe", $output);
        $this->assertStringContainsString("score: 45.5", $output);
        $this->assertStringContainsString("max: 56.8", $output);
        $this->assertStringContainsString("Relevance: 80.42%", $output);
    }

    public function test_format_results_handles_max_possible_flag(): void
    {
        // Arrange
        $results = [
            [
                'name' => 'Perfect Match',
                'score' => 100,
                'max_possible' => 100,
                'percentage' => 100
            ]
        ];
        $query = 'Perfect';

        // Act
        $output = $this->fuzzySearch->formatResults($results, $query);

        // Assert
        $this->assertStringContainsString('[MAX POSSIBLE]', $output);
    }

    public function test_format_results_returns_no_results_message(): void
    {
        // Arrange
        $results = [];
        $query = 'Nonexistent';

        // Act
        $output = $this->fuzzySearch->formatResults($results, $query);

        // Assert
        $this->assertEquals("No results found for 'Nonexistent'.\n", $output);
    }

    public function test_format_results_handles_multiple_results(): void
    {
        // Arrange
        $results = [
            [
                'name' => 'First Result',
                'score' => 90,
                'max_possible' => 100,
                'percentage' => 90
            ],
            [
                'name' => 'Second Result',
                'score' => 80,
                'max_possible' => 100,
                'percentage' => 80
            ],
            [
                'name' => 'Third Result',
                'score' => 70,
                'max_possible' => 100,
                'percentage' => 70
            ]
        ];
        $query = 'test';

        // Act
        $output = $this->fuzzySearch->formatResults($results, $query);

        // Assert
        $this->assertStringContainsString("Top 3 results for 'test':", $output);
        $this->assertStringContainsString("1. First Result", $output);
        $this->assertStringContainsString("2. Second Result", $output);
        $this->assertStringContainsString("3. Third Result", $output);
    }

    public function test_real_world_scenario_from_array(): void
    {
        // Arrange
        $artists = [
            'Leonardo DiCaprio',
            'Leonard Cohen',
            'Leonardo da Vinci',
            'Leonard Nimoy',
            'Leonard Bernstein',
            'Léonard de Vinci',
            'Marie Curie',
            'Albert Einstein'
        ];

        // Act
        $results = $this->fuzzySearch->searchFromArray($artists, 'Leonard', 5);

        // Assert
        $this->assertCount(5, $results);
        foreach ($results as $result) {
            $this->assertStringContainsStringIgnoringCase('leonard', $result['name']);
        }
    }

    public function test_real_world_scenario_from_file(): void
    {
        // Arrange
        $artists = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Albert Einstein'
        ];
        file_put_contents($this->tempFile, json_encode($artists));

        // Act
        $results = $this->fuzzySearch->searchFromFile($this->tempFile, 'Leonard', 5);

        // Assert - Vérifier que les deux résultats contiennent 'Leonard' (ordre peut varier)
        $this->assertCount(2, $results);
        $this->assertStringContainsStringIgnoringCase('leonard', $results[0]['name']);
        $this->assertStringContainsStringIgnoringCase('leonard', $results[1]['name']);
    }

    public function test_search_with_typo_from_array(): void
    {
        // Arrange
        $data = ['Leonard Cohen'];
        $query = 'Leonerd';

        // Act
        $results = $this->fuzzySearch->searchFromArray($data, $query, 5);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
        $this->assertLessThan(100, $results[0]['percentage']);
        $this->assertGreaterThan(0, $results[0]['percentage']);
    }

    public function test_search_respects_limit_from_array(): void
    {
        // Arrange
        $data = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Leonard Nimoy',
            'Leonard Bernstein',
            'Leonard Mlodinow'
        ];

        // Act
        $results = $this->fuzzySearch->searchFromArray($data, 'Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_from_file_with_special_characters(): void
    {
        // Arrange
        $data = ['Jean-Luc Mélenchon', 'Jean Luc Godard'];
        file_put_contents($this->tempFile, json_encode($data));
        $query = 'Jean-Luc Melenchon';

        // Act
        $results = $this->fuzzySearch->searchFromFile($this->tempFile, $query, 5);

        // Assert
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }
}

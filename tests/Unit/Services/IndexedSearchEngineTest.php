<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\IndexedSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Services\IndexedSearchEngine;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class IndexedSearchEngineTest extends TestCase
{
    private IndexedSearchInterface $engine;

    private FileSystemService $fileSystem;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/indexed_search_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        $queryProcessor = $this->getService(QueryProcessorInterface::class);
        $preFilter = $this->getService(PreFilterInterface::class);

        $this->engine = new IndexedSearchEngine($queryProcessor, $preFilter, $this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createJsonFile(string $filename, array $data): string
    {
        $filePath = $this->tempDir.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($filePath, json_encode($data));

        return $filePath;
    }

    private function createJsonlFile(string $filename, array $lines): string
    {
        $filePath = $this->tempDir.DIRECTORY_SEPARATOR.$filename;
        $content = '';
        foreach ($lines as $line) {
            $content .= json_encode($line)."\n";
        }
        file_put_contents($filePath, $content);

        return $filePath;
    }

    public function test_index_json_file(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith', 'Leonard Cohen'];
        $jsonFile = $this->createJsonFile('data.json', $data);

        // Act
        $count = $this->engine->index($jsonFile);

        // Assert
        $this->assertEquals(3, $count);

        $stats = $this->engine->getIndexStats();
        $this->assertTrue($stats['exists']);
        $this->assertEquals(3, $stats['total_items']);
    }

    public function test_index_jsonl_directory(): void
    {
        // Arrange
        $this->createJsonlFile('file1.jsonl', [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
        ]);
        $this->createJsonlFile('file2.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        // Act
        $count = $this->engine->index($this->tempDir);

        // Assert
        $this->assertEquals(4, $count);
    }

    public function test_search_returns_results_from_index(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith', 'Leonard Cohen'];
        $jsonFile = $this->createJsonFile('data.json', $data);
        $this->engine->index($jsonFile);

        // Act
        $results = $this->engine->search('John', 5);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function test_search_respects_limit(): void
    {
        // Arrange
        $data = [
            'Leonard Cohen',
            'Leonardo DiCaprio',
            'Leonard Nimoy',
            'Leonard Bernstein',
            'Leonard Mlodinow',
        ];
        $jsonFile = $this->createJsonFile('data.json', $data);
        $this->engine->index($jsonFile);

        // Act
        $results = $this->engine->search('Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $jsonFile = $this->createJsonFile('data.json', $data);
        $this->engine->index($jsonFile);

        // Act
        $results = $this->engine->search('XYZ', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_get_index_stats_when_index_not_exists(): void
    {
        // Act
        $stats = $this->engine->getIndexStats();

        // Assert
        $this->assertFalse($stats['exists']);
        $this->assertEquals(0, $stats['total_items']);
        $this->assertStringContainsString('.php_search_index', $stats['index_path']);
    }

    public function test_index_overwrites_previous_index(): void
    {
        // Arrange
        $firstData = ['John Doe'];
        $secondData = ['Jane Smith'];

        $jsonFile = $this->createJsonFile('data.json', $firstData);
        $this->engine->index($jsonFile);

        // Act
        file_put_contents($jsonFile, json_encode($secondData));
        $count = $this->engine->index($jsonFile);

        // Assert
        $this->assertEquals(1, $count);

        $results = $this->engine->search('Jane', 5);
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);
    }

    public function test_index_jsonl_with_nested_fields(): void
    {
        // Arrange
        $this->createJsonlFile('nested.jsonl', [
            [
                'user' => [
                    'name' => 'John Doe',
                    'profile' => [
                        'nickname' => 'johnny',
                    ],
                ],
            ],
            [
                'user' => [
                    'name' => 'Jane Smith',
                    'profile' => [
                        'nickname' => 'janie',
                    ],
                ],
            ],
        ]);

        // Act
        $count = $this->engine->index($this->tempDir);

        // Assert
        $this->assertEquals(4, $count); // 2 names + 2 nicknames
    }

    public function test_index_jsonl_with_array_fields(): void
    {
        // Arrange
        $this->createJsonlFile('array.jsonl', [
            ['name' => 'John Doe', 'songs' => ['Hallelujah', 'Suzanne']],
            ['name' => 'Jane Smith', 'songs' => ['Yesterday', 'Hey Jude']],
        ]);

        // Act
        $count = $this->engine->index($this->tempDir);

        // Assert
        $this->assertEquals(6, $count); // 2 names + 4 songs
    }

    public function test_search_with_typo_returns_lower_score(): void
    {
        // Arrange
        $data = ['Leonard Cohen', 'Lenny Kravitz'];
        $jsonFile = $this->createJsonFile('data.json', $data);
        $this->engine->index($jsonFile);

        // Act
        $results = $this->engine->search('Leonerd', 5);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
        // Le score devrait être inférieur à 100 car "Leonerd" n'est pas identique à "Leonard"
        // Mais comme "Leonerd" est proche, le score peut être élevé
        // On vérifie juste qu'il n'est pas à 100%
        $this->assertLessThan(100, $results[0]['percentage']);
        $this->assertGreaterThan(0, $results[0]['percentage']);
    }
}

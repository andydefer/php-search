<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Services\JsonlSearchEngine;
use AndyDefer\PhpSearch\Strategies\SearchPathStrategy;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class JsonlSearchEngineTest extends TestCase
{
    private JsonlSearchEngine $engine;

    private FileSystemService $fileSystem;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/jsonl_search_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        $strategy = new SearchPathStrategy($this->tempDir);
        $context = new JsonlContext;
        $jsonlService = new JsonlService($strategy, $this->fileSystem, $context);

        $queryProcessor = $this->getService(QueryProcessorInterface::class);
        $preFilter = $this->getService(PreFilterInterface::class);

        $this->engine = new JsonlSearchEngine(
            $jsonlService,
            $this->fileSystem,
            $queryProcessor,
            $preFilter
        );
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

    public function test_search_returns_results_from_multiple_files(): void
    {

        // Arrange
        $this->createJsonlFile('file1.jsonl', [
            ['name' => 'Leonard Cohen', 'title' => 'Singer'],
            ['name' => 'Bob Dylan', 'title' => 'Singer'],
        ]);
        $this->createJsonlFile('file2.jsonl', [
            ['name' => 'Leonardo DiCaprio', 'title' => 'Actor'],
            ['name' => 'Marie Curie', 'title' => 'Scientist'],
        ]);

        // Act
        $results = $this->engine->search($this->tempDir, 'name|title', 'Leonard', 5);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($results));

        $names = array_column($results, 'data');
        $foundLeonardCohen = false;
        $foundLeonardoDiCaprio = false;

        foreach ($names as $data) {
            if (isset($data['name']) && $data['name'] === 'Leonard Cohen') {
                $foundLeonardCohen = true;
            }
            if (isset($data['name']) && $data['name'] === 'Leonardo DiCaprio') {
                $foundLeonardoDiCaprio = true;
            }
        }

        $this->assertTrue($foundLeonardCohen, 'Leonard Cohen should be found');
        $this->assertTrue($foundLeonardoDiCaprio, 'Leonardo DiCaprio should be found');
    }

    public function test_search_returns_empty_array_when_directory_not_found(): void
    {

        // Act
        $results = $this->engine->search('/nonexistent/directory_'.uniqid(), 'name', 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_returns_empty_array_when_no_jsonl_files(): void
    {

        // Arrange - créer un dossier vide
        $emptyDir = $this->tempDir.'/empty';
        $this->fileSystem->makeDirectory($emptyDir, PermissionMode::WORLD_WRITABLE, true);

        // Act
        $results = $this->engine->search($emptyDir, 'name', 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_file_returns_results_for_single_file(): void
    {

        // Arrange
        $filePath = $this->createJsonlFile('test.jsonl', [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);

        // Act
        $results = $this->engine->searchFile($filePath, ['name'], 'John', 5);

        // Assert
        $this->assertCount(1, $results);
        $this->assertSame($filePath, $results[0]['file']);
        $this->assertSame(1, $results[0]['line']);
        $this->assertSame('John Doe', $results[0]['data']['name']);
    }

    public function test_search_file_returns_multiple_matches(): void
    {

        // Arrange
        $filePath = $this->createJsonlFile('test.jsonl', [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Johnny Cash', 'email' => 'johnny@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);

        // Act
        $results = $this->engine->searchFile($filePath, ['name'], 'John', 5);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_search_file_returns_empty_when_file_not_found(): void
    {

        // Act
        $results = $this->engine->searchFile('/nonexistent/file.jsonl', ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_with_nested_fields(): void
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
                'tags' => ['admin', 'user'],
            ],
            [
                'user' => [
                    'name' => 'Jane Smith',
                    'profile' => [
                        'nickname' => 'janie',
                    ],
                ],
                'tags' => ['user', 'premium'],
            ],
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'user.name|user.profile.nickname', 'john', 5);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_search_with_array_fields(): void
    {

        // Arrange
        $this->createJsonlFile('array.jsonl', [
            ['name' => 'John Doe', 'songs' => ['Hallelujah', 'Suzanne', 'Bird on a Wire']],
            ['name' => 'Jane Smith', 'songs' => ['Yesterday', 'Hey Jude']],
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'songs', 'Hallelujah', 5);

        // Assert
        $this->assertCount(1, $results);
    }

    public function test_search_respects_limit(): void
    {

        // Arrange
        $this->createJsonlFile('many.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Leonard Nimoy'],
            ['name' => 'Leonard Bernstein'],
            ['name' => 'Leonard Mlodinow'],
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'name', 'Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_with_multiple_fields(): void
    {

        // Arrange
        $this->createJsonlFile('multi.jsonl', [
            ['name' => 'Leonard Cohen', 'title' => 'Singer', 'country' => 'Canada'],
            ['name' => 'Bob Dylan', 'title' => 'Singer', 'country' => 'USA'],
            ['name' => 'Leonardo DiCaprio', 'title' => 'Actor', 'country' => 'USA'],
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'name|title', 'Leonard', 5);

        // Assert
        $this->assertCount(2, $results);
    }

    public function test_search_with_exact_match_returns_high_score(): void
    {

        // Arrange
        $this->createJsonlFile('exact.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Lenny Kravitz'],  // Ce nom a des lettres communes avec "Leonard Cohen"
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'name', 'Leonard Cohen', 5);

        // Assert - Vérifier que le premier résultat est exact et à 100%
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['data']['name']);
        $this->assertEquals(100, $results[0]['percentage']);
    }

    public function test_search_with_typo_returns_lower_score(): void
    {

        // Arrange
        $this->createJsonlFile('typo.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Lenny Kravitz'],
        ]);

        $files = glob($this->tempDir.'/*.jsonl');

        // Act
        $results = $this->engine->search($this->tempDir, 'name', 'Leonerd Coen', 5);

        // Assert - Vérifier que le premier résultat est Leonard Cohen avec un score < 100%
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['data']['name']);
        $this->assertLessThan(100, $results[0]['percentage']);
        $this->assertGreaterThan(0, $results[0]['percentage']);
    }

    public function test_search_in_subdirectories(): void
    {

        // Arrange
        $subDir = $this->tempDir.'/subdir';
        $this->fileSystem->makeDirectory($subDir, PermissionMode::WORLD_WRITABLE, true);

        $this->createJsonlFile('root.jsonl', [['name' => 'Root File']]);

        $filePath = $subDir.'/sub.jsonl';
        $content = json_encode(['name' => 'Subdirectory File'])."\n";
        file_put_contents($filePath, $content);

        // Act
        $results = $this->engine->search($this->tempDir, 'name', 'Subdirectory', 5);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($results));
    }
}

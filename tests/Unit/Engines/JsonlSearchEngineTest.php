<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Engines;

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Configs\EngineConfig;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Engines\JsonlSearchEngine;
use AndyDefer\PhpSearch\Engines\SearchEngine;
use AndyDefer\PhpSearch\Strategies\SearchPathStrategy;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class JsonlSearchEngineTest extends TestCase
{
    private JsonlSearchEngine $engine;

    private FileSystemService $fileSystem;

    private string $tempDir;

    private SearchEngine $searchEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/jsonl_search_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        // Créer JsonlSearchEngine pour les tests de lecture directe
        $strategy = new SearchPathStrategy($this->tempDir);
        $context = new JsonlContext;
        $jsonlService = new JsonlService($strategy, $this->fileSystem, $context);

        $queryProcessor = $this->getService(QueryProcessorInterface::class);
        $preFilter = $this->getService(PreFilterInterface::class);

        $this->engine = new JsonlSearchEngine(
            jsonlService: $jsonlService,
            fileSystem: $this->fileSystem,
            queryProcessor: $queryProcessor,
            preFilter: $preFilter,
        );

        // Créer SearchEngine pour générer de vrais index
        $engineConfig = new EngineConfig($this->tempDir);
        $this->searchEngine = new SearchEngine(
            queryProcessor: $queryProcessor,
            fileSystem: $this->fileSystem,
            config: $engineConfig,
            preFilter: $preFilter,
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

    private function createRealIndex(string $sourceFile): string
    {
        $this->searchEngine->index($sourceFile);
        $stats = $this->searchEngine->getIndexStats($sourceFile);

        return $stats['index_path'].DIRECTORY_SEPARATOR.'index.jsonl';
    }

    // ============================================================
    // Tests pour searchInDirectory()
    // ============================================================

    public function test_search_in_directory_returns_results_from_multiple_files(): void
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

        // Act - Utilisation d'un tableau pour les champs
        $results = $this->engine->searchInDirectory($this->tempDir, ['name', 'title'], 'Leonard', 5);

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

    public function test_search_in_directory_returns_empty_array_when_directory_not_found(): void
    {
        // Act
        $results = $this->engine->searchInDirectory('/nonexistent/directory_'.uniqid(), ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_in_directory_returns_empty_array_when_no_jsonl_files(): void
    {
        // Arrange
        $emptyDir = $this->tempDir.'/empty';
        $this->fileSystem->makeDirectory($emptyDir, PermissionMode::WORLD_WRITABLE, true);

        // Act
        $results = $this->engine->searchInDirectory($emptyDir, ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_in_directory_respects_limit(): void
    {
        // Arrange
        $this->createJsonlFile('many.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Leonard Nimoy'],
            ['name' => 'Leonard Bernstein'],
            ['name' => 'Leonard Mlodinow'],
        ]);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['name'], 'Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_in_directory_with_multiple_fields(): void
    {
        // Arrange
        $this->createJsonlFile('multi.jsonl', [
            ['name' => 'Leonard Cohen', 'title' => 'Singer', 'country' => 'Canada'],
            ['name' => 'Bob Dylan', 'title' => 'Singer', 'country' => 'USA'],
            ['name' => 'Leonardo DiCaprio', 'title' => 'Actor', 'country' => 'USA'],
        ]);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['name', 'title'], 'Leonard', 5);

        // Assert
        $this->assertCount(2, $results);
    }

    public function test_search_in_directory_with_nested_fields(): void
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

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['user.name', 'user.profile.nickname'], 'john', 5);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    public function test_search_in_directory_with_array_fields(): void
    {
        // Arrange
        $this->createJsonlFile('array.jsonl', [
            ['name' => 'John Doe', 'songs' => ['Hallelujah', 'Suzanne', 'Bird on a Wire']],
            ['name' => 'Jane Smith', 'songs' => ['Yesterday', 'Hey Jude']],
        ]);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['songs'], 'Hallelujah', 5);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['data']['name']);
    }

    public function test_search_in_directory_with_exact_match_returns_high_score(): void
    {
        // Arrange
        $this->createJsonlFile('exact.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Lenny Kravitz'],
        ]);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['name'], 'Leonard Cohen', 5);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['data']['name']);
        $this->assertEquals(100, $results[0]['percentage']);
    }

    public function test_search_in_directory_with_typo_returns_lower_score(): void
    {
        // Arrange
        $this->createJsonlFile('typo.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Lenny Kravitz'],
        ]);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['name'], 'Leonerd Coen', 5);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['data']['name']);
        $this->assertLessThan(100, $results[0]['percentage']);
        $this->assertGreaterThan(0, $results[0]['percentage']);
    }

    public function test_search_in_directory_with_subdirectories(): void
    {
        // Arrange
        $subDir = $this->tempDir.'/subdir';
        $this->fileSystem->makeDirectory($subDir, PermissionMode::WORLD_WRITABLE, true);

        $this->createJsonlFile('root.jsonl', [['name' => 'Root File']]);

        $filePath = $subDir.'/sub.jsonl';
        $content = json_encode(['name' => 'Subdirectory File'])."\n";
        file_put_contents($filePath, $content);

        // Act
        $results = $this->engine->searchInDirectory($this->tempDir, ['name'], 'Subdirectory', 5);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    // ============================================================
    // Tests pour searchInFile()
    // ============================================================

    public function test_search_in_file_returns_results_for_single_file(): void
    {
        // Arrange
        $filePath = $this->createJsonlFile('test.jsonl', [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);

        // Act
        $results = $this->engine->searchInFile($filePath, ['name'], 'John', 5);

        // Assert
        $this->assertCount(1, $results);
        $this->assertSame($filePath, $results[0]['file']);
        $this->assertSame(1, $results[0]['line']);
        $this->assertSame('John Doe', $results[0]['data']['name']);
    }

    public function test_search_in_file_returns_multiple_matches(): void
    {
        // Arrange
        $filePath = $this->createJsonlFile('test.jsonl', [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Johnny Cash', 'email' => 'johnny@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);

        // Act
        $results = $this->engine->searchInFile($filePath, ['name'], 'John', 5);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_search_in_file_returns_empty_when_file_not_found(): void
    {
        // Act
        $results = $this->engine->searchInFile('/nonexistent/file.jsonl', ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    // ============================================================
    // Tests pour searchInStream()
    // ============================================================

    public function test_search_in_stream_returns_results(): void
    {
        // Arrange
        $streamData = "{\"name\":\"John Doe\",\"email\":\"john@example.com\"}\n".
            "{\"name\":\"Jane Smith\",\"email\":\"jane@example.com\"}\n".
            "{\"name\":\"Johnny Cash\",\"email\":\"johnny@example.com\"}\n";

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $streamData);
        rewind($stream);

        // Act
        $results = $this->engine->searchInStream($stream, ['name'], 'John', 5);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['data']['name']);
        $this->assertEquals('Johnny Cash', $results[1]['data']['name']);

        fclose($stream);
    }

    public function test_search_in_stream_returns_empty_for_empty_stream(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+');

        // Act
        $results = $this->engine->searchInStream($stream, ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);

        fclose($stream);
    }

    public function test_search_in_stream_throws_exception_for_invalid_resource(): void
    {
        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le paramètre doit être une ressource de type stream');

        // Act
        $this->engine->searchInStream('not a stream', ['name'], 'test', 5);
    }

    // ============================================================
    // Tests pour searchInIterable()
    // ============================================================

    public function test_search_in_iterable_with_array(): void
    {
        // Arrange
        $data = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Johnny Cash', 'email' => 'johnny@example.com'],
        ];

        // Act
        $results = $this->engine->searchInIterable($data, ['name'], 'John', 5);

        // Assert
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['data']['name']);
        $this->assertEquals('Johnny Cash', $results[1]['data']['name']);
    }

    public function test_search_in_iterable_with_generator(): void
    {
        // Arrange
        $generator = function () {
            yield ['name' => 'John Doe'];
            yield ['name' => 'Jane Smith'];
            yield ['name' => 'Johnny Cash'];
        };

        // Act
        $results = $this->engine->searchInIterable($generator(), ['name'], 'John', 5);

        // Assert
        $this->assertCount(2, $results);
    }

    public function test_search_in_iterable_returns_empty_for_empty_array(): void
    {
        // Act
        $results = $this->engine->searchInIterable([], ['name'], 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    // ============================================================
    // Tests pour searchInIndex() avec VRAI INDEX
    // ============================================================

    public function test_search_in_index_returns_results_with_real_index(): void
    {
        // Arrange - Créer un fichier source JSONL
        $sourceFile = $this->tempDir.'/artists.jsonl';
        $content = json_encode(['name' => 'Leonard Cohen', 'id' => 1])."\n".
            json_encode(['name' => 'Bob Dylan', 'id' => 2])."\n".
            json_encode(['name' => 'Leonardo DiCaprio', 'id' => 3])."\n";
        file_put_contents($sourceFile, $content);

        // Créer un vrai index avec SearchEngine
        $indexPath = $this->createRealIndex($sourceFile);

        // Vérifier que l'index existe et contient des données
        $this->assertFileExists($indexPath);
        $indexContent = file_get_contents($indexPath);
        $this->assertNotEmpty($indexContent);
        $this->assertStringContainsString('leonard', strtolower($indexContent));

        // Act - Rechercher dans l'index
        $results = $this->engine->searchInIndex($indexPath, 'Leonard', 5);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
        $this->assertEquals(100, $results[0]['percentage']);
    }

    public function test_search_in_index_returns_multiple_results(): void
    {
        // Arrange - Créer un fichier source JSONL avec plusieurs entrées
        $sourceFile = $this->tempDir.'/data.jsonl';
        $content = json_encode(['name' => 'Leonard Cohen', 'type' => 'musician'])."\n".
            json_encode(['name' => 'Leonardo Da Vinci', 'type' => 'artist'])."\n".
            json_encode(['name' => 'Leonard Nimoy', 'type' => 'actor'])."\n";
        file_put_contents($sourceFile, $content);

        // Créer un vrai index
        $indexPath = $this->createRealIndex($sourceFile);

        // Act
        $results = $this->engine->searchInIndex($indexPath, 'Leonard', 10);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_search_in_index_throws_exception_when_index_not_found(): void
    {
        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fichier d\'index introuvable');

        // Act
        $this->engine->searchInIndex('/nonexistent/index.jsonl', 'test', 5);
    }

    public function test_search_in_index_with_empty_query_returns_empty(): void
    {
        // Arrange
        $sourceFile = $this->tempDir.'/artists.jsonl';
        $content = json_encode(['name' => 'Leonard Cohen'])."\n";
        file_put_contents($sourceFile, $content);
        $indexPath = $this->createRealIndex($sourceFile);

        // Act
        $results = $this->engine->searchInIndex($indexPath, '', 5);

        // Assert
        $this->assertSame([], $results);
    }
}

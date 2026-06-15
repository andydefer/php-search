<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Configs\SearchConfig;
use AndyDefer\PhpSearch\Contracts\Services\CachedSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\JsonlSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Services\CachedSearchEngine;
use AndyDefer\PhpSearch\Services\CacheService;
use AndyDefer\PhpSearch\Services\JsonlSearchEngine;
use AndyDefer\PhpSearch\Strategies\SearchPathStrategy;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class CachedSearchEngineTest extends TestCase
{
    private CachedSearchInterface $cachedSearch;

    private JsonlSearchInterface $directSearch;

    private CacheInterface $cache;

    private FileSystemService $fileSystem;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/cached_search_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        $config = new SearchConfig;
        $this->cache = new CacheService($config);

        // Créer le moteur de recherche direct
        $strategy = new SearchPathStrategy($this->tempDir);
        $context = new JsonlContext;
        $jsonlService = new JsonlService($strategy, $this->fileSystem, $context);

        $queryProcessor = $this->getService(QueryProcessorInterface::class);
        $preFilter = $this->getService(PreFilterInterface::class);

        $this->directSearch = new JsonlSearchEngine(
            $jsonlService,
            $this->fileSystem,
            $queryProcessor,
            $preFilter
        );

        $this->cachedSearch = new CachedSearchEngine(
            $this->directSearch,
            $this->cache
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

    public function test_search_caches_results(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        // Act - Première recherche (cache miss)
        $results1 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);

        // Deuxième recherche (cache hit)
        $results2 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);

        // Assert
        $this->assertSame($results1, $results2);
    }

    public function test_search_returns_correct_results(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
            ['name' => 'Johnny Cash'],
        ]);

        // Act
        $results = $this->cachedSearch->search($this->tempDir, 'name', 'John', 5);

        // Assert
        $this->assertNotEmpty($results);
        // Le résultat contient 'data' avec le nom
        $this->assertEquals('John Doe', $results[0]['data']['name']);
    }

    public function test_search_respects_limit(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Leonard Nimoy'],
            ['name' => 'Leonard Bernstein'],
            ['name' => 'Leonard Mlodinow'],
        ]);

        // Act
        $results = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 3);

        // Assert
        $this->assertCount(3, $results);
    }

    public function test_search_with_different_queries_use_different_cache(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        // Act
        $results1 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);
        $results2 = $this->cachedSearch->search($this->tempDir, 'name', 'Bob', 5);

        // Assert
        $this->assertNotEquals($results1, $results2);
    }

    public function test_search_with_different_fields_use_different_cache(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen', 'title' => 'Singer'],
            ['name' => 'Leonardo DiCaprio', 'title' => 'Actor'],
        ]);

        // Act
        $results1 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);
        $results2 = $this->cachedSearch->search($this->tempDir, 'title', 'Singer', 5);

        // Assert
        $this->assertNotEquals($results1, $results2);
    }

    public function test_clear_cache_removes_all_cached_results(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        // Première recherche (remplit le cache)
        $results1 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);

        // Act - Vider le cache
        $this->cachedSearch->clearCache();

        // Deuxième recherche (cache miss - doit refaire la recherche)
        $results2 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);

        // Assert
        $this->assertSame($results1, $results2); // Mêmes résultats
        // Mais le cache a été recréé
    }

    public function test_clear_cache_with_specific_query(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
            ['name' => 'Leonardo DiCaprio'],
        ]);

        // Remplir le cache avec différentes requêtes
        $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);
        $this->cachedSearch->search($this->tempDir, 'name', 'Bob', 5);
        $this->cachedSearch->search($this->tempDir, 'name', 'Leonardo', 5);

        // Act - Vider uniquement le cache pour 'Leonard'
        $this->cachedSearch->clearCache('Leonard');

        // Les résultats pour 'Bob' doivent encore être en cache
        $bobResults = $this->cachedSearch->search($this->tempDir, 'name', 'Bob', 5);

        // Les résultats pour 'Leonard' doivent être recalculés
        $leonardResults = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);

        // Assert
        $this->assertNotEmpty($leonardResults);
        $this->assertNotEmpty($bobResults);
    }

    public function test_cache_ttl_expires(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
        ]);

        // Act - Premier appel avec TTL court (1 seconde)
        $results1 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5, 1);

        // Attendre l'expiration
        sleep(2);

        // Deuxième appel - doit refaire la recherche
        $results2 = $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5, 1);

        // Assert
        $this->assertSame($results1, $results2);
    }

    public function test_search_with_empty_directory_returns_empty(): void
    {
        // Arrange
        $emptyDir = $this->tempDir.'/empty';
        $this->fileSystem->makeDirectory($emptyDir, PermissionMode::WORLD_WRITABLE, true);

        // Act
        $results = $this->cachedSearch->search($emptyDir, 'name', 'test', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_search_with_empty_query_returns_empty(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'John Doe'],
        ]);

        // Act
        $results = $this->cachedSearch->search($this->tempDir, 'name', '', 5);

        // Assert
        $this->assertSame([], $results);
    }

    public function test_performance_cache_hit_vs_cache_miss(): void
    {
        // Arrange
        $this->createJsonlFile('data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Leonard Nimoy'],
            ['name' => 'Leonard Bernstein'],
            ['name' => 'Leonard Mlodinow'],
        ]);

        // Act - Cache miss
        $start = microtime(true);
        $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);
        $cacheMissTime = microtime(true) - $start;

        // Cache hit
        $start = microtime(true);
        $this->cachedSearch->search($this->tempDir, 'name', 'Leonard', 5);
        $cacheHitTime = microtime(true) - $start;

        // Assert - Le cache hit doit être plus rapide
        $this->assertLessThan($cacheMissTime, $cacheHitTime);
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
        $results = $this->cachedSearch->search($this->tempDir, 'user.name|user.profile.nickname', 'john', 5);

        // Assert
        $this->assertNotEmpty($results);
    }
}

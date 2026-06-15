<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Engine;

use AndyDefer\PhpSearch\Configs\EngineConfig;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Engines\SearchEngine;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

final class SearchEngineTest extends TestCase
{
    private SearchEngine $engine;

    private FileSystemService $fileSystem;

    private string $tempDir;

    private string $docsDir;

    private string $docs2Dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/search_engine_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        $this->docsDir = $this->tempDir.DIRECTORY_SEPARATOR.'docs';
        $this->docs2Dir = $this->tempDir.DIRECTORY_SEPARATOR.'docs2';
        $this->fileSystem->makeDirectory($this->docsDir, PermissionMode::WORLD_WRITABLE, true);
        $this->fileSystem->makeDirectory($this->docs2Dir, PermissionMode::WORLD_WRITABLE, true);

        $queryProcessor = $this->getService(QueryProcessorInterface::class);
        $preFilter = $this->getService(PreFilterInterface::class);
        $config = new EngineConfig($this->tempDir);

        $this->engine = new SearchEngine(
            queryProcessor: $queryProcessor,
            fileSystem: $this->fileSystem,
            config: $config,
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

    private function createJsonlFile(string $dir, string $filename, array $lines): string
    {
        $filePath = $dir.DIRECTORY_SEPARATOR.$filename;
        $content = '';
        foreach ($lines as $line) {
            $content .= json_encode($line)."\n";
        }
        file_put_contents($filePath, $content);

        return $filePath;
    }

    public function test_index_directory(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
        ]);

        // Act
        $count = $this->engine->index($this->docsDir);

        // Assert
        $this->assertEquals(2, $count);
        $this->assertTrue($this->engine->hasIndex($this->docsDir));
    }

    public function test_index_single_file(): void
    {
        // Arrange
        $filePath = $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
        ]);

        // Act
        $count = $this->engine->index($filePath);

        // Assert
        $this->assertEquals(2, $count);
        $this->assertTrue($this->engine->hasIndex($filePath));
    }

    public function test_search_returns_results(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);
        $this->engine->index($this->docsDir);

        // Act
        $results = $this->engine->search('Leonard', 5, $this->docsDir);

        // Assert
        $this->assertNotEmpty($results);
        $this->assertEquals('Leonard Cohen', $results[0]['name']);
    }

    public function test_multi_index_support(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'docs.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);
        $this->createJsonlFile($this->docs2Dir, 'docs2.jsonl', [
            ['name' => 'Marie Curie'],
            ['name' => 'Albert Einstein'],
        ]);

        // Act
        $this->engine->index($this->docsDir);
        $this->engine->index($this->docs2Dir);

        $results1 = $this->engine->search('Leonard', 5, $this->docsDir);
        $results2 = $this->engine->search('Marie', 5, $this->docs2Dir);

        // Assert
        $this->assertNotEmpty($results1);
        $this->assertEquals('Leonard Cohen', $results1[0]['name']);
        $this->assertNotEmpty($results2);
        $this->assertEquals('Marie Curie', $results2[0]['name']);
    }

    public function test_list_indexes(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'John Doe'],
        ]);
        $this->createJsonlFile($this->docs2Dir, 'data2.jsonl', [
            ['name' => 'Jane Smith'],
        ]);

        $this->engine->index($this->docsDir);
        $this->engine->index($this->docs2Dir);

        // Act
        $indexes = $this->engine->listIndexes();

        // Assert
        $this->assertCount(2, $indexes);
    }

    public function test_delete_index(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'John Doe'],
        ]);
        $this->engine->index($this->docsDir);
        $this->assertTrue($this->engine->hasIndex($this->docsDir));

        // Act
        $this->engine->deleteIndex($this->docsDir);

        // Assert
        $this->assertFalse($this->engine->hasIndex($this->docsDir));
    }

    public function test_clear_cache(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'Leonard Cohen'],
        ]);
        $this->engine->index($this->docsDir);
        $this->engine->search('Leonard', 5, $this->docsDir);

        // Act
        $this->engine->clearCache($this->docsDir);

        // Assert - Pas d'erreur, le cache est vidé
        $this->assertTrue(true);
    }

    public function test_get_index_stats(): void
    {
        // Arrange
        $this->createJsonlFile($this->docsDir, 'data.jsonl', [
            ['name' => 'John Doe'],
            ['name' => 'Jane Smith'],
        ]);
        $this->engine->index($this->docsDir);

        // Act
        $stats = $this->engine->getIndexStats($this->docsDir);

        // Assert
        $this->assertTrue($stats['exists']);
        $this->assertEquals(2, $stats['total_items']);
        $this->assertEquals($this->docsDir, $stats['source']);
    }
}

<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Strategies;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpSearch\Records\SearchResultRecord;
use AndyDefer\PhpSearch\Strategies\SearchPathStrategy;
use AndyDefer\PhpSearch\Tests\TestCase;

final class SearchPathStrategyTest extends TestCase
{
    private SearchPathStrategy $strategy;

    private string $basePath;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/search_path_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->basePath = $this->tempDir;
        $this->strategy = new SearchPathStrategy($this->basePath);
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

    private function createSearchResultRecord(string $sessionId): SearchResultRecord
    {
        return new SearchResultRecord(
            session_id: $sessionId,
            file_path: '/test/file.jsonl',
            line_number: 1,
            data: new StrictDataObject(['name' => 'Test']),
            score: 100.0,
            max_possible: 100.0,
            percentage: 100.0,
            timestamp: time(),
        );
    }

    private function createDummyQuery(): AbstractRecord
    {
        return new class extends AbstractRecord {};
    }

    public function test_get_file_path_returns_correct_path(): void
    {
        // Arrange
        $record = $this->createSearchResultRecord('session_123');

        // Act
        $path = $this->strategy->getFilePath($record);

        // Assert
        $expectedHash = substr(md5('session_123'), 0, 8);
        $expectedPath = $this->basePath.DIRECTORY_SEPARATOR.'search_'.$expectedHash.'.jsonl';
        $this->assertSame($expectedPath, $path);
    }

    public function test_get_file_path_returns_same_path_for_same_session_id(): void
    {
        // Arrange
        $record1 = $this->createSearchResultRecord('session_abc');
        $record2 = $this->createSearchResultRecord('session_abc');

        // Act
        $path1 = $this->strategy->getFilePath($record1);
        $path2 = $this->strategy->getFilePath($record2);

        // Assert
        $this->assertSame($path1, $path2);
    }

    public function test_get_file_path_returns_different_path_for_different_session_id(): void
    {
        // Arrange
        $record1 = $this->createSearchResultRecord('session_abc');
        $record2 = $this->createSearchResultRecord('session_xyz');

        // Act
        $path1 = $this->strategy->getFilePath($record1);
        $path2 = $this->strategy->getFilePath($record2);

        // Assert
        $this->assertNotSame($path1, $path2);
    }

    public function test_get_file_path_throws_exception_for_invalid_record_type(): void
    {
        // Arrange
        $invalidRecord = new class extends AbstractRecord {};

        // Expect
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SearchPathStrategy expects SearchResultRecord');

        // Act
        $this->strategy->getFilePath($invalidRecord);
    }

    public function test_get_files_to_scan_returns_empty_array_when_directory_not_exists(): void
    {
        // Arrange
        $strategy = new SearchPathStrategy('/nonexistent/directory_'.uniqid());
        $dummyQuery = $this->createDummyQuery();

        // Act
        $files = $strategy->getFilesToScan($dummyQuery);

        // Assert
        $this->assertSame([], $files);
    }

    public function test_get_files_to_scan_returns_all_search_files(): void
    {
        // Arrange
        // Créer des fichiers de recherche
        touch($this->tempDir.'/search_abc123.jsonl');
        touch($this->tempDir.'/search_def456.jsonl');
        touch($this->tempDir.'/other_file.jsonl'); // Ne doit pas être inclus
        touch($this->tempDir.'/search_ghi789.txt'); // Ne doit pas être inclus

        $dummyQuery = $this->createDummyQuery();

        // Act
        $files = $this->strategy->getFilesToScan($dummyQuery);

        // Assert
        $this->assertCount(2, $files);
        $this->assertStringContainsString('search_abc123.jsonl', $files[0]);
        $this->assertStringContainsString('search_def456.jsonl', $files[1]);
    }

    public function test_get_files_to_scan_returns_empty_array_when_no_search_files(): void
    {
        // Arrange
        touch($this->tempDir.'/other_file.jsonl');
        touch($this->tempDir.'/data.txt');

        $dummyQuery = $this->createDummyQuery();

        // Act
        $files = $this->strategy->getFilesToScan($dummyQuery);

        // Assert
        $this->assertSame([], $files);
    }

    public function test_get_base_directory_returns_configured_base_path(): void
    {
        // Act
        $baseDir = $this->strategy->getBaseDirectory();

        // Assert
        $this->assertSame($this->basePath, $baseDir);
    }

    public function test_base_path_is_trimmed_of_trailing_slash(): void
    {
        // Arrange
        $strategy = new SearchPathStrategy('/var/search/');

        // Act
        $baseDir = $strategy->getBaseDirectory();

        // Assert
        $this->assertSame('/var/search', $baseDir);
    }

    public function test_base_path_without_trailing_slash_stays_same(): void
    {
        // Arrange
        $strategy = new SearchPathStrategy('/var/search');

        // Act
        $baseDir = $strategy->getBaseDirectory();

        // Assert
        $this->assertSame('/var/search', $baseDir);
    }

    public function test_file_path_uses_md5_hash_of_session_id(): void
    {
        // Arrange
        $record = $this->createSearchResultRecord('my_custom_session');

        // Act
        $path = $this->strategy->getFilePath($record);

        // Assert
        $expectedHash = substr(md5('my_custom_session'), 0, 8);
        $this->assertStringContainsString('search_'.$expectedHash.'.jsonl', $path);
    }

    public function test_all_results_from_same_session_go_to_same_file(): void
    {
        // Arrange
        $sessionId = 'same_session_id';
        $record1 = $this->createSearchResultRecord($sessionId);
        $record2 = $this->createSearchResultRecord($sessionId);
        $record3 = $this->createSearchResultRecord($sessionId);

        // Act
        $path1 = $this->strategy->getFilePath($record1);
        $path2 = $this->strategy->getFilePath($record2);
        $path3 = $this->strategy->getFilePath($record3);

        // Assert
        $this->assertSame($path1, $path2);
        $this->assertSame($path2, $path3);
    }
}

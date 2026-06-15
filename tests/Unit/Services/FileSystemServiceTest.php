<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\FileSystemInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class FileSystemServiceTest extends TestCase
{
    private FileSystemInterface $filesystem;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = $this->getService(FileSystemInterface::class);
        $this->tempDir = sys_get_temp_dir() . '/filesystem_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createTempFile(string $filename, string $content = 'test content'): string
    {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    // ============================================================================
    // exists() Tests
    // ============================================================================

    public function test_exists_returns_true_for_existing_file(): void
    {
        // Arrange
        $path = $this->createTempFile('test.txt');

        // Act
        $result = $this->filesystem->exists($path);

        // Assert
        $this->assertTrue($result);
    }

    public function test_exists_returns_false_for_nonexistent_file(): void
    {
        // Arrange
        $path = $this->tempDir . '/nonexistent.txt';

        // Act
        $result = $this->filesystem->exists($path);

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // get() Tests
    // ============================================================================

    public function test_get_returns_file_content(): void
    {
        // Arrange
        $expectedContent = 'Hello World!';
        $path = $this->createTempFile('content.txt', $expectedContent);

        // Act
        $content = $this->filesystem->get($path);

        // Assert
        $this->assertSame($expectedContent, $content);
    }

    public function test_get_throws_exception_for_nonexistent_file(): void
    {
        // Arrange
        $path = $this->tempDir . '/nonexistent.txt';

        // Expect
        $this->expectException(\RuntimeException::class);

        // Act
        $this->filesystem->get($path);
    }

    // ============================================================================
    // put() Tests
    // ============================================================================

    public function test_put_creates_file_with_content(): void
    {
        // Arrange
        $path = $this->tempDir . '/new_file.txt';
        $content = 'New file content';

        // Act
        $result = $this->filesystem->put($path, $content);

        // Assert
        $this->assertNotFalse($result);
        $this->assertFileExists($path);
        $this->assertSame($content, file_get_contents($path));
    }

    // ============================================================================
    // append() Tests
    // ============================================================================

    public function test_append_adds_content_to_file(): void
    {
        // Arrange
        $path = $this->createTempFile('append.txt', 'Initial content');

        // Act
        $result = $this->filesystem->append($path, "\nAppended content");

        // Assert
        $this->assertNotFalse($result);
        $content = file_get_contents($path);
        $this->assertStringContainsString('Initial content', $content);
        $this->assertStringContainsString('Appended content', $content);
    }

    // ============================================================================
    // isDirectory() Tests
    // ============================================================================

    public function test_is_directory_returns_true_for_directory(): void
    {
        // Arrange
        $path = $this->tempDir;

        // Act
        $result = $this->filesystem->isDirectory($path);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_directory_returns_false_for_file(): void
    {
        // Arrange
        $path = $this->createTempFile('file.txt');

        // Act
        $result = $this->filesystem->isDirectory($path);

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // isFile() Tests
    // ============================================================================

    public function test_is_file_returns_true_for_file(): void
    {
        // Arrange
        $path = $this->createTempFile('file.txt');

        // Act
        $result = $this->filesystem->isFile($path);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_file_returns_false_for_directory(): void
    {
        // Arrange
        $path = $this->tempDir;

        // Act
        $result = $this->filesystem->isFile($path);

        // Assert
        $this->assertFalse($result);
    }

    // ============================================================================
    // isReadable() Tests
    // ============================================================================

    public function test_is_readable_returns_true_for_readable_file(): void
    {
        // Arrange
        $path = $this->createTempFile('readable.txt');

        // Act
        $result = $this->filesystem->isReadable($path);

        // Assert
        $this->assertTrue($result);
    }

    // ============================================================================
    // isWritable() Tests
    // ============================================================================

    public function test_is_writable_returns_true_for_writable_file(): void
    {
        // Arrange
        $path = $this->createTempFile('writable.txt');

        // Act
        $result = $this->filesystem->isWritable($path);

        // Assert
        $this->assertTrue($result);
    }

    // ============================================================================
    // makeDirectory() Tests
    // ============================================================================

    public function test_make_directory_creates_directory(): void
    {
        // Arrange
        $newDir = $this->tempDir . '/new_directory';

        // Act
        $result = $this->filesystem->makeDirectory($newDir);

        // Assert
        $this->assertTrue($result);
        $this->assertDirectoryExists($newDir);
    }

    // ============================================================================
    // ensureDirectoryExists() Tests
    // ============================================================================

    public function test_ensure_directory_exists_creates_missing_directory(): void
    {
        // Arrange
        $newDir = $this->tempDir . '/missing_dir';

        // Act
        $this->filesystem->ensureDirectoryExists($newDir);

        // Assert
        $this->assertDirectoryExists($newDir);
    }

    // ============================================================================
    // copy() Tests
    // ============================================================================

    public function test_copy_copies_file(): void
    {
        // Arrange
        $source = $this->createTempFile('source.txt', 'Source content');
        $destination = $this->tempDir . '/destination.txt';

        // Act
        $result = $this->filesystem->copy($source, $destination);

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($destination);
        $this->assertSame('Source content', file_get_contents($destination));
    }

    // ============================================================================
    // move() Tests
    // ============================================================================

    public function test_move_moves_file(): void
    {
        // Arrange
        $source = $this->createTempFile('move_source.txt', 'Move content');
        $destination = $this->tempDir . '/move_destination.txt';

        // Act
        $result = $this->filesystem->move($source, $destination);

        // Assert
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($source);
        $this->assertFileExists($destination);
        $this->assertSame('Move content', file_get_contents($destination));
    }

    // ============================================================================
    // glob() Tests
    // ============================================================================

    public function test_glob_returns_matching_files(): void
    {
        // Arrange
        $this->createTempFile('file1.txt');
        $this->createTempFile('file2.txt');
        $this->createTempFile('file3.log');

        // Act
        $result = $this->filesystem->glob($this->tempDir . '/*.txt');

        // Assert
        $this->assertCount(2, $result);
    }

    // ============================================================================
    // delete() Tests
    // ============================================================================

    public function test_delete_removes_file(): void
    {
        // Arrange
        $path = $this->createTempFile('to_delete.txt');

        // Act
        $result = $this->filesystem->delete($path);

        // Assert
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($path);
    }

    // ============================================================================
    // deleteDirectory() Tests
    // ============================================================================

    public function test_delete_directory_removes_non_empty_directory(): void
    {
        // Arrange
        $dir = $this->tempDir . '/non_empty_dir';
        mkdir($dir);
        $this->createTempFile('non_empty_dir/file1.txt');

        // Act
        $result = $this->filesystem->deleteDirectory($dir);

        // Assert
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($dir);
    }

    // ============================================================================
    // size() Tests
    // ============================================================================

    public function test_size_returns_file_size(): void
    {
        // Arrange
        $content = '12345';
        $path = $this->createTempFile('size.txt', $content);

        // Act
        $result = $this->filesystem->size($path);

        // Assert
        $this->assertSame(strlen($content), $result);
    }

    // ============================================================================
    // lastModified() Tests
    // ============================================================================

    public function test_last_modified_returns_timestamp(): void
    {
        // Arrange
        $path = $this->createTempFile('modified.txt');

        // Act
        $result = $this->filesystem->lastModified($path);

        // Assert
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ============================================================================
    // extension() Tests
    // ============================================================================

    public function test_extension_returns_file_extension(): void
    {
        // Arrange
        $path = $this->tempDir . '/file.txt';

        // Act
        $result = $this->filesystem->extension($path);

        // Assert
        $this->assertSame('txt', $result);
    }

    public function test_extension_returns_empty_string_for_no_extension(): void
    {
        // Arrange
        $path = $this->tempDir . '/file_without_extension';

        // Act
        $result = $this->filesystem->extension($path);

        // Assert
        $this->assertSame('', $result);
    }

    // ============================================================================
    // basename() Tests
    // ============================================================================

    public function test_basename_returns_basename(): void
    {
        // Arrange
        $path = $this->tempDir . '/subdir/file.txt';

        // Act
        $result = $this->filesystem->basename($path);

        // Assert
        $this->assertSame('file.txt', $result);
    }

    // ============================================================================
    // dirname() Tests
    // ============================================================================

    public function test_dirname_returns_directory_name(): void
    {
        // Arrange
        $path = $this->tempDir . '/subdir/file.txt';

        // Act
        $result = $this->filesystem->dirname($path);

        // Assert
        $this->assertStringContainsString('subdir', $result);
    }
}

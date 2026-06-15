<?php

// src/Services/FileSystemService.php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\FileSystemInterface;
use AndyDefer\PhpSearch\Enums\PermissionMode;

/**
 * Native PHP implementation of the file system interface.
 *
 * This implementation uses only PHP built-in functions and has no external
 * dependencies, making it suitable for any PHP project regardless of the
 * framework being used.
 *
 * @author Andy Defer
 */
class FileSystemService implements FileSystemInterface
{
    /**
     * {@inheritDoc}
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $path): string
    {
        if (! $this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist at path: %s', $path));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read file at path: %s', $path));
        }

        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, string $content): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $content);
    }

    /**
     * {@inheritDoc}
     */
    public function append(string $path, string $content): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
    }

    /**
     * {@inheritDoc}
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * {@inheritDoc}
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool
    {
        if ($this->isDirectory($path)) {
            return true;
        }

        return mkdir($path, $mode->value(), $recursive);
    }

    /**
     * {@inheritDoc}
     */
    public function ensureDirectoryExists(string $path): void
    {
        if (! $this->isDirectory($path)) {
            if (! $this->makeDirectory($path, PermissionMode::DIRECTORY, true)) {
                throw new \RuntimeException(sprintf('Cannot create directory: %s', $path));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function copy(string $source, string $destination): bool
    {
        $this->ensureDirectoryExists(dirname($destination));

        return copy($source, $destination);
    }

    /**
     * {@inheritDoc}
     */
    public function move(string $source, string $destination): bool
    {
        $this->ensureDirectoryExists(dirname($destination));

        return rename($source, $destination);
    }

    /**
     * {@inheritDoc}
     */
    public function glob(string $pattern, int $flags = 0): array
    {
        $result = glob($pattern, $flags);

        return $result === false ? [] : $result;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): bool
    {
        if (! $this->exists($path)) {
            return true;
        }

        if ($this->isDirectory($path)) {
            return $this->deleteDirectory($path);
        }

        return unlink($path);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory(string $directory): bool
    {
        if (! $this->isDirectory($directory)) {
            return false;
        }

        $files = $this->glob($directory . '/*');

        foreach ($files as $file) {
            if ($this->isDirectory($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }

        return rmdir($directory);
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $path): int
    {
        if (! $this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist: %s', $path));
        }

        $size = filesize($path);

        if ($size === false) {
            throw new \RuntimeException(sprintf('Cannot get file size: %s', $path));
        }

        return $size;
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified(string $path): int
    {
        if (! $this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist: %s', $path));
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            throw new \RuntimeException(sprintf('Cannot get last modified time: %s', $path));
        }

        return $mtime;
    }

    /**
     * {@inheritDoc}
     */
    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * {@inheritDoc}
     */
    public function basename(string $path): string
    {
        return basename($path);
    }

    /**
     * {@inheritDoc}
     */
    public function dirname(string $path): string
    {
        return dirname($path);
    }
}

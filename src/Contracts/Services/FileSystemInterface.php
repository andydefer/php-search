<?php

// src/Contracts/Services/FileSystemInterface.php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

use AndyDefer\PhpSearch\Enums\PermissionMode;

/**
 * Interface for file system operations.
 *
 * This interface provides a minimal set of methods needed for file creation
 * and manipulation, decoupling the package from any specific framework
 * implementation.
 *
 * @author Andy Defer
 */
interface FileSystemInterface
{
    /**
     * Determine if a file or directory exists at the given path.
     *
     * @param  string  $path  The path to check
     * @return bool True if the path exists, false otherwise
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param  string  $path  Path to the file
     * @return string The file contents
     *
     * @throws \RuntimeException If the file cannot be read or does not exist
     */
    public function get(string $path): string;

    /**
     * Write the contents to a file.
     *
     * @param  string  $path  Destination path where the file should be created
     * @param  string  $content  Content to write to the file
     * @return int|false Number of bytes written, or false on failure
     */
    public function put(string $path, string $content): int|false;

    /**
     * Append content to a file.
     *
     * @param  string  $path  Path to the file
     * @param  string  $content  Content to append
     * @return int|false Number of bytes written, or false on failure
     */
    public function append(string $path, string $content): int|false;

    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is a directory, false otherwise
     */
    public function isDirectory(string $path): bool;

    /**
     * Determine if the given path is a file.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is a file, false otherwise
     */
    public function isFile(string $path): bool;

    /**
     * Determine if the given path is readable.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is readable, false otherwise
     */
    public function isReadable(string $path): bool;

    /**
     * Determine if the given path is writable.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is writable, false otherwise
     */
    public function isWritable(string $path): bool;

    /**
     * Create a directory.
     *
     * @param  string  $path  Directory path to create
     * @param  PermissionMode  $mode  Directory permissions (default: PermissionMode::DIRECTORY)
     * @param  bool  $recursive  Create parent directories if needed (default: true)
     * @return bool True on success, false on failure
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool;

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param  string  $path  Directory path to check/create
     *
     * @throws \RuntimeException If directory cannot be created
     */
    public function ensureDirectoryExists(string $path): void;

    /**
     * Copy a file from source to destination.
     *
     * @param  string  $source  Source file path
     * @param  string  $destination  Destination file path
     * @return bool True on success, false on failure
     */
    public function copy(string $source, string $destination): bool;

    /**
     * Move/Rename a file or directory.
     *
     * @param  string  $source  Source path
     * @param  string  $destination  Destination path
     * @return bool True on success, false on failure
     */
    public function move(string $source, string $destination): bool;

    /**
     * Find pathnames matching a pattern.
     *
     * @param  string  $pattern  The pattern to match (glob syntax)
     * @param  int  $flags  Optional flags (GLOB_MARK, GLOB_NOSORT, etc.)
     * @return array<int, string> Array of matching pathnames, empty array if no matches
     */
    public function glob(string $pattern, int $flags = 0): array;

    /**
     * Delete a file or directory.
     *
     * @param  string  $path  Path to the file or directory to delete
     * @return bool True on success, false on failure
     */
    public function delete(string $path): bool;

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param  string  $directory  Path to the directory to delete
     * @return bool True on success, false on failure
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Get the size of a file in bytes.
     *
     * @param  string  $path  Path to the file
     * @return int File size in bytes
     *
     * @throws \RuntimeException If file does not exist or cannot be read
     */
    public function size(string $path): int;

    /**
     * Get the last modified time of a file.
     *
     * @param  string  $path  Path to the file
     * @return int Unix timestamp of last modification
     *
     * @throws \RuntimeException If file does not exist
     */
    public function lastModified(string $path): int;

    /**
     * Get the file extension.
     *
     * @param  string  $path  Path to the file
     * @return string File extension (without dot), empty string if none
     */
    public function extension(string $path): string;

    /**
     * Get the basename of a path.
     *
     * @param  string  $path  Path to the file
     * @return string Basename of the path
     */
    public function basename(string $path): string;

    /**
     * Get the directory name of a path.
     *
     * @param  string  $path  Path to the file
     * @return string Directory name
     */
    public function dirname(string $path): string;
}

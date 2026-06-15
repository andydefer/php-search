<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use DateInterval;

/**
 * Persistent cache implementation using JSONL files.
 *
 * Stores cache entries as JSONL files organized by hash-based directories.
 *
 * @author Andy Defer
 */
final class PersistentCacheService implements CacheInterface
{
    public function __construct(
        private readonly SearchConfigInterface $config,
        private readonly JsonlService $jsonlService,
        private readonly FileSystemInterface $fileSystem,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->getFilePath($key);

        if (! $this->fileSystem->exists($filePath)) {
            return $default;
        }

        $content = $this->fileSystem->get($filePath);
        $record = json_decode($content, true);

        if ($record === null) {
            return $default;
        }

        // Vérifier l'expiration
        if (isset($record['expires_at']) && $record['expires_at'] < time()) {
            $this->fileSystem->delete($filePath);

            return $default;
        }

        return $record['value'];
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        $filePath = $this->getFilePath($key);
        $record = [
            'key' => $key,
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
        ];

        $content = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Créer le dossier si nécessaire
        $directory = dirname($filePath);
        if (! $this->fileSystem->isDirectory($directory)) {
            $this->fileSystem->makeDirectory($directory, PermissionMode::WORLD_WRITABLE, true);
        }

        return $this->fileSystem->put($filePath, $content) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function setWithInterval(string $key, mixed $value, DateInterval $ttl): bool
    {
        $now = new \DateTimeImmutable;
        $expiry = $now->add($ttl);

        return $this->set($key, $value, $expiry->getTimestamp() - time());
    }

    /**
     * {@inheritDoc}
     */
    public function setWithDefaultTtl(string $key, mixed $value): bool
    {
        $ttl = $this->config->getDefaultCacheTtl();

        if ($ttl === null) {
            return $this->setPermanent($key, $value);
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * {@inheritDoc}
     */
    public function setPermanent(string $key, mixed $value): bool
    {
        $filePath = $this->getFilePath($key);
        $record = [
            'key' => $key,
            'value' => $value,
            'expires_at' => null,
            'created_at' => time(),
        ];

        $content = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $directory = dirname($filePath);
        if (! $this->fileSystem->isDirectory($directory)) {
            $this->fileSystem->makeDirectory($directory, PermissionMode::WORLD_WRITABLE, true);
        }

        return $this->fileSystem->put($filePath, $content) !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (! $this->fileSystem->exists($filePath)) {
            return true;
        }

        return $this->fileSystem->delete($filePath);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): bool
    {
        $pattern = $this->getCachePattern();
        $files = $this->fileSystem->glob($pattern);

        foreach ($files as $file) {
            $this->fileSystem->delete($file);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if ($ttl !== null) {
                $this->set($key, $value, $ttl);
            } else {
                $this->setWithDefaultTtl($key, $value);
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (! $this->fileSystem->exists($filePath)) {
            return false;
        }

        $content = $this->fileSystem->get($filePath);
        $record = json_decode($content, true);

        if ($record === null) {
            return false;
        }

        // Vérifier l'expiration
        if (isset($record['expires_at']) && $record['expires_at'] < time()) {
            $this->fileSystem->delete($filePath);

            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getRecord(string $key): ?array
    {
        $filePath = $this->getFilePath($key);

        if (! $this->fileSystem->exists($filePath)) {
            return null;
        }

        $content = $this->fileSystem->get($filePath);
        $record = json_decode($content, true);

        if ($record === null) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($record['expires_at']) && $record['expires_at'] < time()) {
            $this->fileSystem->delete($filePath);

            return null;
        }

        return [
            'value' => $record['value'],
            'expires' => $record['expires_at'] ?? null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getRaw(string $key): ?string
    {
        $filePath = $this->getFilePath($key);

        if (! $this->fileSystem->exists($filePath)) {
            return null;
        }

        return $this->fileSystem->get($filePath);
    }

    /**
     * Retourne le chemin du fichier de cache pour une clé.
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        $levels = [];

        for ($i = 0; $i < 2; $i++) {
            $levels[] = $hash[$i];
        }

        $directory = $this->config->getCacheKeyRawData();
        $basePath = sys_get_temp_dir().'/php_search_cache';

        return implode(DIRECTORY_SEPARATOR, [
            $basePath,
            ...$levels,
            $this->sanitizeKey($key).'.json',
        ]);
    }

    /**
     * Retourne le pattern glob pour tous les fichiers cache.
     */
    private function getCachePattern(): string
    {
        return sys_get_temp_dir().'/php_search_cache/*/*/*.json';
    }

    /**
     * Nettoie la clé pour l'utiliser comme nom de fichier.
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
    }
}

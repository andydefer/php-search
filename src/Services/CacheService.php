<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use DateInterval;

/**
 * Simple array-based cache implementation without ambiguity.
 */
final class CacheService implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int|null}>
     */
    private array $cache = [];

    public function __construct(
        private readonly SearchConfigInterface $config
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $record = $this->getRecord($key);

        if ($record === null) {
            return $default;
        }

        return $record['value'];
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];

        return true;
    }

    public function setWithInterval(string $key, mixed $value, DateInterval $ttl): bool
    {
        $now = new \DateTimeImmutable;
        $expiry = $now->add($ttl);

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expiry->getTimestamp(),
        ];

        return true;
    }

    public function setWithDefaultTtl(string $key, mixed $value): bool
    {
        $ttl = $this->config->getDefaultCacheTtl();

        if ($ttl === null) {
            $this->cache[$key] = [
                'value' => $value,
                'expires' => null,
            ];
        } else {
            $this->cache[$key] = [
                'value' => $value,
                'expires' => time() + $ttl,
            ];
        }

        return true;
    }

    public function setPermanent(string $key, mixed $value): bool
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => null,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

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

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->cache[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $record = $this->getRecord($key);

        return $record !== null;
    }

    public function getRecord(string $key): ?array
    {
        if (! isset($this->cache[$key])) {
            return null;
        }

        $record = $this->cache[$key];

        // Check if expired
        if ($record['expires'] !== null && $record['expires'] < time()) {
            unset($this->cache[$key]);

            return null;
        }

        return $record;
    }

    public function getRaw(string $key): ?string
    {
        $record = $this->getRecord($key);

        if ($record === null) {
            return null;
        }

        return json_encode($record['value']);
    }
}

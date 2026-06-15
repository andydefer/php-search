<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

use DateInterval;

/**
 * PSR-16 compatible cache interface without ambiguity.
 */
interface CacheInterface
{
    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set with explicit TTL in seconds.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @param int $ttl Time to live in seconds.
     * @return bool True on success and false on failure.
     */
    public function set(string $key, mixed $value, int $ttl): bool;

    /**
     * Set with DateInterval TTL.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @param DateInterval $ttl Time to live as DateInterval.
     * @return bool True on success and false on failure.
     */
    public function setWithInterval(string $key, mixed $value, DateInterval $ttl): bool;

    /**
     * Set with default TTL from config.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @return bool True on success and false on failure.
     */
    public function setWithDefaultTtl(string $key, mixed $value): bool;

    /**
     * Set with no expiration (permanent).
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store.
     * @return bool True on success and false on failure.
     */
    public function setPermanent(string $key, mixed $value): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys A list of keys that can be obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     * @return iterable<string, mixed> A list of key => value pairs.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable<string, mixed> $values A list of key => value pairs for a multiple-set operation.
     * @param int|null $ttl Optional. The TTL value in seconds.
     * @return bool True on success and false on failure.
     */
    public function setMultiple(iterable $values, ?int $ttl = null): bool;

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key The cache item key.
     * @return bool True on success and false on failure.
     */
    public function has(string $key): bool;

    /**
     * Gets the array record for a key.
     *
     * @param string $key The cache key.
     * @return array{value: mixed, expires: int|null}|null The cache record or null if not found.
     */
    public function getRecord(string $key): ?array;

    /**
     * Gets the raw value from cache (without unserialization).
     *
     * @param string $key The cache key.
     * @return string|null The raw JSON value or null if not found.
     */
    public function getRaw(string $key): ?string;
}

<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Container;

use Psr\Container\ContainerInterface;

/**
 * Simple Dependency Injection Container.
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, callable|object>
     */
    private array $entries = [];

    /**
     * @var array<string, bool>
     */
    private array $resolved = [];

    /**
     * Register a service in the container.
     */
    public function set(string $id, callable|object $definition): void
    {
        $this->entries[$id] = $definition;
        unset($this->resolved[$id]);
    }

    /**
     * Register a singleton service (lazy-loaded).
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->entries[$id] = $factory;
    }

    /**
     * Register an already instantiated service.
     */
    public function instance(string $id, object $instance): void
    {
        $this->entries[$id] = $instance;
        $this->resolved[$id] = true;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     * @throws \InvalidArgumentException
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \InvalidArgumentException("Service not found: {$id}");
        }

        $entry = $this->entries[$id];

        // Return already resolved instance
        if (isset($this->resolved[$id])) {
            return $entry;
        }

        // Resolve factory
        if (is_callable($entry)) {
            $instance = $entry($this);
            $this->entries[$id] = $instance;
            $this->resolved[$id] = true;
            return $instance;
        }

        // Return raw value
        return $entry;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * Remove a service from the container.
     */
    public function remove(string $id): void
    {
        unset($this->entries[$id], $this->resolved[$id]);
    }

    /**
     * Clear all services from the container.
     */
    public function clear(): void
    {
        $this->entries = [];
        $this->resolved = [];
    }
}

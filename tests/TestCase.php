<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests;

use AndyDefer\PhpSearch\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?Container $container = null;

    /**
     * Get the dependency injection container.
     * The container is lazy-loaded and shared across all tests.
     */
    protected function getContainer(): Container
    {
        if ($this->container === null) {
            $this->container = require __DIR__ . '/../config/container.php';
        }

        return $this->container;
    }

    /**
     * Get a service from the container.
     *
     * @param string $id Service identifier (interface or class name)
     * @return mixed
     */
    protected function getService(string $id): mixed
    {
        return $this->getContainer()->get($id);
    }

    /**
     * Set a custom container for testing (useful for mocking).
     */
    protected function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Clear the container instance (useful between tests).
     */
    protected function clearContainer(): void
    {
        $this->container = null;
    }
}

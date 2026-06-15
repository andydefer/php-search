<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Strategies;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpJsonl\Contracts\JsonlPathStrategyInterface;
use AndyDefer\PhpSearch\Records\SearchResultRecord;

/**
 * Stratégie de chemin pour les résultats de recherche.
 *
 * Organise les fichiers JSONL de résultats par session.
 *
 * @author Andy Defer
 */
final class SearchPathStrategy implements JsonlPathStrategyInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePath(AbstractRecord $entity): string
    {
        if (! $entity instanceof SearchResultRecord) {
            throw new \InvalidArgumentException(
                sprintf('SearchPathStrategy expects SearchResultRecord, got %s', get_class($entity))
            );
        }

        $sessionHash = substr(md5($entity->session_id), 0, 8);
        $filename = sprintf('search_%s.jsonl', $sessionHash);

        return $this->basePath.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilesToScan(AbstractRecord $query): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        $pattern = $this->basePath.DIRECTORY_SEPARATOR.'search_*.jsonl';

        return glob($pattern) ?: [];
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseDirectory(): string
    {
        return $this->basePath;
    }
}

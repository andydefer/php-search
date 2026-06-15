<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Configs;

/**
 * Configuration du moteur de recherche.
 *
 * @author Andy Defer
 */
final class EngineConfig
{
    private const INDEX_BASE_DIR = '.php_search_index';

    private const LAST_SOURCE_FILE = '.last_source';

    private const INDEX_FILE = 'index.jsonl';

    private const CACHE_DIR = 'cache';

    private const CACHE_KEY_PREFIX = 'search_result_';

    private const CACHE_TTL = 86400; // 24h

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? getcwd(), DIRECTORY_SEPARATOR);
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getIndexBaseDir(): string
    {
        return self::INDEX_BASE_DIR;
    }

    public function getIndexFile(): string
    {
        return self::INDEX_FILE;
    }

    public function getCacheDir(): string
    {
        return self::CACHE_DIR;
    }

    public function getCacheKeyPrefix(): string
    {
        return self::CACHE_KEY_PREFIX;
    }

    public function getCacheTtl(): int
    {
        return self::CACHE_TTL;
    }

    /**
     * Génère un nom d'index unique à partir du chemin source.
     */
    public function getIndexName(string $source): string
    {
        $normalized = str_replace(['/', '\\', ':'], '_', $source);
        $normalized = strtr($normalized, [
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            'ï' => 'i',
            'î' => 'i',
        ]);

        return $normalized;
    }

    /**
     * Retourne le chemin de l'index pour une source donnée.
     */
    public function getIndexPathForSource(string $source): string
    {
        $indexName = $this->getIndexName($source);

        return $this->basePath.DIRECTORY_SEPARATOR.self::INDEX_BASE_DIR.DIRECTORY_SEPARATOR.$indexName;
    }

    /**
     * Retourne le chemin du fichier d'index pour une source donnée.
     */
    public function getIndexFilePathForSource(string $source): string
    {
        return $this->getIndexPathForSource($source).DIRECTORY_SEPARATOR.self::INDEX_FILE;
    }

    /**
     * Retourne le chemin du cache pour une source donnée.
     */
    public function getCachePathForSource(string $source): string
    {
        return $this->getIndexPathForSource($source).DIRECTORY_SEPARATOR.self::CACHE_DIR;
    }

    /**
     * Sauvegarde la dernière source utilisée.
     */
    public function saveLastSource(string $source): void
    {
        file_put_contents($this->getLastSourceFilePath(), $source);
    }

    /**
     * Charge la dernière source utilisée.
     */
    public function loadLastSource(): ?string
    {
        $file = $this->getLastSourceFilePath();
        if (! file_exists($file)) {
            return null;
        }

        return trim(file_get_contents($file));
    }

    /**
     * Supprime la dernière source sauvegardée.
     */
    public function clearLastSource(): void
    {
        $file = $this->getLastSourceFilePath();
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Retourne le chemin du fichier de dernière source.
     */
    private function getLastSourceFilePath(): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.self::LAST_SOURCE_FILE;
    }
}

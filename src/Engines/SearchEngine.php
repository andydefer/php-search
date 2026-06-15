<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Engines;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpJsonl\Strategies\KeyBasedPathStrategy;
use AndyDefer\PhpSearch\Configs\EngineConfig;
use AndyDefer\PhpSearch\Contracts\Engine\SearchEngineInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Records\IndexedItemRecord;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;

/**
 * Moteur de recherche unifié avec indexation persistante et cache des résultats.
 *
 * @author Andy Defer
 */
final class SearchEngine extends BaseSearchEngine implements SearchEngineInterface
{
    public function __construct(
        QueryProcessorInterface $queryProcessor,
        private readonly FileSystemInterface $fileSystem,
        private readonly EngineConfig $config,
        PreFilterInterface $preFilter,
    ) {
        parent::__construct($queryProcessor, $preFilter);
    }

    /**
     * {@inheritDoc}
     */
    public function index(string $source): int
    {
        $source = realpath($source) ?: $source;

        if (! is_dir($source) && ! is_file($source)) {
            throw new \InvalidArgumentException("Source invalide: {$source}");
        }

        $this->ensureDirectoriesForSource($source);
        $this->deleteIndex($source);
        $this->clearCache($source);

        $items = [];

        if (is_file($source) && pathinfo($source, PATHINFO_EXTENSION) === 'jsonl') {
            $items = $this->indexSingleFile($source, $source);
        } elseif (is_dir($source)) {
            $items = $this->indexDirectory($source);
        }

        $this->saveIndex($source, $items);
        $this->config->saveLastSource($source);

        return count($items);
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limit = 5, ?string $source = null): array
    {
        if ($source === null) {
            $source = $this->config->loadLastSource();
        }

        if ($source === null) {
            throw new \RuntimeException(
                'Aucune source indexée. Veuillez d\'abord indexer un dossier ou fichier.'
            );
        }

        if (! $this->hasIndex($source)) {
            throw new \RuntimeException(
                "Aucun index trouvé pour la source: {$source}. Veuillez d'abord indexer."
            );
        }

        // Normalisation de la requête pour la clé de cache
        $normalizedQuery = $this->normalizeQuery($query);
        $cacheKey = $this->config->getCacheKeyPrefix().md5($normalizedQuery.'|'.$limit);

        $cached = $this->getCachedResult($source, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $processedQuery = $this->queryProcessor->process($query);
        if (empty($processedQuery)) {
            return [];
        }

        $indexItems = $this->loadIndex($source);
        $results = [];

        foreach ($indexItems as $item) {
            $score = $this->queryProcessor->computeScore($processedQuery, $item['item_words']);
            if ($score !== null) {
                $results[] = [
                    'name' => $item['original_text'],
                    'source' => $item['source'],
                    'score' => $score['score'],
                    'max_possible' => $score['max_possible'],
                    'percentage' => $score['percentage'],
                ];
            }
        }

        $this->queryProcessor->sortResults($results);
        $finalResults = array_slice($results, 0, $limit);
        $this->cacheResult($source, $cacheKey, $finalResults);

        return $finalResults;
    }

    /**
     * {@inheritDoc}
     */
    public function hasIndex(?string $source = null): bool
    {
        if ($source === null) {
            $source = $this->config->loadLastSource();
        }
        if ($source === null) {
            return false;
        }

        return $this->fileSystem->exists($this->config->getIndexFilePathForSource($source));
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexStats(?string $source = null): array
    {
        if ($source === null) {
            $source = $this->config->loadLastSource();
        }

        $indexPath = $this->config->getIndexPathForSource($source);
        $indexFile = $this->config->getIndexFilePathForSource($source);

        if (! $this->fileSystem->exists($indexFile)) {
            return [
                'exists' => false,
                'total_items' => 0,
                'index_path' => $indexPath,
                'cache_path' => $this->config->getCachePathForSource($source),
                'source' => $source,
            ];
        }

        $items = $this->loadIndex($source);

        return [
            'exists' => true,
            'total_items' => count($items),
            'index_path' => $indexPath,
            'cache_path' => $this->config->getCachePathForSource($source),
            'index_size_bytes' => $this->fileSystem->size($indexFile),
            'source' => $source,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function deleteIndex(?string $source = null): void
    {
        if ($source === null) {
            $source = $this->config->loadLastSource();
        }

        if ($source === null) {
            return;
        }

        $indexPath = $this->config->getIndexPathForSource($source);
        if ($this->fileSystem->isDirectory($indexPath)) {
            $this->deleteDirectory($indexPath);
        }

        $lastSource = $this->config->loadLastSource();
        if ($source === $lastSource) {
            $this->config->clearLastSource();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(?string $source = null, ?string $query = null): void
    {
        if ($source === null) {
            $source = $this->config->loadLastSource();
        }

        if ($source === null) {
            return;
        }

        $cachePath = $this->config->getCachePathForSource($source);

        if ($query === null) {
            if ($this->fileSystem->isDirectory($cachePath)) {
                $this->deleteDirectory($cachePath);
                $this->ensureDirectoriesForSource($source);
            }

            return;
        }

        $normalizedQuery = $this->normalizeQuery($query);
        $cacheKey = $this->config->getCacheKeyPrefix().md5($normalizedQuery.'|5');
        $cacheFile = $this->getCacheFilePath($source, $cacheKey);

        if ($this->fileSystem->exists($cacheFile)) {
            $this->fileSystem->delete($cacheFile);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listIndexes(): array
    {
        $baseIndexDir = $this->config->getBasePath().DIRECTORY_SEPARATOR.$this->config->getIndexBaseDir();

        if (! $this->fileSystem->isDirectory($baseIndexDir)) {
            return [];
        }

        $indexes = [];
        $dirs = $this->fileSystem->glob($baseIndexDir.DIRECTORY_SEPARATOR.'*');

        foreach ($dirs as $dir) {
            if ($this->fileSystem->isDirectory($dir)) {
                $indexName = basename($dir);
                $indexFile = $dir.DIRECTORY_SEPARATOR.$this->config->getIndexFile();

                if ($this->fileSystem->exists($indexFile)) {
                    $content = $this->fileSystem->get($indexFile);
                    $lines = explode("\n", trim($content));
                    $totalItems = 0;
                    foreach ($lines as $line) {
                        if (trim($line) !== '') {
                            $totalItems++;
                        }
                    }

                    $indexes[$indexName] = [
                        'path' => $dir,
                        'total_items' => $totalItems,
                        'size_bytes' => $this->fileSystem->size($indexFile),
                    ];
                }
            }
        }

        return $indexes;
    }

    // ============================================================
    // Méthodes privées
    // ============================================================

    private function ensureDirectoriesForSource(string $source): void
    {
        $indexPath = $this->config->getIndexPathForSource($source);
        $cachePath = $this->config->getCachePathForSource($source);

        if (! $this->fileSystem->isDirectory($indexPath)) {
            $this->fileSystem->makeDirectory($indexPath, PermissionMode::DIRECTORY, true);
        }
        if (! $this->fileSystem->isDirectory($cachePath)) {
            $this->fileSystem->makeDirectory($cachePath, PermissionMode::DIRECTORY, true);
        }
    }

    private function indexDirectory(string $directory): array
    {
        $items = [];
        $files = $this->findAllJsonlFiles($directory);
        $sourceName = realpath($directory) ?: $directory;

        foreach ($files as $filePath) {
            $fileItems = $this->indexSingleFile($filePath, $sourceName);
            $items = array_merge($items, $fileItems);
        }

        return $items;
    }

    private function indexSingleFile(string $filePath, string $sourceName): array
    {
        $items = [];
        $lineNumber = 0;
        $context = new JsonlContext;

        $tempService = new JsonlService(
            new KeyBasedPathStrategy('/tmp', 1),
            $this->fileSystem,
            $context
        );

        $tempService->readLineByLine($filePath, function ($line) use ($filePath, $sourceName, &$items, &$lineNumber) {
            $lineNumber++;
            foreach ($line as $field => $value) {
                if (is_string($value)) {
                    $items[] = $this->createIndexItem($value, $filePath, $sourceName, $lineNumber, $field);
                } elseif (is_array($value)) {
                    $this->flattenAndIndex($value, $filePath, $sourceName, $lineNumber, $field, $items);
                }
            }
        });

        return $items;
    }

    private function flattenAndIndex(array $array, string $filePath, string $sourceName, int $lineNumber, string $parentField, array &$items): void
    {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $items[] = $this->createIndexItem($value, $filePath, $sourceName, $lineNumber, "{$parentField}.{$key}");
            } elseif (is_array($value)) {
                $this->flattenAndIndex($value, $filePath, $sourceName, $lineNumber, "{$parentField}.{$key}", $items);
            }
        }
    }

    private function createIndexItem(string $text, string $filePath, string $sourceName, int $line, string $field): array
    {
        $processedWords = $this->queryProcessor->process($text);
        $itemWordsCollection = new StringTypedCollection;

        foreach ($processedWords as $wordData) {
            $normalized = $wordData['normalized'];
            $itemWordsCollection->add(json_encode([
                'original' => $normalized,
                'normalized' => $normalized,
                'max_score' => $this->calculateMaxScore($normalized),
                'ngrams' => $wordData['ngrams'],
            ]));
        }

        return [
            'id' => md5($sourceName.$filePath.$line.$field.$text),
            'original_text' => $text,
            'source' => $filePath,
            'line' => $line,
            'field' => $field,
            'item_words' => $itemWordsCollection,
        ];
    }

    private function saveIndex(string $source, array $items): void
    {
        $indexFile = $this->config->getIndexFilePathForSource($source);
        $content = '';

        foreach ($items as $item) {
            $record = new IndexedItemRecord(
                id: $item['id'],
                original_text: $item['original_text'],
                source: $item['source'],
                line: $item['line'],
                field: $item['field'],
                item_words: $item['item_words'],
            );

            $normalized = $this->normalizeRecord($record);
            $content .= json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $this->fileSystem->put($indexFile, $content);
    }

    private function normalizeRecord(IndexedItemRecord $record): array
    {
        $itemWordsArray = [];
        foreach ($record->item_words as $encoded) {
            $itemWordsArray[] = json_decode($encoded, true);
        }

        return [
            'id' => $record->id,
            'original_text' => $record->original_text,
            'source' => $record->source,
            'line' => $record->line,
            'field' => $record->field,
            'item_words' => $itemWordsArray,
        ];
    }

    private function loadIndex(string $source): array
    {
        $indexFile = $this->config->getIndexFilePathForSource($source);

        if (! $this->fileSystem->exists($indexFile)) {
            return [];
        }

        $content = $this->fileSystem->get($indexFile);
        $lines = explode("\n", trim($content));
        $items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $items[] = json_decode($trimmed, true);
            }
        }

        return $items;
    }

    private function getCacheFilePath(string $source, string $cacheKey): string
    {
        $hash = md5($cacheKey);
        $cachePath = $this->config->getCachePathForSource($source);

        return implode(DIRECTORY_SEPARATOR, [
            $cachePath,
            $hash[0],
            $hash[1],
            $cacheKey.'.json',
        ]);
    }

    private function getCachedResult(string $source, string $cacheKey): ?array
    {
        $cacheFile = $this->getCacheFilePath($source, $cacheKey);

        if (! $this->fileSystem->exists($cacheFile)) {
            return null;
        }

        $content = $this->fileSystem->get($cacheFile);
        $data = json_decode($content, true);

        if ($data === null) {
            return null;
        }

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->fileSystem->delete($cacheFile);

            return null;
        }

        return $data['results'];
    }

    private function cacheResult(string $source, string $cacheKey, array $results): void
    {
        $cacheFile = $this->getCacheFilePath($source, $cacheKey);
        $directory = dirname($cacheFile);

        if (! $this->fileSystem->isDirectory($directory)) {
            $this->fileSystem->makeDirectory($directory, PermissionMode::DIRECTORY, true);
        }

        $data = [
            'key' => $cacheKey,
            'results' => $results,
            'created_at' => time(),
            'expires_at' => time() + $this->config->getCacheTtl(),
        ];

        $this->fileSystem->put($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function findAllJsonlFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $pattern = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.jsonl';
        $files = glob($pattern);

        $subdirs = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);

        foreach ($subdirs as $subdir) {
            $subFiles = $this->findAllJsonlFiles($subdir);
            $files = array_merge($files, $subFiles);
        }

        return $files;
    }

    private function deleteDirectory(string $directory): void
    {
        $files = $this->fileSystem->glob($directory.DIRECTORY_SEPARATOR.'*');

        foreach ($files as $file) {
            if ($this->fileSystem->isDirectory($file)) {
                $this->deleteDirectory($file);
            } else {
                $this->fileSystem->delete($file);
            }
        }

        $this->fileSystem->delete($directory);
    }
}

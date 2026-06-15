<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpJsonl\Strategies\KeyBasedPathStrategy;
use AndyDefer\PhpSearch\Contracts\Services\IndexedSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpSearch\Records\IndexedItemRecord;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;

/**
 * Moteur de recherche avec pré-indexation.
 *
 * Prépare les n-grams une fois pour toutes et les stocke dans un index JSONL.
 * La recherche devient alors extrêmement rapide car elle n'a plus qu'à comparer
 * les n-grams pré-calculés.
 *
 * @author Andy Defer
 */
final class IndexedSearchEngine implements IndexedSearchInterface
{
    private const INDEX_DIR = '.php_search_index';

    private const INDEX_FILE = 'index.jsonl';

    private JsonlService $indexService;

    private FileSystemInterface $fileSystem;

    private string $indexPath;

    public function __construct(
        private readonly QueryProcessorInterface $queryProcessor,
        private readonly PreFilterInterface $preFilter,
        ?string $basePath = null,
    ) {
        $this->fileSystem = new FileSystemService;
        $basePath = $basePath ?? getcwd();
        $this->indexPath = $basePath.DIRECTORY_SEPARATOR.self::INDEX_DIR;

        $strategy = new KeyBasedPathStrategy($this->indexPath, 2);
        $context = new JsonlContext;
        $this->indexService = new JsonlService($strategy, $this->fileSystem, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function index(string $source): int
    {
        $this->ensureIndexDirectory();
        $this->clearIndex();

        $items = [];

        if (is_file($source) && pathinfo($source, PATHINFO_EXTENSION) === 'json') {
            $items = $this->indexJsonFile($source);
        } elseif (is_dir($source)) {
            $items = $this->indexJsonlDirectory($source);
        } else {
            throw new \InvalidArgumentException("Source invalide: {$source}");
        }

        $this->saveIndex($items);

        return count($items);
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $query, int $limit = 5): array
    {
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $indexItems = $this->loadIndex();
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

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexStats(): array
    {
        $indexFile = $this->indexPath.DIRECTORY_SEPARATOR.self::INDEX_FILE;

        if (! $this->fileSystem->exists($indexFile)) {
            return [
                'exists' => false,
                'total_items' => 0,
                'index_path' => $this->indexPath,
            ];
        }

        $items = $this->loadIndex();

        return [
            'exists' => true,
            'total_items' => count($items),
            'index_path' => $this->indexPath,
            'index_size_bytes' => $this->fileSystem->size($indexFile),
        ];
    }

    private function ensureIndexDirectory(): void
    {
        if (! $this->fileSystem->isDirectory($this->indexPath)) {
            $this->fileSystem->makeDirectory($this->indexPath, PermissionMode::DIRECTORY, true);
        }
    }

    private function clearIndex(): void
    {
        $pattern = $this->indexPath.DIRECTORY_SEPARATOR.'*.jsonl';
        $files = $this->fileSystem->glob($pattern);

        foreach ($files as $file) {
            $this->fileSystem->delete($file);
        }
    }

    private function indexJsonFile(string $filePath): array
    {
        $content = $this->fileSystem->get($filePath);
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new \InvalidArgumentException("Fichier JSON invalide: {$filePath}");
        }

        $items = [];

        foreach ($data as $item) {
            if (is_string($item)) {
                $items[] = $this->createIndexItem($item, $filePath);
            }
        }

        return $items;
    }

    private function indexJsonlDirectory(string $directory): array
    {
        $items = [];
        $files = $this->findAllJsonlFiles($directory);

        foreach ($files as $filePath) {
            $fileItems = $this->indexJsonlFile($filePath);
            $items = array_merge($items, $fileItems);
        }

        return $items;
    }

    private function indexJsonlFile(string $filePath): array
    {
        $items = [];
        $lineNumber = 0;

        $tempService = new JsonlService(
            new KeyBasedPathStrategy('/tmp', 1),
            $this->fileSystem,
            new JsonlContext
        );

        $tempService->readLineByLine($filePath, function ($line) use ($filePath, &$items, &$lineNumber) {
            $lineNumber++;

            foreach ($line as $field => $value) {
                if (is_string($value)) {
                    $items[] = $this->createIndexItem($value, $filePath, $lineNumber, $field);
                } elseif (is_array($value)) {
                    $this->flattenAndIndex($value, $filePath, $lineNumber, $field, $items);
                }
            }
        });

        return $items;
    }

    private function flattenAndIndex(array $array, string $filePath, int $lineNumber, string $parentField, array &$items): void
    {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $items[] = $this->createIndexItem($value, $filePath, $lineNumber, "{$parentField}.{$key}");
            } elseif (is_array($value)) {
                $this->flattenAndIndex($value, $filePath, $lineNumber, "{$parentField}.{$key}", $items);
            }
        }
    }

    private function createIndexItem(string $text, string $source, int $line = 0, string $field = ''): array
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
            'id' => md5($source.$line.$field.$text),
            'original_text' => $text,
            'source' => $source,
            'line' => $line,
            'field' => $field,
            'item_words' => $itemWordsCollection,
        ];
    }

    private function saveIndex(array $items): void
    {
        $indexFile = $this->indexPath.DIRECTORY_SEPARATOR.self::INDEX_FILE;
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

    private function loadIndex(): array
    {
        $indexFile = $this->indexPath.DIRECTORY_SEPARATOR.self::INDEX_FILE;

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

    private function calculateMaxScore(string $word): float
    {
        $length = strlen($word);
        if ($length <= 1) {
            return 0;
        }
        if ($length === 2) {
            return 2.5;
        }
        if ($length === 3) {
            return 9.0;
        }

        return 15.5;
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
}

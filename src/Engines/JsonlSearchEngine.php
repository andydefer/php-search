<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Engines;

use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Contracts\Services\JsonlSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

class JsonlSearchEngine extends BaseSearchEngine implements JsonlSearchInterface
{
    public function __construct(
        private readonly JsonlService $jsonlService,
        private readonly FileSystemInterface $fileSystem,
        QueryProcessorInterface $queryProcessor,
        PreFilterInterface $preFilter,
    ) {
        parent::__construct($queryProcessor, $preFilter);
    }

    /**
     * {@inheritDoc}
     */
    public function searchInDirectory(string $directory, string $fields, string $query, int $limit = 5): array
    {
        $fieldList = array_map('trim', explode('|', $fields));
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $jsonlFiles = $this->findAllJsonlFiles($directory);
        $allResults = [];

        foreach ($jsonlFiles as $filePath) {
            $fileResults = $this->searchInFile($filePath, $fieldList, $query, $limit);
            $allResults = array_merge($allResults, $fileResults);
        }

        $this->queryProcessor->sortResults($allResults);

        return array_slice($allResults, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function searchInFile(string $filePath, array $fields, string $query, int $limit = 5): array
    {
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        if (! $this->fileSystem->exists($filePath)) {
            return [];
        }

        $results = [];
        $lineNumber = 0;

        $this->jsonlService->readLineByLine($filePath, function ($line) use (
            $fields,
            $processedQuery,
            $query,
            $filePath,
            &$results,
            &$lineNumber
        ) {
            $lineNumber++;

            if (! $this->passesPreFilter($line, $fields, $query)) {
                return;
            }

            $score = $this->computeLineScore($line, $fields, $processedQuery);

            if ($score !== null) {
                $results[] = [
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'data' => $line,
                    'score' => $score['score'],
                    'max_possible' => $score['max_possible'],
                    'percentage' => $score['percentage'],
                ];
            }
        });

        $this->queryProcessor->sortResults($results);

        return array_slice($results, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function searchInStream($stream, array $fields, string $query, int $limit = 5): array
    {
        if (! is_resource($stream)) {
            throw new \InvalidArgumentException('Le paramètre doit être une ressource de type stream');
        }

        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $results = [];
        $lineNumber = 0;

        while (! feof($stream)) {
            $line = fgets($stream);
            if ($line === false) {
                break;
            }

            $lineNumber++;
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            $data = json_decode($trimmedLine, true);

            if ($data === null) {
                continue;
            }

            if (! $this->passesPreFilter($data, $fields, $query)) {
                continue;
            }

            $score = $this->computeLineScore($data, $fields, $processedQuery);

            if ($score !== null) {
                $results[] = [
                    'stream' => true,
                    'line' => $lineNumber,
                    'data' => $data,
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
    public function searchInIterable(iterable $iterable, array $fields, string $query, int $limit = 5): array
    {
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $results = [];
        $lineNumber = 0;

        foreach ($iterable as $item) {
            $lineNumber++;

            if (! is_array($item)) {
                continue;
            }

            if (! $this->passesPreFilter($item, $fields, $query)) {
                continue;
            }

            $score = $this->computeLineScore($item, $fields, $processedQuery);

            if ($score !== null) {
                $results[] = [
                    'source' => 'iterable',
                    'line' => $lineNumber,
                    'data' => $item,
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
    public function searchInIndex(string $indexPath, string $query, int $limit = 5): array
    {
        if (! $this->fileSystem->exists($indexPath)) {
            throw new \InvalidArgumentException("Fichier d'index introuvable: {$indexPath}");
        }

        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $content = $this->fileSystem->get($indexPath);
        $lines = explode("\n", trim($content));
        $results = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $item = json_decode($trimmedLine, true);
            if ($item === null) {
                continue;
            }

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

    // ============================================================
    // Méthodes privées
    // ============================================================

    /**
     * Trouve tous les fichiers JSONL dans un dossier.
     */
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

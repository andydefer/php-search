<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Contracts\Services\JsonlSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

class JsonlSearchEngine implements JsonlSearchInterface
{
    public function __construct(
        private readonly JsonlService $jsonlService,
        private readonly FileSystemInterface $fileSystem,
        private readonly QueryProcessorInterface $queryProcessor,
        private readonly PreFilterInterface $preFilter,
    ) {}

    public function search(string $directory, string $fields, string $query, int $limit = 5): array
    {

        $fieldList = array_map('trim', explode('|', $fields));
        $processedQuery = $this->queryProcessor->process($query);

        if (empty($processedQuery)) {
            return [];
        }

        $jsonlFiles = $this->findAllJsonlFiles($directory);

        $allResults = [];

        foreach ($jsonlFiles as $filePath) {
            $fileResults = $this->searchFile($filePath, $fieldList, $query, $limit);
            $allResults = array_merge($allResults, $fileResults);
        }

        $this->queryProcessor->sortResults($allResults);

        return array_slice($allResults, 0, $limit);
    }

    public function searchFile(string $filePath, array $fields, string $query, int $limit = 5): array
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

    private function passesPreFilter(array $line, array $fields, string $query): bool
    {
        $flattenedValues = $this->extractFieldValues($line, $fields);

        if (empty($flattenedValues)) {
            return false;
        }

        foreach ($flattenedValues as $value) {
            if ($this->preFilter->passes((string) $value, $query)) {
                return true;
            }
        }

        return false;
    }

    private function computeLineScore(array $line, array $fields, array $processedQuery): ?array
    {
        $flattenedValues = $this->extractFieldValues($line, $fields);

        if (empty($flattenedValues)) {
            return null;
        }

        $bestScore = null;
        $bestPercentage = 0.0;

        foreach ($flattenedValues as $value) {
            $itemWords = $this->prepareItemWords((string) $value);
            $score = $this->queryProcessor->computeScore($processedQuery, $itemWords);

            if ($score !== null && $score['percentage'] > $bestPercentage) {
                $bestPercentage = $score['percentage'];
                $bestScore = $score;
            }
        }

        return $bestScore;
    }

    private function extractFieldValues(array $data, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $this->getNestedValue($data, $field);

            if ($value !== null) {
                if (is_array($value)) {
                    $values = array_merge($values, $this->flattenArray($value));
                } else {
                    $values[] = (string) $value;
                }
            }
        }

        return array_unique($values);
    }

    private function getNestedValue(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    private function flattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item)) {
                $result = array_merge($result, $this->flattenArray($item));
            } else {
                $result[] = (string) $item;
            }
        }

        return $result;
    }

    private function prepareItemWords(string $item): array
    {
        $processed = $this->queryProcessor->process($item);
        $itemWords = [];

        foreach ($processed as $wordData) {
            $normalized = $wordData['normalized'];
            $itemWords[] = [
                'original' => $normalized,
                'normalized' => $normalized,
                'max_score' => $this->calculateMaxScore($normalized),
                'ngrams' => $wordData['ngrams'],
            ];
        }

        return $itemWords;
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

        // Utiliser glob directement au lieu de FileSystemInterface
        $pattern = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.jsonl';

        $files = glob($pattern);

        // Ajouter les sous-dossiers
        $subdirs = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);

        foreach ($subdirs as $subdir) {
            $subFiles = $this->findAllJsonlFiles($subdir);
            $files = array_merge($files, $subFiles);
        }

        return $files;
    }
}

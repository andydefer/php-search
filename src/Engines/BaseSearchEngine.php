<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Engines;

use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Contracts\Services\QueryProcessorInterface;

/**
 * Classe abstraite contenant les méthodes communes aux moteurs de recherche.
 *
 * @author Andy Defer
 */
abstract class BaseSearchEngine
{
    public function __construct(
        protected readonly QueryProcessorInterface $queryProcessor,
        protected readonly PreFilterInterface $preFilter,
    ) {}

    /**
     * Vérifie si une ligne passe le pré-filtre.
     */
    protected function passesPreFilter(array $line, array $fields, string $query): bool
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

    /**
     * Calcule le score pour une ligne sur les champs spécifiés.
     */
    protected function computeLineScore(array $line, array $fields, array $processedQuery): ?array
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

    /**
     * Extrait les valeurs des champs spécifiés d'une ligne JSON.
     */
    protected function extractFieldValues(array $data, array $fields): array
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

    /**
     * Récupère une valeur imbriquée via une notation pointée.
     */
    protected function getNestedValue(array $data, string $path): mixed
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

    /**
     * Aplatit un tableau récursivement en strings.
     */
    protected function flattenArray(array $array): array
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

    /**
     * Prépare les mots d'un item pour le scoring.
     */
    protected function prepareItemWords(string $item): array
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

    /**
     * Calcule le score maximum pour un mot.
     */
    protected function calculateMaxScore(string $word): float
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

    /**
     * Normalise une requête (minuscules, suppression accents).
     */
    protected function normalizeQuery(string $query): string
    {
        $normalized = strtolower($query);

        return strtr($normalized, [
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
    }
}

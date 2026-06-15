<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Configs;

use AndyDefer\PhpSearch\Contracts\Configs\SearchConfigInterface;

final class SearchConfig implements SearchConfigInterface
{
    private array $diacritics = [
        'Š' => 'S',
        'š' => 's',
        'Ž' => 'Z',
        'ž' => 'z',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'A',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ø' => 'O',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'B',
        'ß' => 'ss',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'a',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'ø' => 'o',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'b',
        'ÿ' => 'y',
    ];

    public function getMinNgramLength(): int
    {
        return 2;
    }

    public function getMaxNgramLength(): int
    {
        return 4;
    }

    public function getCacheKeyGrams(): string
    {
        return 'ngram.grams.';
    }

    public function getCacheKeyScores(): string
    {
        return 'ngram.scores.';
    }

    public function getCacheKeyKeys(): string
    {
        return 'ngram.keys';
    }

    public function getCacheKeyRawData(): string
    {
        return 'dataset.raw';
    }

    public function getCacheKeyPreprocessed(): string
    {
        return 'dataset.preprocessed';
    }

    public function getCacheKeyNormalized(): string
    {
        return 'string.normalized.';
    }

    public function getMinLetterMatchPercentage(): int
    {
        return 30;
    }

    public function getMinLengthRatio(): float
    {
        return 0.5;
    }

    public function getMaxCandidates(): int
    {
        return 5;
    }

    public function getEarlyStopThreshold(): float
    {
        return 0.95;
    }

    public function getDiacritics(): array
    {
        return $this->diacritics;
    }

    public function getDefaultCacheTtl(): ?int
    {
        return 3600;
    }
}

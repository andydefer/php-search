<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Configs;

interface SearchConfigInterface
{
    public function getMinNgramLength(): int;
    public function getMaxNgramLength(): int;
    public function getCacheKeyGrams(): string;
    public function getCacheKeyScores(): string;
    public function getCacheKeyKeys(): string;
    public function getCacheKeyRawData(): string;
    public function getCacheKeyPreprocessed(): string;
    public function getCacheKeyNormalized(): string;
    public function getMinLetterMatchPercentage(): int;
    public function getMinLengthRatio(): float;
    public function getMaxCandidates(): int;
    public function getEarlyStopThreshold(): float;
    public function getDiacritics(): array;
    public function getDefaultCacheTtl(): ?int;
}

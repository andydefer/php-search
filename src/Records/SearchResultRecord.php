<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

/**
 * Record pour stocker les résultats de recherche.
 *
 * @author Andy Defer
 */
final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $session_id,
        public readonly string $file_path,
        public readonly int $line_number,
        public readonly StrictDataObject $data,
        public readonly float $score,
        public readonly float $max_possible,
        public readonly float $percentage,
        public readonly int $timestamp,
    ) {}
}

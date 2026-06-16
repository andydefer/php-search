<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Record pour stocker un item dans l'index.
 *
 * @author Andy Defer
 */
final class IndexedItemRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $original_text,
        public readonly string $source,
        public readonly int $line,
        public readonly string $field,

        public readonly StringTypedCollection $item_words,
    ) {}
}

<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;

/**
 * Port for memoizing the result of a full parse+traverse of one SourceCode,
 * keyed by the SourceCode object. Implementations should be non-owning (e.g. a
 * WeakMap) so a cached entry is reclaimed when its SourceCode is no longer
 * referenced, bounding memory across many scanned files.
 */
interface ParsedSourceCacheInterface
{
    public function has(SourceCodeInterface $code): bool;

    /** @return list<LiteralInterface>|null */
    public function get(SourceCodeInterface $code): ?array;

    /** @param list<LiteralInterface> $literals */
    public function put(SourceCodeInterface $code, array $literals): void;
}

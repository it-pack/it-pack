<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;

/**
 * Port for memoizing the result of a full parse+traverse of one SourceCode,
 * keyed by content identity rather than object identity (DisenchantIt wraps
 * the same source string in a fresh SourceCode per entry point). Implementations
 * must be bounded — see DEFAULT_MAX_ENTRIES — so memory stays proportional to
 * the cap even when one detector scans many distinct sources.
 */
interface ParsedSourceCacheInterface
{
    /**
     * Default maximum number of distinct sources held at once. Implementations
     * evict deterministically (the oldest insertion first) when at capacity.
     */
    public const int DEFAULT_MAX_ENTRIES = 32;

    public function has(SourceCodeInterface $code): bool;

    /** @return list<LiteralInterface>|null */
    public function get(SourceCodeInterface $code): ?array;

    /** @param list<LiteralInterface> $literals */
    public function put(SourceCodeInterface $code, array $literals): void;
}

<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ParsedSourceCacheInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;

/**
 * Scan-result cache keyed by the *content identity* of a SourceCode, not by
 * object identity. DisenchantIt creates a fresh SourceCode object per public
 * entry point (detect → rewrite → rewriteWithSemConv all wrap the same source
 * string in a new SourceCode), so a WeakMap keyed by object identity never
 * hits across those calls and the (expensive) parse+traverse runs three times.
 * Keying by `SourceCode::identity()` — a stable content hash — collapses all
 * three into exactly one parse per distinct source string.
 *
 * Entries persist for the lifetime of the owning Detector. That is bounded by
 * the number of distinct sources scanned through that Detector, which for a
 *   single Disenchanter (one file) is one — so the original weak-reference GC
 *   guarantee is not needed here.
 */
final class ParsedSourceCache implements ParsedSourceCacheInterface
{
    /** @var array<string, list<LiteralInterface>> */
    private array $store = [];

    public function has(SourceCodeInterface $code): bool
    {
        return isset($this->store[$code->identity()]);
    }

    /** @return list<LiteralInterface>|null */
    public function get(SourceCodeInterface $code): ?array
    {
        return $this->store[$code->identity()] ?? null;
    }

    /** @param list<LiteralInterface> $literals */
    public function put(SourceCodeInterface $code, array $literals): void
    {
        $this->store[$code->identity()] = $literals;
    }
}

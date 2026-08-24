<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ParsedSourceCacheInterface;
use ItPack\DisenchantIt\Exception\InvalidCacheConfigurationException;
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
 * The store is **bounded**: at most `maxEntries` distinct sources are held for
 * the lifetime of the owning Detector. When the bound is reached, the oldest
 * inserted entry (deterministic FIFO) is evicted before a new one is stored;
 * re-putting an already-cached key never evicts. This keeps memory proportional
 * to `maxEntries` even when one Detector scans many distinct sources, while a
 * single Disenchanter run (one file) always fits in the default cap.
 */
final class ParsedSourceCache implements ParsedSourceCacheInterface
{
    public const DEFAULT_MAX_ENTRIES = 32;

    /** @var array<string, list<LiteralInterface>> */
    private array $store = [];

    public function __construct(private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES)
    {
        if ($this->maxEntries < 1) {
            throw new InvalidCacheConfigurationException($this->maxEntries);
        }
    }

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
        if (!isset($this->store[$code->identity()])) {
            while (count($this->store) >= $this->maxEntries) {
                unset($this->store[array_key_first($this->store)]);
            }
        }

        $this->store[$code->identity()] = $literals;
    }
}

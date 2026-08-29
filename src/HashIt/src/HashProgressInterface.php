<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Progress sink for streaming / bulk hashing operations. Implemented by callers
 * who want to observe byte-level progress (CLI bars, async notifications) without
 * the core coupling to any logging or messaging library.
 *
 * The core only calls {@see HashProgressInterface::onBytes()}; all rendering lives
 * in the adapter that implements this port. A ready-made callable-backed
 * implementation is available as {@see HashProgress}.
 */
interface HashProgressInterface
{
    /**
     * @param int $processed bytes hashed so far (cumulative across the operation)
     * @param int|null $total total bytes to hash, or null when not yet known
     */
    public function onBytes(int $processed, ?int $total): void;
}

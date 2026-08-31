<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

/**
 * An in-progress, pausable hash. Produced by
 * {@see ResumableHashingInterface::start()} and rebuildable from a checkpoint via
 * {@see ResumableHashingInterface::resume()}.
 *
 * The handle is mutable: {@see self::update()} appends bytes, {@see self::checkpoint()}
 * snapshots the current state (the live handle keeps working afterwards), and
 * {@see self::finalize()} consumes the state to produce the digest.
 */
interface ResumableHashInterface
{
    /**
     * Append $data to the in-progress hash.
     */
    public function update(string $data): void;

    /**
     * Return a portable checkpoint of the current state. The blob can be persisted
     * and later resumed; the live handle remains usable afterwards.
     */
    public function checkpoint(): string;

    /**
     * Finalize and return the digest. Consumes the state (further calls fail).
     */
    public function finalize(): HashInterface;
}

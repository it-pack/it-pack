<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use InvalidArgumentException;
use ItPack\HashIt\HashAlgorithm;

/**
 * Pausable (resumable) hashing. Begin a streaming hash with {@see self::start()}
 * and, at any point, snapshot it with {@see ResumableHashInterface::checkpoint()}
 * to persist; later rebuild the exact same in-progress state with
 * {@see self::resume()} — even in a different process.
 *
 * Checkpoints are only available for plain algorithms: keyed / HMAC contexts
 * cannot be serialized by the native hash extension (see docs/ADR-0002), so
 * {@see ResumableHashInterface::checkpoint()} throws for them.
 */
interface ResumableHashingInterface
{
    /**
     * Begin a resumable hash for $algorithm.
     *
     * @param string|HashAlgorithm $algorithm plain algorithm (HMAC is unsupported)
     * @param bool $binary when true, {@see ResumableHashInterface::finalize()} returns the raw digest
     */
    public function start(string|HashAlgorithm $algorithm = HashAlgorithm::SHA256, bool $binary = false): ResumableHashInterface;

    /**
     * Resume a previously checkpointed hash. The checkpoint envelope carries the
     * algorithm and binary flag, so they need not be supplied again.
     *
     * @throws InvalidArgumentException when $checkpoint is not a valid checkpoint
     */
    public function resume(string $checkpoint): ResumableHashInterface;
}

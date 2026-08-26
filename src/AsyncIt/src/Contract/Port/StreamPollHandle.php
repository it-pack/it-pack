<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

use InvalidArgumentException;

/**
 * Opaque handle for one registered poller watcher.
 *
 * A handle is the only token a task holds for a registered OS-level readiness
 * watcher. It MUST be released (via {@see PollerPortInterface::release()}) on
 * terminal transition; a retained handle is a resource leak detected by
 * conformance Assertion C.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final readonly class StreamPollHandle
{
    public function __construct(public string $id)
    {
        if ($id === '') {
            throw new InvalidArgumentException('StreamPollHandle id must not be empty.');
        }
    }

    public function __toString(): string
    {
        return $this->id;
    }
}

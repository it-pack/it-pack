<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

/**
 * Strategy applied when the artifact buffer is full.
 *
 *  - `DropNewest`      discards the incoming artifact.
 *  - `DropOldest`      discards the oldest buffered artifact to make room.
 *  - `DropLowPriority` discards the lowest-priority artifact (oldest low-priority, or the
 *                      incoming one when it is low priority); high-priority artifacts win.
 *  - `BlockWithTimeout` waits up to a timeout for space, then drops newest.
 *  - `Fail`            throws, letting the surrounding failure policy decide.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum OverflowStrategy: string
{
    case DropNewest = 'drop_newest';
    case DropOldest = 'drop_oldest';
    case DropLowPriority = 'drop_low_priority';
    case BlockWithTimeout = 'block_with_timeout';
    case Fail = 'fail';
}

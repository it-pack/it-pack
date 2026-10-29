<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\TaskChannelInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;

/**
 * Bundle of the three adapter-backed primitives assembled by a
 * {@see RuntimeAdapterAssemblerInterface} for a given {@see RuntimeAdapterKind}.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RuntimeAssembly
{
    public function __construct(
        public PollerPortInterface $poller,
        public ContextStorageInterface $storage,
        public TaskChannelInterface $channel,
    ) {
    }
}

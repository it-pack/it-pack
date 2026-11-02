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
final readonly class RuntimeAssembly implements RuntimeAssemblyInterface
{
    public function __construct(
        public PollerPortInterface $poller,
        public ContextStorageInterface $storage,
        public TaskChannelInterface $channel,
    ) {
    }

    public function poller(): PollerPortInterface
    {
        return $this->poller;
    }

    public function storage(): ContextStorageInterface
    {
        return $this->storage;
    }

    public function channel(): TaskChannelInterface
    {
        return $this->channel;
    }
}

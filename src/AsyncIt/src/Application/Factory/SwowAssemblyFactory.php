<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Adapter\Swow\SwowChannel;
use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowPoller;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;

/**
 * Assembles the Swow runtime's poller, context storage, and channel.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class SwowAssemblyFactory implements RuntimeAssemblyFactoryInterface
{
    public function __construct(
        private readonly FiberScopeSlotFactoryInterface $slotFactory,
    ) {
    }

    public function create(CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        return new RuntimeAssembly(
            new SwowPoller(),
            new SwowCoroutineContextStorage($this->slotFactory),
            new SwowChannel(new \Swow\Coroutine\Channel()),
        );
    }
}

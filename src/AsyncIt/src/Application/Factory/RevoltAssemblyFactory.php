<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use Fiber;
use ItPack\AsyncIt\Adapter\Revolt\RevoltChannel;
use ItPack\AsyncIt\Adapter\Revolt\RevoltFiberContextStorage;
use ItPack\AsyncIt\Adapter\Revolt\RevoltPoller;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\Domain\FiberScopeSlot;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use WeakMap;

/**
 * Assembles the Revolt runtime's poller, context storage, and channel.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final readonly class RevoltAssemblyFactory implements RuntimeAssemblyFactoryInterface
{
    public function __construct(
        private FiberScopeSlotFactoryInterface $slotFactory,
    ) {
    }

    public function create(CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlot> $perFiberMap */
        $perFiberMap = new WeakMap();

        return new RuntimeAssembly(
            new RevoltPoller(),
            new RevoltFiberContextStorage($perFiberMap, $this->slotFactory),
            new RevoltChannel(new EmulatedChannel()),
        );
    }
}

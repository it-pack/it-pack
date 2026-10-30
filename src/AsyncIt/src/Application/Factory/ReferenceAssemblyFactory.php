<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;

/**
 * Assembles the reference runtime's poller, context storage, and channel.
 *
 * The reference assembly uses the synchronous {@see MemoryContextStorage},
 * {@see EmulatedChannel}, and a {@see PollerFactory}-selected poller (native
 * `Io\Poll` or `SelectPoller` fallback). This is the zero-dependency path for
 * environments without Swoole, Swow, or Revolt.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final readonly class ReferenceAssemblyFactory implements RuntimeAssemblyFactoryInterface
{
    public function __construct(
        private PollerFactoryInterface $pollerFactory = new PollerFactory(),
    ) {
    }

    public function create(CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        return new RuntimeAssembly(
            $this->pollerFactory->create($descriptor),
            new MemoryContextStorage(),
            new EmulatedChannel(),
        );
    }
}

<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Adapter\Swoole\SwooleChannel;
use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use Swoole\Coroutine\Channel;

/**
 * Assembles the Swoole runtime's poller, context storage, and channel.
 *
 * Lives in {@see \ItPack\AsyncIt\Application\Factory} so it can depend on the
 * Swoole adapter concretes (Application → Adapter direction is permitted).
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final readonly class SwooleAssemblyFactory implements RuntimeAssemblyFactoryInterface
{
    public function __construct(
        private FiberScopeSlotFactoryInterface $slotFactory,
    ) {
    }

    public function create(CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        return new RuntimeAssembly(
            new SwoolePoller(),
            new SwooleCoroutineContextStorage($this->slotFactory),
            new SwooleChannel(new Channel()),
        );
    }
}

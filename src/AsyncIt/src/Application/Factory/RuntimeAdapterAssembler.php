<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Adapter\Revolt\RevoltChannel;
use ItPack\AsyncIt\Adapter\Revolt\RevoltFiberContextStorage;
use ItPack\AsyncIt\Adapter\Revolt\RevoltPoller;
use ItPack\AsyncIt\Adapter\Swoole\SwooleChannel;
use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Adapter\Swow\SwowChannel;
use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowPoller;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Domain\Channel\EmulatedChannel;
use ItPack\AsyncIt\Domain\FiberScopeSlot;

/**
 * Concrete assembler that instantiates the runtime-specific poller, context
 * storage, and channel backends.
 *
 * This class lives in `Application\Factory`, which is permitted to depend on
 * `Adapter\*`. The SDK composition root depends only on
 * {@see RuntimeAdapterAssemblerInterface}, keeping adapter concretes out of
 * the SDK layer.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class RuntimeAdapterAssembler implements RuntimeAdapterAssemblerInterface
{
    public function assemble(RuntimeAdapterKind $kind, CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        return new RuntimeAssembly(
            $this->buildPoller($kind, $descriptor),
            $this->buildStorage($kind),
            $this->buildChannel($kind),
        );
    }

    private function buildPoller(
        RuntimeAdapterKind $kind,
        CapabilityDescriptor $descriptor
    ): \ItPack\AsyncIt\Contract\PollerPortInterface {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwoolePoller(),
            RuntimeAdapterKind::Swow => new SwowPoller(),
            RuntimeAdapterKind::Revolt => new RevoltPoller(),
            RuntimeAdapterKind::Reference => new PollerFactory()->create($descriptor),
        };
    }

    private function buildStorage(RuntimeAdapterKind $kind): \ItPack\RunIt\Contract\Port\ContextStorageInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwooleCoroutineContextStorage(new FiberScopeSlotFactory()),
            RuntimeAdapterKind::Swow => new SwowCoroutineContextStorage(new FiberScopeSlotFactory()),
            RuntimeAdapterKind::Revolt => new RevoltFiberContextStorage(
                $this->revoltPerFiberMap(),
                new FiberScopeSlotFactory(),
            ),
            RuntimeAdapterKind::Reference => new MemoryContextStorage(),
        };
    }

    /**
     * Per-Fiber WeakMap backing {@see RevoltFiberContextStorage}.
     *
     * @return \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlot>
     */
    private function revoltPerFiberMap(): \WeakMap
    {
        /** @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlot> $map */
        $map = new \WeakMap();

        return $map;
    }

    private function buildChannel(RuntimeAdapterKind $kind): \ItPack\AsyncIt\Contract\TaskChannelInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwooleChannel(new \Swoole\Coroutine\Channel()),
            RuntimeAdapterKind::Swow => new SwowChannel(new \Swow\Coroutine\Channel()),
            RuntimeAdapterKind::Revolt => new RevoltChannel(new EmulatedChannel()),
            RuntimeAdapterKind::Reference => new EmulatedChannel(),
        };
    }
}

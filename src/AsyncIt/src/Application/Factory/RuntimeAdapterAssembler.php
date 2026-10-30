<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;

/**
 * Concrete assembler that dispatches to per-runtime factory classes.
 *
 * Each runtime adapter package owns a {@see RuntimeAssemblyFactoryInterface}
 * implementation that constructs its poller, context-storage, and channel.
 * This facade selects the correct one, keeping adapter concretes out of the
 * SDK composer root while avoiding a 23-way direct coupling.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class RuntimeAdapterAssembler implements RuntimeAdapterAssemblerInterface
{
    private readonly FiberScopeSlotFactoryInterface $slotFactory;

    /**
     * @param array<RuntimeAdapterKind, RuntimeAssemblyFactoryInterface> $factories
     */
    public function __construct(
        private readonly array $factories = [],
        ?FiberScopeSlotFactoryInterface $slotFactory = null,
    ) {
        $this->slotFactory = $slotFactory ?? new FiberScopeSlotFactory();
    }

    public function assemble(RuntimeAdapterKind $kind, CapabilityDescriptor $descriptor): RuntimeAssembly
    {
        $factory = $this->factories[$kind->value]
            ?? $this->defaultFactory($kind);

        return $factory->create($descriptor);
    }

    private function defaultFactory(RuntimeAdapterKind $kind): RuntimeAssemblyFactoryInterface
    {
        return match ($kind) {
            RuntimeAdapterKind::Swoole => new SwooleAssemblyFactory($this->slotFactory),
            RuntimeAdapterKind::Swow => new SwowAssemblyFactory($this->slotFactory),
            RuntimeAdapterKind::Revolt => new RevoltAssemblyFactory($this->slotFactory),
            RuntimeAdapterKind::Reference => new ReferenceAssemblyFactory(),
        };
    }
}

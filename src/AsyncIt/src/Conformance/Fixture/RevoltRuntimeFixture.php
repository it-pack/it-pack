<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Revolt\RevoltFiberContextStorage;
use ItPack\AsyncIt\Adapter\Revolt\RevoltPoller;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;

/**
 * Conformance fixture exercising the Revolt runtime adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
 */
final class RevoltRuntimeFixture extends AbstractRuntimeFixture
{
    public function __construct()
    {
        /** @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, \ItPack\AsyncIt\Domain\FiberScopeSlot> $perFiber */
        $perFiber = new \WeakMap();

        parent::__construct(new RevoltPoller(), new RevoltFiberContextStorage($perFiber, new FiberScopeSlotFactory()), new RevoltPoller());
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Revolt';
    }
}

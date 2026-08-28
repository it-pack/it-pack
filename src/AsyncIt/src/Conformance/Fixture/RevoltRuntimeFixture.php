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
        parent::__construct(new RevoltPoller(), new RevoltFiberContextStorage(new \WeakMap(), new FiberScopeSlotFactory()), new RevoltPoller());
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Revolt';
    }
}

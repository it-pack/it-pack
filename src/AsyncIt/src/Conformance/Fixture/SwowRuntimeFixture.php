<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowPoller;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;

/**
 * Conformance fixture exercising the Swow runtime adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 */
final class SwowRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    use RuntimeFixtureTrait;

    public function __construct()
    {
        $this->initRuntimeFixture(
            new SwowPoller(),
            new SwowCoroutineContextStorage(new FiberScopeSlotFactory()),
            new SwowPoller(),
        );
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Swow';
    }
}

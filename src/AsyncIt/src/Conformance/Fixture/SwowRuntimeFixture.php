<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Swow\SwowCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swow\SwowPoller;

/**
 * Conformance fixture exercising the Swow runtime adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
 */
final class SwowRuntimeFixture extends AbstractRuntimeFixture
{
    public function __construct()
    {
        parent::__construct(new SwowPoller(), new SwowCoroutineContextStorage(), new SwowPoller());
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Swow';
    }
}

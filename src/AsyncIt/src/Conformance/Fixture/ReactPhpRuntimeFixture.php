<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Interop\ReactPhp\ReactPhpPollerBridge;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;

/**
 * Conformance fixture exercising the ReactPHP interop poller bridge.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#poller-interop-reactphp
 */
final class ReactPhpRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    use RuntimeFixtureTrait;

    public function __construct(object $loop)
    {
        $this->initRuntimeFixture(
            new ReactPhpPollerBridge($loop),
            new MemoryContextStorage(),
            new ReactPhpPollerBridge($loop),
        );
    }

    public function portName(): string
    {
        return 'AsyncRuntime/ReactPhp';
    }
}

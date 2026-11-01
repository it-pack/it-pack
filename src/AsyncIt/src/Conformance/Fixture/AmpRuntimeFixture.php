<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Interop\Amp\AmpPollerBridge;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;

/**
 * Conformance fixture exercising the Amp interop poller bridge.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#poller-interop-amp
 */
final class AmpRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    use RuntimeFixtureTrait;

    public const string PORT_NAME = 'AsyncRuntime/Amp';

    public function __construct()
    {
        $this->initRuntimeFixture(new AmpPollerBridge(), new MemoryContextStorage(), new AmpPollerBridge());
    }

    public function portName(): string
    {
        return self::PORT_NAME;
    }
}

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
final class AmpRuntimeFixture extends AbstractRuntimeFixture
{
    public function __construct()
    {
        parent::__construct(new AmpPollerBridge(), new MemoryContextStorage(), new AmpPollerBridge());
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Amp';
    }
}

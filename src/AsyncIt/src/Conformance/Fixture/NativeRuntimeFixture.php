<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Poller\NativePoller;

/**
 * Conformance fixture exercising the native `Io\Poll` poller adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#poller-native
 */
final class NativeRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    use RuntimeFixtureTrait;

    public const string PORT_NAME = 'AsyncRuntime/Native';

    public function __construct()
    {
        $nativeClass = 'Io\\Poll';
        /** @var \Io\Poll $native */
        $native = new $nativeClass();

        $this->initRuntimeFixture(new NativePoller($native), new MemoryContextStorage(), new NativePoller($native));
    }

    public function portName(): string
    {
        return self::PORT_NAME;
    }
}

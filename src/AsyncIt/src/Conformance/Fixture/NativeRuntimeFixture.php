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
final class NativeRuntimeFixture extends AbstractRuntimeFixture
{
    public function __construct()
    {
        $nativeClass = 'Io\\Poll';
        /** @var \Io\Poll $native */
        $native = new $nativeClass();

        parent::__construct(new NativePoller($native), new MemoryContextStorage(), new NativePoller($native));
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Native';
    }
}

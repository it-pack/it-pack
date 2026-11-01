<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;

/**
 * Conformance fixture exercising the Swoole runtime adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 */
final class SwooleRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    use RuntimeFixtureTrait;

    public const string PORT_NAME = 'AsyncRuntime/Swoole';

    public function __construct()
    {
        $this->initRuntimeFixture(
            new SwoolePoller(),
            new SwooleCoroutineContextStorage(new FiberScopeSlotFactory()),
            new SwoolePoller(),
        );
    }

    public function portName(): string
    {
        return self::PORT_NAME;
    }
}

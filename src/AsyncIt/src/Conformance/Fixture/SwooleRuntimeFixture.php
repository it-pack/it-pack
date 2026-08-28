<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Adapter\Swoole\SwooleCoroutineContextStorage;
use ItPack\AsyncIt\Application\Factory\FiberScopeSlotFactory;
use ItPack\AsyncIt\Adapter\Swoole\SwoolePoller;

/**
 * Conformance fixture exercising the Swoole runtime adapter.
 *
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 */
final class SwooleRuntimeFixture extends AbstractRuntimeFixture
{
    public function __construct()
    {
        parent::__construct(new SwoolePoller(), new SwooleCoroutineContextStorage(new FiberScopeSlotFactory()), new SwoolePoller());
    }

    public function portName(): string
    {
        return 'AsyncRuntime/Swoole';
    }
}

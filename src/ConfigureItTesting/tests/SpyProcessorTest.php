<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureItTesting\Fake\InMemoryGraph;
use ItPack\ConfigureItTesting\Fake\SpyProcessor;
use PHPUnit\Framework\TestCase;

final class SpyProcessorTest extends TestCase
{
    public function testRecordsEveryProcessedGraph(): void
    {
        $spy = new SpyProcessor();
        $a = new InMemoryGraph();
        $b = new InMemoryGraph();

        self::assertSame($a, $spy->process($a));
        self::assertSame($b, $spy->process($b));

        self::assertSame(2, $spy->timesCalled());
        self::assertSame([$a, $b], $spy->processed());
        self::assertSame($b, $spy->lastGraph());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use PHPUnit\Framework\TestCase;

final class LazyTelemetryBridgeTest extends TestCase
{
    public function testRecorderIsDeferredUntilFirstRecord(): void
    {
        $calls = 0;
        $bridge = new LazyTelemetryBridge(
            static function () use (&$calls): ArrayTelemetryRecorder {
                ++$calls;

                return new ArrayTelemetryRecorder();
            },
        );

        self::assertFalse($bridge->isInitialized());
        self::assertSame(0, $calls);

        $bridge->record('boot', ['adapter' => 'swoole']);

        self::assertTrue($bridge->isInitialized());
        self::assertSame(1, $calls);
    }

    public function testEventsAreCaptured(): void
    {
        $bridge = new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );

        $bridge->record('a', ['x' => 1]);
        $bridge->record('b', ['y' => 2]);

        $events = $bridge->events();
        self::assertCount(2, $events);
        self::assertSame('a', $events[0]['event']);
        self::assertSame('b', $events[1]['event']);
    }
}

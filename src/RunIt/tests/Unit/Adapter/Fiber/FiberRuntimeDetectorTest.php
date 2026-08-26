<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Fiber;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Fiber\FiberRuntimeDetector;

/**
 * M11.2 — Fiber-only runtime detection.
 */
final class FiberRuntimeDetectorTest extends Unit
{
    public function testNotInCoroutineOnMainStack(): void
    {
        $detector = new FiberRuntimeDetector();

        self::assertFalse($detector->inCoroutine());
        self::assertFalse($detector->inFiber());
    }

    public function testDetectsFiberContext(): void
    {
        $detector = new FiberRuntimeDetector();
        $inside = null;

        $fiber = new Fiber(static function () use ($detector, &$inside): void {
            $inside = $detector->inFiber();
        });
        $fiber->start();

        self::assertTrue($inside);
    }
}

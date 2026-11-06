<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\SwooleRuntimeDetector;

use function Swoole\Coroutine\run;

/**
 * M11.4 / M11.2 — Swoole/OpenSwoole runtime detection.
 */
final class SwooleRuntimeDetectorTest extends Unit
{
    public function testNotInCoroutineOnMainStack(): void
    {
        $detector = new SwooleRuntimeDetector();

        self::assertFalse($detector->inCoroutine());
        self::assertFalse($detector->inFiber());
    }

    public function testDetectsCoroutineContext(): void
    {
        if (!function_exists('Swoole\Coroutine\run')) {
            $this->markTestSkipped(
                'Swoole\Coroutine\run does not exist'
            );
        }

        $detector = new SwooleRuntimeDetector();
        $inside = null;

        run(static function () use ($detector, &$inside): void {
            $inside = $detector->inCoroutine();
        });

        self::assertTrue($inside);
    }
}

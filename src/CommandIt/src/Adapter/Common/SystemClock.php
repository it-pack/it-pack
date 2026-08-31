<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Port\ClockInterface;

/**
 * System clock: wall-clock seconds via `time()` and monotonic nanoseconds via `hrtime()`.
 *
 * @api
 */
final readonly class SystemClock implements ClockInterface
{
    public function nowSeconds(): int
    {
        return \time();
    }

    public function monotonicNanoseconds(): int
    {
        return \hrtime(true);
    }
}

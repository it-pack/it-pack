<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior;

use Codeception\Test\Unit;
use ItPack\CommandIt\Behavior\Domain\TimestampedBehavior;

/**
 * @covers \ItPack\CommandIt\Behavior\Domain\TimestampedBehavior
 */
final class TimestampedBehaviorTest extends Unit
{
    public function testCountProjectsTimestamp(): void
    {
        $artifact = new class () {
            use TimestampedBehavior;

            public function timestamp(): int
            {
                return 1700000000;
            }
        };

        self::assertSame(1700000000, $artifact->count());
    }
}

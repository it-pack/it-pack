<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SequencedBehavior::class)]
final class SequencedBehaviorTest extends Unit
{
    public function testCountDelegatesToSequence(): void
    {
        $fixture = new class () {
            use SequencedBehavior;

            public function sequence(): int
            {
                return 7;
            }
        };

        self::assertSame(7, $fixture->count());
    }
}

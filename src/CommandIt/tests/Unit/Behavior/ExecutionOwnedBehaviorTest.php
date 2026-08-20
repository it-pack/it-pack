<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior;

use Codeception\Test\Unit;
use ItPack\CommandIt\Behavior\Domain\ExecutionOwnedBehavior;

/**
 * @covers \ItPack\CommandIt\Behavior\Domain\ExecutionOwnedBehavior
 */
final class ExecutionOwnedBehaviorTest extends Unit
{
    public function testBelongsToMatchesExecutionId(): void
    {
        $artifact = new class () {
            use ExecutionOwnedBehavior;

            public function executionId(): string
            {
                return 'exec-1';
            }
        };

        self::assertTrue($artifact->belongsTo('exec-1'));
        self::assertFalse($artifact->belongsTo('exec-2'));
    }
}

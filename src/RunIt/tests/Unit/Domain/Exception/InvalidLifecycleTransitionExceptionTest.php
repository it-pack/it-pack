<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Domain\Exception\InvalidLifecycleTransitionException;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;

/**
 * @group domain
 */
final class InvalidLifecycleTransitionExceptionTest extends Unit
{
    public function testImplementsMarkerInterfaces(): void
    {
        $exception = InvalidLifecycleTransitionException::forTransition(
            ExecutionStatus::Created,
            ExecutionStatus::Succeeded,
        );

        self::assertInstanceOf(InvalidValueExceptionInterface::class, $exception);
    }

    public function testForTransition(): void
    {
        $exception = InvalidLifecycleTransitionException::forTransition(
            ExecutionStatus::Created,
            ExecutionStatus::Succeeded,
        );

        $expected = 'Execution transition from "created" to "succeeded" is not allowed.';

        self::assertSame($expected, $exception->getMessage());
    }

    public function testForNonTerminalFailure(): void
    {
        $exception = InvalidLifecycleTransitionException::forNonTerminalFailure(ExecutionStatus::Running);

        $expected = 'Execution failure requires a terminal status, got "running".';

        self::assertSame($expected, $exception->getMessage());
    }
}

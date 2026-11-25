<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exception;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Exception\InvalidActivityTransitionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidActivityTransitionException::class)]
final class InvalidActivityTransitionExceptionTest extends Unit
{
    public function testForIllegalTarget(): void
    {
        $from = $this->createMock(ActivityStatusInterface::class);
        $from->method('label')->willReturn('running');
        $to = $this->createMock(ActivityStatusInterface::class);
        $to->method('label')->willReturn('created');

        $exception = InvalidActivityTransitionException::forIllegalTarget($from, $to);

        self::assertStringContainsString('running', $exception->getMessage());
        self::assertStringContainsString('created', $exception->getMessage());
    }

    public function testIsActivityStatus(): void
    {
        self::assertSame('running', ActivityStatus::Running->label());
    }
}

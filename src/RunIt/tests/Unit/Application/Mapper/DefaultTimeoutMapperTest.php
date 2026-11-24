<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mapper;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Mapper\DefaultTimeoutMapper;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Domain\TimeoutInterface;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DefaultTimeoutMapper::class)]
final class DefaultTimeoutMapperTest extends Unit
{
    public function testMapReturnsExecutionFailure(): void
    {
        $mapper = new DefaultTimeoutMapper();

        $timeout = $this->createMock(TimeoutInterface::class);
        $timeout->method('cause')->willReturn('operation timed out');
        $timeout->method('limit')->willReturn(
            $this->createMock(DurationInterface::class)
        );
        $timeout->method('elapsed')->willReturn(
            $this->createMock(DurationInterface::class)
        );
        $timeout->method('throwableDescription')->willReturn(null);

        $result = $mapper->map($timeout);

        self::assertInstanceOf(ExecutionFailure::class, $result);
        self::assertSame(ExecutionStatus::TimedOut, $result->status());
    }

    public function testMapWithThrowableDescription(): void
    {
        $mapper = new DefaultTimeoutMapper();

        $description = ThrowableDescription::create(
            RuntimeException::class,
            'custom cause',
            0,
            previous: null,
            trace: null,
        );

        $timeout = $this->createMock(TimeoutInterface::class);
        $timeout->method('cause')->willReturn('operation timed out');
        $timeout->method('limit')->willReturn(
            $this->createMock(DurationInterface::class)
        );
        $timeout->method('elapsed')->willReturn(
            $this->createMock(DurationInterface::class)
        );
        $timeout->method('throwableDescription')->willReturn($description);

        $result = $mapper->map($timeout);

        self::assertInstanceOf(ExecutionFailure::class, $result);
        self::assertSame(ExecutionStatus::TimedOut, $result->status());
    }
}

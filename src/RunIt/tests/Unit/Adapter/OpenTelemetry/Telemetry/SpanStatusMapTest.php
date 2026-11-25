<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Telemetry;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SpanStatusMap;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use OpenTelemetry\API\Trace\StatusCode;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SpanStatusMap::class)]
final class SpanStatusMapTest extends Unit
{
    public function testMapTerminalStatuses(): void
    {
        $map = new SpanStatusMap();

        $succeeded = $this->createMock(ExecutionStatusInterface::class);
        $succeeded->method('label')->willReturn('succeeded');
        self::assertSame(StatusCode::STATUS_OK, $map->map($succeeded)['code']);

        $failed = $this->createMock(ExecutionStatusInterface::class);
        $failed->method('label')->willReturn('failed');
        self::assertSame(StatusCode::STATUS_ERROR, $map->map($failed)['code']);

        $cancelled = $this->createMock(ExecutionStatusInterface::class);
        $cancelled->method('label')->willReturn('cancelled');
        self::assertSame(StatusCode::STATUS_ERROR, $map->map($cancelled)['code']);

        $timedOut = $this->createMock(ExecutionStatusInterface::class);
        $timedOut->method('label')->willReturn('timed_out');
        self::assertSame(StatusCode::STATUS_ERROR, $map->map($timedOut)['code']);
    }

    public function testMapNonTerminalIsUnset(): void
    {
        $map = new SpanStatusMap();

        $created = $this->createMock(ExecutionStatusInterface::class);
        $created->method('label')->willReturn('created');
        self::assertSame(StatusCode::STATUS_UNSET, $map->map($created)['code']);
    }

    public function testExecutionStatusCasesExist(): void
    {
        self::assertSame('created', ExecutionStatus::Created->value);
    }
}

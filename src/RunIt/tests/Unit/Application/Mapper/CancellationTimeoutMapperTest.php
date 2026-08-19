<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mapper;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Mapper\DefaultCancellationMapper;
use ItPack\RunIt\Application\Mapper\DefaultTimeoutMapper;
use ItPack\RunIt\Domain\Execution\Cancellation;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\Timeout;
use ItPack\RunIt\Domain\Shared\Duration;

final class CancellationTimeoutMapperTest extends Unit
{
    public function testCancellationMapsToCancelledFailure(): void
    {
        $mapper = new DefaultCancellationMapper();
        $cancellation = Cancellation::of('user aborted');

        $result = $mapper->map($cancellation);

        self::assertSame(ExecutionStatus::Cancelled, $result->status());
        self::assertNotNull($result->throwableDescription());
        self::assertSame('user aborted', $result->throwableDescription()->message());
        self::assertSame('user aborted', $cancellation->cause());
    }

    public function testTimeoutMapsToTimedOutFailure(): void
    {
        $mapper = new DefaultTimeoutMapper();
        $timeout = Timeout::of(Duration::ofSeconds(5), Duration::ofSeconds(7), 'deadline exceeded');

        $result = $mapper->map($timeout);

        self::assertSame(ExecutionStatus::TimedOut, $result->status());
        self::assertNotNull($result->throwableDescription());
        self::assertStringContainsString('deadline exceeded', $result->throwableDescription()->message());
    }
}

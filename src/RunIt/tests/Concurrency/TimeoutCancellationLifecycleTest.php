<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Lifecycle\ExecutionLifecycleCoordinator;
use ItPack\RunIt\Application\Mapper\DefaultCancellationMapper;
use ItPack\RunIt\Application\Mapper\DefaultTimeoutMapper;
use ItPack\RunIt\Domain\Execution\Cancellation;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\Timeout;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * Cooperative timeout and cancellation are runtime-adapter concerns: an engine or
 * worker observes the elapsed limit (or the consumer's stop signal) and classifies
 * the outcome through {@see DefaultTimeoutMapper} / {@see DefaultCancellationMapper}.
 *
 * These tests pin that full classification pipeline end to end — signal value object
 * → mapped failure result → coordinator transition → terminal aggregate record with
 * the exact status and preserved cause.
 */
final class TimeoutCancellationLifecycleTest extends Unit
{
    public function testTimeoutSignalClassifiesAsTimedOutThroughTheCoordinator(): void
    {
        $timeout = Timeout::of(Duration::ofSeconds(5), Duration::ofSeconds(9), 'deadline exceeded');

        $failure = new DefaultTimeoutMapper()->map($timeout);
        $terminal = $this->completeRunningExecution($failure);

        self::assertSame(ExecutionStatus::TimedOut, $terminal->status());
        self::assertStringContainsString(
            'limit 5000000000 ns',
            $terminal->result()?->throwableDescription()?->message() ?? '',
            'the timeout cause must name its limit',
        );
        self::assertStringContainsString(
            'elapsed 9000000000 ns',
            $terminal->result()?->throwableDescription()?->message() ?? '',
            'the timeout cause must name the elapsed time',
        );
    }

    public function testCancellationSignalClassifiesAsCancelledThroughTheCoordinator(): void
    {
        $cancellation = Cancellation::of('consumer requested stop');

        $failure = new DefaultCancellationMapper()->map($cancellation);
        $terminal = $this->completeRunningExecution($failure);

        self::assertSame(ExecutionStatus::Cancelled, $terminal->status());
        self::assertStringContainsString(
            'consumer requested stop',
            $terminal->result()?->throwableDescription()?->message() ?? '',
            'the cancellation cause is preserved verbatim',
        );
    }

    private function completeRunningExecution(\ItPack\RunIt\Contract\Domain\ExecutionResultInterface $failure): \ItPack\RunIt\Contract\Domain\ExecutionInterface
    {
        $running = Execution::start(
            ExecutionId::fromString('exec-ttl'),
            ExecutionDefinition::create(ExecutionName::fromString('worker.iteration'), ExecutionKind::fromString('worker')),
            Instant::of(1000),
        );

        return new ExecutionLifecycleCoordinator()
            ->transition($running, $failure, Instant::of(9000))
        ;
    }
}

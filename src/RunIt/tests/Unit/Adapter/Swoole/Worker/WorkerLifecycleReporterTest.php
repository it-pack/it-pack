<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole\Worker;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\Worker\WorkerLifecycleReporter;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M11.7 — worker lifecycle reporting.
 */
final class WorkerLifecycleReporterTest extends Unit
{
    public function testStartStopAndFailureEmitWorkerOccurrences(): void
    {
        $kit = new RunItTestKit();
        $reporter = WorkerLifecycleReporter::create($kit->runner);

        $reporter->start();
        $reporter->stop();
        $reporter->failure(new RuntimeException('worker died'));

        $types = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof OccurrenceInterface) {
                $types[] = $artifact->type()->toString();
            }
        }

        self::assertContains('itpack.worker.started.v1', $types);
        self::assertContains('itpack.worker.stopped.v1', $types);
        self::assertContains('itpack.worker.failed.v1', $types);
    }

    public function testResetInvokesProvidedHooks(): void
    {
        $kit = new RunItTestKit();
        $resetCalled = false;
        $flushCalled = false;

        $reporter = WorkerLifecycleReporter::create(
            $kit->runner,
            static function () use (&$resetCalled): void {
                $resetCalled = true;
            },
            static function () use (&$flushCalled): void {
                $flushCalled = true;
            },
        );

        $reporter->reset();

        self::assertTrue($resetCalled, 'reset hook must run on reset()');
        self::assertTrue($flushCalled, 'flush hook must run on reset()');
    }

    public function testWorkerLifecycleIsSeparateFromRequestExecutions(): void
    {
        $kit = new RunItTestKit();
        $reporter = WorkerLifecycleReporter::create($kit->runner);

        $reporter->start();
        $reporter->stop();
        $reporter->failure(new RuntimeException('worker died'));

        $executionIds = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof \ItPack\RunIt\Contract\Domain\ExecutionInterface) {
                $executionIds[$artifact->id()->toString()] = true;
            }
        }

        // each lifecycle event is its own execution, independent of any request
        self::assertCount(3, $executionIds, 'start, stop, and failure are three separate executions');
    }
}

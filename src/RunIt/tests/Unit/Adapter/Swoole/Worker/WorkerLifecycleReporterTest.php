<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole\Worker;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\Http\ServerExecutionMiddleware;
use ItPack\RunIt\Adapter\Swoole\Worker\WorkerLifecycleReporter;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\RunItTestKit;
use Throwable;

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

    public function testFailureSupportsRecorderWithDifferentParameterNames(): void
    {
        $observation = self::createStub(ObservationInterface::class);
        $recorder = new class ($observation) implements ObservationRecorderInterface {
            /** @var list<mixed> */
            public array $arguments = [];

            public function __construct(private readonly ObservationInterface $observation)
            {
            }

            public function record(
                string $text,
                ?ObservationSeverityInterface $level = null,
                ?AttributeCollectionInterface $data = null,
                ?Throwable $error = null,
                ?string $code = null,
            ): ObservationInterface {
                $this->arguments = [$text, $level, $data, $error, $code];

                return $this->observation;
            }
        };
        $scope = self::createStub(ExecutionScopeInterface::class);
        $scope->method('observations')->willReturn($recorder);
        $runner = self::createStub(ExecutionRunnerInterface::class);
        $runner->method('run')->willReturnCallback(
            static fn ($definition, callable $operation): mixed => $operation($scope),
        );
        $error = new RuntimeException('worker died');

        WorkerLifecycleReporter::create($runner)->failure($error);

        self::assertSame(['Worker failed: worker died', null, null, $error, 'worker.failure'], $recorder->arguments);

        $middleware = new ServerExecutionMiddleware(
            $runner,
            ExecutionName::fromString('http.request'),
            ExecutionKind::fromString('http.request'),
        );
        $response = new stdClass();
        self::assertSame($response, $middleware->handle(new stdClass(), static fn (): object => $response));
        self::assertSame(['HTTP request handled', null, null, null, 'http.request'], $recorder->arguments);
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
            if ($artifact instanceof ExecutionInterface) {
                $executionIds[$artifact->id()->toString()] = true;
            }
        }

        // each lifecycle event is its own execution, independent of any request
        self::assertCount(3, $executionIds, 'start, stop, and failure are three separate executions');
    }
}

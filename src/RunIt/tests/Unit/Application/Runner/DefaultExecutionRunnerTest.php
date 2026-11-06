<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Throwable;

final class DefaultExecutionRunnerTest extends Unit
{
    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch'));
    }

    public function testReturnsOperationResultUnchangedOnSuccess(): void
    {
        $kit = new RunItTestKit();

        $result = $kit->runner->run($this->definition(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testActivityOnlyModeWithoutCurrentExecutionRunsAFullExecution(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new ActivityOnlyPolicy());

        $result = $kit->runner->run($this->definition(), static fn (): string => 'root');

        self::assertSame('root', $result);
        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Succeeded, $executions[0]->status());
    }

    public function testActivityOnlyModeInsideAnExecutionDelegatesToTheActivityScope(): void
    {
        $kit = new RunItTestKit(nestedPolicy: new ActivityOnlyPolicy());
        $innerResult = null;

        $outer = $kit->runner->run($this->definition(), static function () use ($kit, &$innerResult): string {
            /** @var string $inner */
            $inner = $kit->runner->run(
                ExecutionDefinition::create(ExecutionName::fromString('inner'), ExecutionKind::fromString('step')),
                static fn (): string => 'activity-result',
            );
            $innerResult = $inner;

            return 'outer';
        });

        self::assertSame('outer', $outer);
        self::assertSame('activity-result', $innerResult);
    }

    public function testSuccessfulExecutionReachesSucceededStatusExactlyOnce(): void
    {
        $kit = new RunItTestKit();

        $kit->runner->run($this->definition(), static fn (): int => 42);

        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Succeeded, $executions[0]->status());
        self::assertTrue($executions[0]->status()->isTerminal());

        $snapshots = $this->snapshots($kit);
        self::assertCount(1, $snapshots);
    }

    public function testRethrowsExactOriginalThrowable(): void
    {
        $kit = new RunItTestKit();
        $thrown = new RuntimeException('boom');

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught, 'The original throwable must be rethrown.');
        self::assertSame('boom', $caught->getMessage());
    }

    public function testFailedExecutionReachesFailedStatus(): void
    {
        $kit = new RunItTestKit();

        try {
            $kit->runner->run($this->definition(), static function (): never {
                throw new RuntimeException('kaboom');
            });
        } catch (RuntimeException) {
        }

        $executions = $this->executions($kit);
        self::assertCount(1, $executions);
        self::assertSame(ExecutionStatus::Failed, $executions[0]->status());
        self::assertNotNull($executions[0]->result()?->throwableDescription());
        self::assertSame('kaboom', $executions[0]->result()->throwableDescription()->message());
    }

    public function testContextIsRestoredAfterExecution(): void
    {
        $kit = new RunItTestKit();

        $kit->runner->run($this->definition(), static fn (): int => 1);

        self::assertNull($kit->storage->current(), 'The ambient execution scope must be detached after completion.');
    }

    public function testPublicationFailureCannotReplaceBusinessResult(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result, 'A publication failure must not replace the operation result.');
    }

    public function testPublicationFailureCannotReplaceBusinessException(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());
        $thrown = new RuntimeException('business-error');

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught);
        self::assertSame('business-error', $caught->getMessage());
    }

    public function testPublicationFailureIsForwardedToHealthCallback(): void
    {
        $captured = null;
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result, 'The business result must be preserved.');
        self::assertInstanceOf(
            Throwable::class,
            $captured,
            'The health callback must receive the publication failure.'
        );
        self::assertSame('publisher down', $captured->getMessage());
    }

    /**
     * @return list<ExecutionInterface>
     */
    private function executions(RunItTestKit $kit): array
    {
        $executions = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof ExecutionInterface && $artifact->status()->isTerminal()) {
                $executions[] = $artifact;
            }
        }

        return $executions;
    }

    /**
     * @return list<SnapshotInterface>
     */
    private function snapshots(RunItTestKit $kit): array
    {
        $snapshots = [];
        foreach ($kit->artifacts() as $artifact) {
            if ($artifact instanceof SnapshotInterface) {
                $snapshots[] = $artifact;
            }
        }

        return $snapshots;
    }
}

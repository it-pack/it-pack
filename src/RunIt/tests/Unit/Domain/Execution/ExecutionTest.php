<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Shared\Instant;

final class ExecutionTest extends Unit
{
    public function testTerminalStatusesAreReported(): void
    {
        self::assertFalse(ExecutionStatus::Created->isTerminal());
        self::assertFalse(ExecutionStatus::Running->isTerminal());
        self::assertTrue(ExecutionStatus::Succeeded->isTerminal());
        self::assertTrue(ExecutionStatus::Failed->isTerminal());
        self::assertTrue(ExecutionStatus::Cancelled->isTerminal());
        self::assertTrue(ExecutionStatus::TimedOut->isTerminal());
    }

    public function testSuccessResultHasNoThrowable(): void
    {
        $success = ExecutionSuccess::create();

        self::assertSame(ExecutionStatus::Succeeded, $success->status());
        self::assertNull($success->throwableDescription());
    }

    public function testFailureResultCarriesDescription(): void
    {
        $failure = ExecutionFailure::of(
            ExecutionStatus::Failed,
            \ItPack\RunIt\Domain\Shared\ThrowableDescription::create('RuntimeException', 'boom', 0, null, null),
        );

        self::assertSame(ExecutionStatus::Failed, $failure->status());
        self::assertNotNull($failure->throwableDescription());
    }

    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('import'),
            ExecutionKind::fromString('batch'),
            null,
            [],
            ExecutionContext::empty()->with(
                \ItPack\RunIt\Domain\Context\ContextEntry::public(
                    \ItPack\RunIt\Domain\Context\ContextKey::fromString('tenant.id'),
                    'acme',
                ),
            ),
        );
    }

    public function testStartProducesRunningExecution(): void
    {
        $execution = Execution::start(
            ExecutionId::fromString('exec-1'),
            $this->definition(),
            Instant::of(1000),
        );

        self::assertSame('exec-1', $execution->id()->toString());
        self::assertSame(ExecutionStatus::Running, $execution->status());
        self::assertSame(1000, $execution->timestamp());
        self::assertSame(1000, $execution->startedAt()->epochSeconds());
        self::assertNull($execution->endedAt());
        self::assertNull($execution->result());
        self::assertSame(0, $execution->sequence());
    }

    public function testCompleteReturnsNewTerminalExecution(): void
    {
        $running = Execution::start(ExecutionId::fromString('exec-1'), $this->definition(), Instant::of(1000));
        $done = $running->complete(ExecutionSuccess::create(), Instant::of(2000));

        self::assertSame(ExecutionStatus::Succeeded, $done->status());
        self::assertSame(2000, $done->endedAt()?->epochSeconds());
        self::assertNotNull($done->result());
        // original is unchanged
        self::assertSame(ExecutionStatus::Running, $running->status());
    }

    public function testNormalizationIsDeterministic(): void
    {
        $execution = Execution::start(ExecutionId::fromString('exec-1'), $this->definition(), Instant::of(1000));

        $expected = [
            'id' => 'exec-1',
            'name' => 'import',
            'kind' => 'batch',
            'status' => 'running',
            'started_at' => 1000,
            'ended_at' => null,
            'parent_execution_id' => null,
            'linked_execution_ids' => [],
            'sequence' => 0,
            'context' => ['tenant.id' => ['key' => 'tenant.id', 'value' => 'acme', 'classification' => 'public', 'expires_at' => null, 'source' => null]],
            'result' => null,
        ];

        self::assertSame($expected, $execution->normalize());
        self::assertSame($execution->normalize(), $execution->normalize());
    }

    public function testParentAndLinkedReferencesAreCaptured(): void
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('child'),
            ExecutionKind::fromString('task'),
            ExecutionId::fromString('parent-1'),
            [ExecutionId::fromString('linked-1')],
        );

        $execution = Execution::start(ExecutionId::fromString('exec-2'), $definition, Instant::of(1));

        self::assertSame('parent-1', $execution->definition()->parentExecutionId()?->toString());
        self::assertSame(['linked-1'], array_map(static fn ($id) => $id->toString(), $execution->definition()->linkedExecutionIds()));
    }
}

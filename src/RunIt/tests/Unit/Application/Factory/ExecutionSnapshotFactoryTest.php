<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

final class ExecutionSnapshotFactoryTest extends Unit
{
    public function testTerminalSnapshotCapturesNormalizedExecution(): void
    {
        $factory = new ExecutionSnapshotFactory(
            new StringIdentifierFactory(SnapshotId::fromString(...), new SequenceIdentifierGenerator('snap')),
            new DeterministicHashCalculator(),
        );

        $execution = Execution::start(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
            Instant::of(1000),
        )->complete(ExecutionSuccess::create(), Instant::of(2000));

        $snapshot = $factory->terminal($execution);

        self::assertInstanceOf(ExecutionSnapshot::class, $snapshot);
        self::assertSame(SnapshotClassification::Terminal, $snapshot->classification());
        self::assertSame('exec-1', $snapshot->executionId()->toString());
        self::assertNotEmpty($snapshot->contentHash());
        self::assertArrayHasKey('status', (array) $snapshot->payload());
    }
}

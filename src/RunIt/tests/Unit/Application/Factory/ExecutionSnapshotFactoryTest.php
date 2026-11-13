<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Factory\ExecutionSnapshotFactory;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\Fixture\SequenceIdentifierGenerator;

#[CoversClass(ExecutionSnapshotFactory::class)]
#[UsesClass(StringIdentifierFactory::class)]
#[UsesClass(DeterministicHashCalculator::class)]
#[UsesClass(SequenceIdentifierGenerator::class)]
#[UsesClass(Execution::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(ExecutionDefinition::class)]
#[UsesClass(Instant::class)]
#[UsesClass(ExecutionKind::class)]
#[UsesClass(ExecutionSuccess::class)]
final class ExecutionSnapshotFactoryTest extends Unit
{
    /**
     * @throws InvalidValueExceptionInterface
     */
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

        self::assertSame(SnapshotClassification::Terminal, $snapshot->classification());
        self::assertSame('exec-1', $snapshot->executionId()->toString());
        self::assertNotEmpty($snapshot->contentHash());
        self::assertArrayHasKey('status', (array) $snapshot->payload());
    }
}

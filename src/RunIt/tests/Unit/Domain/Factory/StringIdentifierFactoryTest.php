<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Factory;

use Codeception\Test\Unit;
use ItPack\RunIt\Common\Contract\FactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Factory\StringIdentifierFactory;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use RuntimeException;

final class StringIdentifierFactoryTest extends Unit
{
    public function testFactoryMintsIdentifiersOfTheBoundType(): void
    {
        $factory = new StringIdentifierFactory(
            ExecutionId::fromString(...),
            $this->generator('exec-1', 'exec-2'),
        );

        $first = $factory->build();
        $second = $factory->build();

        self::assertInstanceOf(ExecutionIdInterface::class, $first);
        verify($first->toString())->equals('exec-1');
        verify($second->toString())->equals('exec-2');
        self::assertFalse($first->equals($second));
    }

    public function testTheSameGeneratorCanBackDifferentIdentifierTypes(): void
    {
        $generator = $this->generator('shared-value');

        $snapshotIds = new StringIdentifierFactory(SnapshotId::fromString(...), $generator);

        $snapshotId = $snapshotIds->build();

        self::assertInstanceOf(SnapshotIdInterface::class, $snapshotId);
        self::assertFalse($snapshotId->equals(ExecutionId::fromString('shared-value')));
    }

    public function testADefectiveGeneratorFailsAtCreationTime(): void
    {
        $factory = new StringIdentifierFactory(
            ExecutionId::fromString(...),
            $this->generator("exec\n1"),
        );

        $this->expectException(InvalidIdentifierException::class);

        $factory->build();
    }

    public function testTheFactoryIsUsableThroughTheSharedFactoryContract(): void
    {
        $factory = new StringIdentifierFactory(
            ExecutionId::fromString(...),
            $this->generator('exec-1'),
        );

        self::assertInstanceOf(FactoryInterface::class, $factory);

        $built = $this->buildThrough($factory);

        self::assertInstanceOf(ExecutionIdInterface::class, $built);
    }

    private function buildThrough(FactoryInterface $factory): object
    {
        return $factory->build();
    }

    private function generator(string ...$values): IdentifierGeneratorInterface
    {
        return new class (array_values($values)) implements IdentifierGeneratorInterface {
            private int $position = 0;

            /**
             * @param list<string> $values
             */
            public function __construct(private readonly array $values)
            {
            }

            public function generate(): string
            {
                return $this->values[$this->position++] ?? throw new RuntimeException(
                    'The test generator ran out of values.',
                );
            }
        };
    }
}

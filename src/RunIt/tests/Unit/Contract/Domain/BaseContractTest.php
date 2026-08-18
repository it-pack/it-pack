<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\StringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\ActivityOwnedInterface;
use ItPack\RunIt\Contract\Domain\AttributedInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\NormalizableInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use Tests\Support\Fixture\FixedSequencedArtifact;
use Tests\Support\Fixture\FixedTimestampedArtifact;

final class BaseContractTest extends Unit
{
    public const array DATA = [
        'name' => 'demo',
        'count' => 3,
        'enabled' => true,
    ];

    public const array IDENTIFIERS = [
        'exec-1',
        'exec-2',
    ];

    public const array ATTRIBUTES = [
        'tenant.id' => 't-1',
        'tags' => [
            'a',
            'b'],
    ];

    public const int TIMESTAMP = 1_700_000_000;
    public const int SEQUENCE = 7;

    public function testExecutionArtifactIsNormalizable(): void
    {
        $implemented = class_implements(ExecutionArtifactInterface::class);

        self::assertNotFalse($implemented);

        verify(
            $implemented
        )->arrayContains(
            NormalizableInterface::class,
        );
    }

    public function testNormalizableReturnsPrimitiveScalars(): void
    {
        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return BaseContractTest::DATA;
            }
        };

        verify(
            $artifact->normalize()
        )->equals(
            self::DATA
        );

        verify(
            $artifact->normalize()
        )->equals(
            $artifact->normalize()
        );
    }

    public function testStringIdentifierEqualityIsByValue(): void
    {
        $first = $this->createIdentifier(self::IDENTIFIERS[0]);
        $second = $this->createIdentifier(self::IDENTIFIERS[0]);
        $third = $this->createIdentifier(self::IDENTIFIERS[1]);

        verify($first)->equals($second);
        verify($first)->notEquals($third);
        verify(self::IDENTIFIERS[0])->equals($first->toString());
    }

    public function testExecutionOwnedReportsExecution(): void
    {
        $artifact = new class () implements ExecutionOwnedInterface {
            public function executionId(): ExecutionIdInterface
            {
                return new class () implements ExecutionIdInterface {
                    use StringIdentifierBehavior;

                    public function toString(): string
                    {
                        return BaseContractTest::IDENTIFIERS[0];
                    }

                    public function equals(StringIdentifierInterface $other): bool
                    {
                        return $other->toString() === $this->toString();
                    }
                };
            }
        };

        verify(
            self::IDENTIFIERS[0]
        )->equals(
            $artifact->executionId()->toString()
        );
    }

    public function testActivityOwnedAllowsMissingActivity(): void
    {
        $artifact = new class () implements ActivityOwnedInterface {
            public function activityId(): ?StringIdentifierInterface
            {
                return null;
            }
        };

        self::assertNull($artifact->activityId());
    }

    public function testTimestampAndSequenceProjectPrimitives(): void
    {
        $timed = new FixedTimestampedArtifact(self::TIMESTAMP);
        $sequenced = new FixedSequencedArtifact(self::SEQUENCE);

        verify(
            $timed->timestamp()
        )->equals(
            self::TIMESTAMP
        );

        verify(
            $sequenced->sequence()
        )->equals(
            self::SEQUENCE
        );

        verify(
            $timed->count()
        )->equals(
            self::TIMESTAMP
        );

        verify(
            $sequenced->count()
        )->equals(
            self::SEQUENCE
        );
    }

    public function testAttributesReturnBoundedPrimitiveMap(): void
    {
        $attributed = new class () implements AttributedInterface {
            public function attributes(): array
            {
                return BaseContractTest::ATTRIBUTES;
            }
        };

        verify($attributed->attributes())->equals(self::ATTRIBUTES);
    }

    private function createIdentifier(string $value): StringIdentifierInterface
    {
        return new readonly class ($value) implements StringIdentifierInterface {
            use StringIdentifierBehavior;

            public function __construct(private string $value)
            {
            }

            public function equals(StringIdentifierInterface $other): bool
            {
                return $other->toString() === $this->value;
            }

            public function toString(): string
            {
                return $this->value;
            }
        };
    }
}

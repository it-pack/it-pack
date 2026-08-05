<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Domain;

use ItPack\RunIt\Contract\Domain\ActivityOwnedInterface;
use ItPack\RunIt\Contract\Domain\AttributedInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\NormalizableInterface;
use ItPack\RunIt\Contract\Domain\SequencedInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\TimestampedInterface;

final class BaseContractTest extends \Codeception\Test\Unit
{
    public function testExecutionArtifactIsNormalizable(): void
    {
        $implements = class_implements(ExecutionArtifactInterface::class) ? class_implements(ExecutionArtifactInterface::class) : [];

        self::assertContains(NormalizableInterface::class, $implements);
    }

    public function testNormalizableReturnsPrimitiveScalars(): void
    {
        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return ['name' => 'demo', 'count' => 3, 'enabled' => true];
            }
        };

        $normalized = $artifact->normalize();

        self::assertSame(['name' => 'demo', 'count' => 3, 'enabled' => true], $normalized);
        self::assertSame($normalized, $artifact->normalize());
    }

    public function testStringIdentifierEqualityIsByValue(): void
    {
        $first = $this->identifier('exec-1');
        $second = $this->identifier('exec-1');
        $third = $this->identifier('exec-2');

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($third));
        self::assertSame('exec-1', $first->toString());
    }

    public function testExecutionOwnedReportsExecution(): void
    {
        $artifact = new class () implements ExecutionOwnedInterface {
            public function executionId(): StringIdentifierInterface
            {
                return new class () implements StringIdentifierInterface {
                    public function toString(): string
                    {
                        return 'exec-42';
                    }

                    public function equals(StringIdentifierInterface $other): bool
                    {
                        return $other->toString() === $this->toString();
                    }
                };
            }
        };

        self::assertSame('exec-42', $artifact->executionId()->toString());
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
        $timed = new class () implements TimestampedInterface {
            public function timestamp(): int
            {
                return 1_700_000_000;
            }
        };

        $sequenced = new class () implements SequencedInterface {
            public function sequence(): int
            {
                return 7;
            }
        };

        self::assertSame(1_700_000_000, $timed->timestamp());
        self::assertSame(7, $sequenced->sequence());
    }

    public function testAttributesReturnBoundedPrimitiveMap(): void
    {
        $attributed = new class () implements AttributedInterface {
            public function attributes(): array
            {
                return ['tenant.id' => 't-1', 'tags' => ['a', 'b']];
            }
        };

        self::assertSame(['tenant.id' => 't-1', 'tags' => ['a', 'b']], $attributed->attributes());
    }

    private function identifier(string $value): StringIdentifierInterface
    {
        return new class ($value) implements StringIdentifierInterface {
            public function __construct(private readonly string $value)
            {
            }

            public function toString(): string
            {
                return $this->value;
            }

            public function equals(StringIdentifierInterface $other): bool
            {
                return $other->toString() === $this->value;
            }
        };
    }
}

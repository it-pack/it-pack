<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ExecutionKindPredicate;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\TrustedArtifactProjectionInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExecutionKindPredicate::class)]
final class ExecutionKindPredicateTest extends Unit
{
    public function testExecutionOwnedArtifactWithMatchingKindReturnsTrue(): void
    {
        $resolver = static fn (ExecutionIdInterface $id): ExecutionKindInterface
            => ExecutionKind::fromString('task');
        $decide = static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'task';
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = $this->createArtifact();

        self::assertTrue($predicate->test($artifact));
    }

    public function testExecutionOwnedArtifactWithNonMatchingKindReturnsFalse(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ExecutionKindInterface => ExecutionKind::fromString('task'));
        $decide = (static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'batch');
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = $this->createArtifact();

        self::assertFalse($predicate->test($artifact));
    }

    public function testExecutionOwnedArtifactWithResolverThrowingReturnsFalse(): void
    {
        $resolver = static function (ExecutionIdInterface $id): ?ExecutionKindInterface {
            throw new InvalidIdentifierException('resolver broken');
        };
        $decide = (static fn (ExecutionKindInterface $kind): bool => true);
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = $this->createArtifact();

        self::assertFalse($predicate->test($artifact));
    }

    public function testExecutionOwnedArtifactWithResolverReturningNullReturnsFalse(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ?ExecutionKindInterface => null);
        $decide = (static fn (ExecutionKindInterface $kind): bool => true);
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = $this->createArtifact();

        self::assertFalse($predicate->test($artifact));
    }

    public function testNonExecutionOwnedArtifactReturnsFalse(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ?ExecutionKindInterface => null);
        $decide = (static fn (ExecutionKindInterface $kind): bool => true);
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };

        self::assertFalse($predicate->test($artifact));
    }

    public function testRedactedArtifactWithExecutionTypeResolvesFromNormalized(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ExecutionKindInterface => ExecutionKind::fromString('task'));
        $decide = (static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'task');
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return ['id' => 'exec-1'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testRedactedArtifactWithNonExecutionTypeResolvesFromExecutionId(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ExecutionKindInterface => ExecutionKind::fromString('task'));
        $decide = (static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'task');
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'observation';
            }

            public function normalize(): array
            {
                return ['execution_id' => 'exec-1'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function testRedactedArtifactWithMissingIdReturnsFalse(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ?ExecutionKindInterface => null);
        $decide = (static fn (ExecutionKindInterface $kind): bool => true);
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new class () implements ExecutionArtifactInterface, RedactedArtifactInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return [];
            }
        };

        self::assertFalse($predicate->test($artifact));
    }

    public function testVerifiedArtifactProjectionResolvesFromNormalized(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ExecutionKindInterface => ExecutionKind::fromString('task'));
        $decide = (static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'task');
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new VerifiedArtifactProjection(
            ['id' => 'exec-1', 'execution_id' => 'exec-1'],
            'execution'
        );

        self::assertTrue($predicate->test($artifact));
    }

    public function testTrustedArtifactProjectionResolvesFromNormalized(): void
    {
        $resolver = (static fn (ExecutionIdInterface $id): ExecutionKindInterface => ExecutionKind::fromString('task'));
        $decide = (static fn (ExecutionKindInterface $kind): bool => $kind->toString() === 'task');
        $predicate = new ExecutionKindPredicate($resolver, $decide);

        $artifact = new class () implements ExecutionArtifactInterface, TrustedArtifactProjectionInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return ['id' => 'exec-1'];
            }
        };

        self::assertTrue($predicate->test($artifact));
    }

    public function createArtifact(): ExecutionArtifactInterface
    {
        return new class () implements ExecutionArtifactInterface, ExecutionOwnedInterface {
            public function executionId(): ExecutionIdInterface
            {
                return ExecutionId::fromString('exec-1');
            }

            public function normalize(): array
            {
                return [];
            }
        };
    }
}

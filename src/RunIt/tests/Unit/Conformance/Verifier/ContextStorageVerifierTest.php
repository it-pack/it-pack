<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Conformance\Verifier\ContextStorageVerifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use RuntimeException;
use Tests\Support\Fixture\FakeContextToken;
use Tests\Support\Fixture\Psr\FakeExecutionScope;
use Tests\Support\Fixture\Psr\RecordingObservationRecorder;
use Tests\Support\Fixture\ThrowingAttachContextStorage;

/**
 * @group conformance
 */
final class ContextStorageVerifierTest extends Unit
{
    private function scope(): ExecutionScopeInterface
    {
        return new FakeExecutionScope(
            Execution::start(
                ExecutionId::fromString('exec-1'),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1),
            ),
            ExecutionContext::empty(),
            new RecordingObservationRecorder(),
        );
    }

    private function hasViolation(ConformanceResult $result, string $id): bool
    {
        return array_any($result->errors(), fn (ConformanceViolation $violation): bool => $violation->id === $id);
    }

    private function conformantStorage(): ContextStorageInterface
    {
        return new class () implements ContextStorageInterface {
            /** @var list<array{0: ExecutionScopeInterface, 1: ContextTokenInterface}> */
            private array $stack = [];

            public function current(): ?ExecutionScopeInterface
            {
                if ($this->stack === []) {
                    return null;
                }

                return $this->stack[count($this->stack) - 1][0];
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $token = new FakeContextToken();

                $this->stack[] = [$scope, $token];

                return $token;
            }

            public function detach(ContextTokenInterface $token): void
            {
                $this->stack = array_values(
                    array_filter(
                        $this->stack,
                        fn (array $entry): bool => $entry[1] !== $token,
                    ),
                );
            }
        };
    }

    public function testPortReportsContextStorageInterface(): void
    {
        self::assertSame(ContextStorageInterface::class, new ContextStorageVerifier()->port());
    }

    public function testConformantStoragePassesAllChecks(): void
    {
        $scope = $this->scope();
        $storage = $this->conformantStorage();

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testAttachThrowIsRecorded(): void
    {
        $scope = $this->scope();

        $result = new ContextStorageVerifier()->verify(new ThrowingAttachContextStorage(), $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'attach.throws'));
    }

    public function testCurrentMismatchAfterAttachIsRecorded(): void
    {
        $scope = $this->scope();
        $other = $this->scope();
        $storage = new readonly class ($other) implements ContextStorageInterface {
            public function __construct(private ExecutionScopeInterface $other)
            {
            }

            public function current(): ExecutionScopeInterface
            {
                return $this->other;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new FakeContextToken();
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'current.after-attach'));
    }

    public function testSecondAttachThrowIsRecorded(): void
    {
        $scope = $this->scope();
        $storage = new class ($scope) implements ContextStorageInterface {
            private int $attaches = 0;

            public function __construct(private readonly ExecutionScopeInterface $scope)
            {
            }

            public function current(): ExecutionScopeInterface
            {
                return $this->scope;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $this->attaches++;

                if ($this->attaches === 2) {
                    throw new RuntimeException('second attach broken');
                }

                return new FakeContextToken();
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'attach.throws'));
    }

    public function testDetachSecondTokenThrowIsRecorded(): void
    {
        $scope = $this->scope();
        $storage = new readonly class ($scope) implements ContextStorageInterface {
            public function __construct(private ExecutionScopeInterface $scope)
            {
            }

            public function current(): ExecutionScopeInterface
            {
                return $this->scope;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new FakeContextToken();
            }

            public function detach(ContextTokenInterface $token): void
            {
                throw new RuntimeException('detach broken');
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'detach.throws'));
    }

    public function testDetachTokenThrowIsRecorded(): void
    {
        $scope = $this->scope();
        $storage = new class ($scope) implements ContextStorageInterface {
            private int $detaches = 0;

            public function __construct(private readonly ExecutionScopeInterface $scope)
            {
            }

            public function current(): ExecutionScopeInterface
            {
                return $this->scope;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new FakeContextToken();
            }

            public function detach(ContextTokenInterface $token): void
            {
                $this->detaches++;

                if ($this->detaches === 2) {
                    throw new RuntimeException('final detach broken');
                }
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'detach.throws'));
    }

    public function testScopeStillPresentAfterFinalDetachIsRecorded(): void
    {
        $scope = $this->scope();
        $storage = new readonly class ($scope) implements ContextStorageInterface {
            public function __construct(private ExecutionScopeInterface $scope)
            {
            }

            public function current(): ExecutionScopeInterface
            {
                return $this->scope;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new FakeContextToken();
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $scope);

        self::assertFalse($result->isConformant());
        self::assertTrue($this->hasViolation($result, 'detach.incomplete'));
    }
}

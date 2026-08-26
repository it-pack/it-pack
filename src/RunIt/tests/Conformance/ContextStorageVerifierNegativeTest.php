<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Result\ViolationSeverity;
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
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Feeds the {@see ContextStorageVerifier} broken storage implementations and
 * asserts each LIFO contract breach is reported with the expected violation.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ContextStorageVerifierNegativeTest extends Unit
{
    private RunItTestKit $kit;

    private int $scopes = 0;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    public function testThrowingAttachIsReportedAndStopsTheCheck(): void
    {
        $storage = new class () implements ContextStorageInterface {
            public function current(): ?ExecutionScopeInterface
            {
                throw new RuntimeException('current() must never be reached');
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                throw new RuntimeException('attach refused');
            }

            public function detach(ContextTokenInterface $token): void
            {
                throw new RuntimeException('detach must never be reached');
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['attach.throws'], ConformanceViolations::ids($result));
    }

    public function testSecondThrowingAttachSkipsTheInnerScopeChecks(): void
    {
        $storage = new class () implements ContextStorageInterface {
            /** @var list<ExecutionScopeInterface> */
            public array $stack = [];

            private int $attaches = 0;

            public function current(): ?ExecutionScopeInterface
            {
                return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                if (++$this->attaches > 1) {
                    throw new RuntimeException('second attach refused');
                }

                $this->stack[] = $scope;

                return new class () implements ContextTokenInterface {};
            }

            public function detach(ContextTokenInterface $token): void
            {
                array_pop($this->stack);
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['attach.throws'], ConformanceViolations::ids($result));
    }

    public function testWrongCurrentIsReportedAcrossTheWholeStackWalk(): void
    {
        $outer = $this->scope();
        $wrong = $this->scope();

        $storage = new class ($wrong) implements ContextStorageInterface {
            /** @var list<ExecutionScopeInterface> */
            public array $stack = [];

            public function __construct(private readonly ExecutionScopeInterface $wrong)
            {
            }

            public function current(): ?ExecutionScopeInterface
            {
                return $this->stack === [] ? null : $this->wrong;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $this->stack[] = $scope;

                return new class () implements ContextTokenInterface {};
            }

            public function detach(ContextTokenInterface $token): void
            {
                array_pop($this->stack);
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $outer);

        self::assertFalse($result->isConformant());
        self::assertSame(
            ['current.after-attach', 'current.not-lifo', 'detach.wrong-scope'],
            ConformanceViolations::ids($result),
        );
    }

    public function testDetachThatLeavesTheScopeAttachedIsReported(): void
    {
        $storage = new class () implements ContextStorageInterface {
            /** @var list<ExecutionScopeInterface> */
            public array $stack = [];

            public function current(): ?ExecutionScopeInterface
            {
                return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $this->stack[] = $scope;

                return new class () implements ContextTokenInterface {};
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['detach.incomplete'], ConformanceViolations::ids($result));
    }

    public function testThrowingDetachAfterPopIsReportedTwice(): void
    {
        $storage = new class () implements ContextStorageInterface {
            /** @var list<ExecutionScopeInterface> */
            public array $stack = [];

            public function current(): ?ExecutionScopeInterface
            {
                return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $this->stack[] = $scope;

                return new class () implements ContextTokenInterface {};
            }

            public function detach(ContextTokenInterface $token): void
            {
                array_pop($this->stack);

                throw new RuntimeException('detach failed after pop');
            }
        };

        $result = new ContextStorageVerifier()->verify($storage, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['detach.throws', 'detach.throws'], ConformanceViolations::ids($result));
        self::assertSame(ViolationSeverity::Error, $result->violations[0]->severity);
    }

    private function scope(): ExecutionScopeInterface
    {
        return $this->kit->buildScope(
            Execution::start(
                ExecutionId::fromString('exec-conf-' . ++$this->scopes),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1),
            ),
            ExecutionContext::empty(),
        );
    }
}

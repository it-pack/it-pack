<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use Tests\Unit\Conformance\Fixture\FakeExecutionScope;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;
use PHPUnit\Framework\TestCase;

/**
 * Negative (and logic) siblings for conformance Assertion A — Context Isolation.
 *
 * A deliberately bleeding storage must be reported with the `ctx-bleed` id at
 * error severity; a LIFO-tolerant storage must pass.
 *
 * @covers \ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier
 */
final class ContextIsolationVerifierTest extends TestCase
{
    public function testDetectsContextBleed(): void
    {
        $verifier = new ContextIsolationVerifier();
        $result = $verifier->verify($this->bleedingFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('ctx-bleed', $result->errors()[0]->id);
        self::assertSame(ViolationSeverity::Error, $result->errors()[0]->severity);
    }

    public function testPassesForLifoTolerantStorage(): void
    {
        $verifier = new ContextIsolationVerifier();
        $result = $verifier->verify($this->lifoFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result->errors());
    }

    private function bleedingFixture(): AsyncRuntimeFixtureInterface
    {
        $storage = new class implements ContextStorageInterface {
            private ?ExecutionScopeInterface $sentinel = null;

            public function current(): ExecutionScopeInterface
            {
                if ($this->sentinel === null) {
                    $this->sentinel = new FakeExecutionScope();
                }

                return $this->sentinel;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new class implements ContextTokenInterface {
                    public function id(): int
                    {
                        return 0;
                    }
                };
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        return new FakeRuntimeFixture($storage, 0);
    }

    private function lifoFixture(): AsyncRuntimeFixtureInterface
    {
        $storage = new class implements ContextStorageInterface {
            /** @var ExecutionScopeInterface[] */
            private array $stack = [];

            public function current(): ?ExecutionScopeInterface
            {
                return $this->stack === [] ? null : end($this->stack);
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                $this->stack[] = $scope;

                return new class implements ContextTokenInterface {
                    public function id(): int
                    {
                        return 0;
                    }
                };
            }

            public function detach(ContextTokenInterface $token): void
            {
                array_pop($this->stack);
            }
        };

        return new FakeRuntimeFixture($storage, 0);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use Codeception\Test\Unit;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\Instant;
use RuntimeException;
use Tests\Support\Fixture\Psr\FakeExecutionScope;
use Tests\Support\Fixture\Psr\RecordingObservationRecorder;

/**
 * @group conformance
 */
final class ExecutionEngineVerifierTest extends Unit
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

    public function testPortReportsExecutionEngineInterface(): void
    {
        self::assertSame(ExecutionEngineInterface::class, new ExecutionEngineVerifier()->port());
    }

    public function testConformantEnginePasses(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation();
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {
                };
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result);
    }

    public function testRunThrowIsRecorded(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                throw new RuntimeException('run broken');
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {
                };
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('run.throws', $result->errors()[0]->id);
    }

    public function testWrongValueIsRecorded(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return 43;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {
                };
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame('run.wrong-value', $result->errors()[0]->id);
    }

    public function testNotDeterministicIsRecorded(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            private int $calls = 0;

            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                $this->calls++;

                return $this->calls === 1 ? 42 : 43;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {
                };
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame('run.not-deterministic', $result->errors()[0]->id);
    }

    public function testNullRunThrowIsRecorded(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            private int $calls = 0;

            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                $this->calls++;

                if ($this->calls === 3) {
                    throw new RuntimeException('null run broken');
                }

                return 42;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {
                };
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame('run.null.throws', $result->errors()[0]->id);
    }

    public function testCapabilityThrowIsRecorded(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation();
            }

            public function capability(): EngineCapabilityInterface
            {
                throw new RuntimeException('capability broken');
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame('capability.throws', $result->errors()[0]->id);
    }
}

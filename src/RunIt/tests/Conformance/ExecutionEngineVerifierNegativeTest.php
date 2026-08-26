<?php

declare(strict_types=1);

namespace Tests\Conformance;

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
use Tests\Support\Fixture\Conformance\ConformanceViolations;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Feeds the {@see ExecutionEngineVerifier} broken engine implementations and
 * asserts each contract breach is reported with the expected violation identity.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionEngineVerifierNegativeTest extends Unit
{
    private RunItTestKit $kit;

    protected function setUp(): void
    {
        $this->kit = new RunItTestKit();
    }

    public function testThrowingRunIsReportedAndStopsTheCheck(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                throw new RuntimeException('engine down');
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {};
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['run.throws'], ConformanceViolations::ids($result));
    }

    public function testWrongOperationValueIsReported(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return 7;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {};
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['run.wrong-value', 'run.null.wrong-value'], ConformanceViolations::ids($result));
    }

    public function testDivergingResultsAreReportedAsNonDeterministic(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            private int $calls = 0;

            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return ++$this->calls + 41;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {};
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['run.not-deterministic', 'run.null.wrong-value'], ConformanceViolations::ids($result));
    }

    public function testNullResultCoercionIsReported(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation() ?? 0;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {};
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['run.null.wrong-value'], ConformanceViolations::ids($result));
    }

    public function testThrowingNullReturningOperationIsReportedAndStopsTheCheck(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                $value = $operation();

                if ($value === null) {
                    throw new RuntimeException('null operations rejected');
                }

                return $value;
            }

            public function capability(): EngineCapabilityInterface
            {
                return new class () implements EngineCapabilityInterface {};
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['run.null.throws'], ConformanceViolations::ids($result));
    }

    public function testThrowingCapabilityIsReportedLast(): void
    {
        $engine = new class () implements ExecutionEngineInterface {
            public function run(ExecutionScopeInterface $scope, callable $operation): mixed
            {
                return $operation();
            }

            public function capability(): EngineCapabilityInterface
            {
                throw new RuntimeException('capability unavailable');
            }
        };

        $result = new ExecutionEngineVerifier()->verify($engine, $this->scope());

        self::assertFalse($result->isConformant());
        self::assertSame(['capability.throws'], ConformanceViolations::ids($result));
    }

    private function scope(): ExecutionScopeInterface
    {
        return $this->kit->buildScope(
            Execution::start(
                ExecutionId::fromString('exec-conf'),
                ExecutionDefinition::create(ExecutionName::fromString('op'), ExecutionKind::fromString('task')),
                Instant::of(1),
            ),
            ExecutionContext::empty(),
        );
    }
}

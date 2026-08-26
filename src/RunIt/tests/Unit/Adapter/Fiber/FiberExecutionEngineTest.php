<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Fiber;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Fiber\FiberExecutionEngine;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M11.3 — Fiber execution engine.
 */
final class FiberExecutionEngineTest extends Unit
{
    private function kit(): RunItTestKit
    {
        return new RunItTestKit();
    }

    private function definition(): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('fiber'));
    }

    public function testRunsOperationAndReturnsItsResult(): void
    {
        $kit = $this->kit();
        $engine = new FiberExecutionEngine();

        $result = $kit->runner->run(
            $this->definition(),
            static function (ExecutionScopeInterface $scope) use ($engine): mixed {
                return $engine->run(
                    $scope,
                    static fn (ExecutionScopeInterface $s): string => $s === $scope ? 'ok' : 'mismatch'
                );
            }
        );

        self::assertSame('ok', $result);
    }

    public function testPropagatesOperationException(): void
    {
        $kit = $this->kit();
        $engine = new FiberExecutionEngine();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $kit->runner->run($this->definition(), static function (ExecutionScopeInterface $scope) use ($engine): void {
            $engine->run($scope, static function (): void {
                throw new RuntimeException('boom');
            });
        });
    }

    public function testCapabilityReportsFiberSupport(): void
    {
        $capability = new FiberExecutionEngine()->capability();

        self::assertInstanceOf(EngineCapabilityInterface::class, $capability);
        self::assertInstanceOf(EngineCapability::class, $capability);
        self::assertTrue($capability->supportsFibers);
        self::assertFalse($capability->supportsCoroutines);
        self::assertFalse($capability->concurrent);
    }

    public function testImplementsEngineContract(): void
    {
        self::assertInstanceOf(ExecutionEngineInterface::class, new FiberExecutionEngine());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Engine;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Engine\SynchronousExecutionEngine;
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
 * M11.3 — default synchronous execution engine.
 */
final class SynchronousExecutionEngineTest extends Unit
{
    private function scope(): ExecutionScopeInterface
    {
        $kit = new RunItTestKit();
        $captured = null;
        $kit->runner->run(
            ExecutionDefinition::create(ExecutionName::fromString('test'), ExecutionKind::fromString('sync')),
            static function (ExecutionScopeInterface $scope) use (&$captured): void {
                $captured = $scope;
            },
        );

        self::assertInstanceOf(ExecutionScopeInterface::class, $captured);

        return $captured;
    }

    public function testRunsOperationOnCallingStack(): void
    {
        $engine = new SynchronousExecutionEngine();

        $result = $engine->run($this->scope(), static fn (): string => 'value');

        self::assertSame('value', $result);
    }

    public function testPropagatesOperationException(): void
    {
        $engine = new SynchronousExecutionEngine();

        $this->expectException(RuntimeException::class);

        $engine->run($this->scope(), static function (): never {
            throw new RuntimeException('boom');
        });
    }

    public function testCapabilityReportsPlainSynchronousExecution(): void
    {
        $capability = new SynchronousExecutionEngine()->capability();

        self::assertInstanceOf(EngineCapabilityInterface::class, $capability);
        self::assertInstanceOf(EngineCapability::class, $capability);
        self::assertFalse($capability->supportsFibers);
        self::assertFalse($capability->supportsCoroutines);
        self::assertFalse($capability->concurrent);
    }

    public function testImplementsEngineContract(): void
    {
        self::assertInstanceOf(ExecutionEngineInterface::class, new SynchronousExecutionEngine());
    }

    public function testDefinitionHelperCompiles(): void
    {
        self::assertInstanceOf(
            ExecutionDefinitionInterface::class,
            ExecutionDefinition::create(ExecutionName::fromString('x'), ExecutionKind::fromString('y'))
        );
    }
}

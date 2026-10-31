<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;

/**
 * Proves the reference runtime passes RunIt's {@see ExecutionEngineVerifier}
 * and returns operation results (including across Fiber suspend/resume).
 *
 * @covers \ItPack\AsyncIt\SDK\AsyncRuntime
 * @covers \ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory
 * @covers \ItPack\AsyncIt\Application\Factory\RuntimeAdapterAssembler
 * @covers \ItPack\AsyncIt\Application\Factory\SchedulerFactory
 * @covers \ItPack\AsyncIt\Application\Factory\PerRunExecutionScopeFactory
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class AsyncRuntimeTest extends TestCase
{
    public function testPassesRunItExecutionEngineVerifier(): void
    {
        $result = new ExecutionEngineVerifier()->verify($this->runtime(), new ReferenceExecutionScope('engine'));

        self::assertTrue(
            $result->isConformant(),
            $result->summary(),
        );
    }

    public function testReturnsOperationValue(): void
    {
        $value = $this->runtime()->run(new ReferenceExecutionScope('e'), static fn (): int => 42);

        self::assertSame(42, $value);
    }

    public function testReturnsValueAcrossFiberSuspendResume(): void
    {
        $runtime = $this->runtime();

        $value = $runtime->run(
            new ReferenceExecutionScope('e'),
            static function () use ($runtime): int {
                $runtime->awaitDelay(Duration::ofNanoseconds(1));

                return 7;
            },
        );

        self::assertSame(7, $value);
    }

    public function testReturnsNullForNullOperation(): void
    {
        $value = $this->runtime()->run(new ReferenceExecutionScope('e'), static fn (): ?int => null);

        self::assertNull($value);
    }

    public function testCapabilityReportsFiberSupport(): void
    {
        $capability = $this->runtime()->capability();

        self::assertTrue($capability->supportsFibers);
        // Swoole is available: BlockingHook = AdapterOnly → supportsCoroutines = true
        self::assertTrue($capability->supportsCoroutines);
        // FiberExecution = Supported → concurrent = true
        self::assertTrue($capability->concurrent);
    }

    private function runtime(): AsyncRuntime
    {
        return AsyncRuntime::discover();
    }
}

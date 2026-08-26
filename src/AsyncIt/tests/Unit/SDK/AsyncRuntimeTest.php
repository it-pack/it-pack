<?php

declare(strict_types=1);

namespace Tests\Unit\SDK;

use Fiber;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Conformance\Verifier\ExecutionEngineVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Proves the reference runtime passes RunIt's {@see ExecutionEngineVerifier}
 * and returns operation results (including across Fiber suspend/resume).
 *
 * @covers \ItPack\AsyncIt\SDK\AsyncRuntime
 * @covers \ItPack\AsyncIt\SDK\Factory\AsyncRuntimeFactory
 * @covers \ItPack\AsyncIt\Application\Factory\SchedulerFactory
 * @covers \ItPack\AsyncIt\Application\Factory\PerRunExecutionScopeFactory
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class AsyncRuntimeTest extends TestCase
{
    private function runtime(): AsyncRuntime
    {
        return AsyncRuntime::discover();
    }

    public function testPassesRunItExecutionEngineVerifier(): void
    {
        $result = (new ExecutionEngineVerifier())->verify($this->runtime(), new ReferenceExecutionScope('engine'));

        self::assertTrue(
            $result->isConformant(),
            (string) $result->summary(),
        );
    }

    public function testReturnsOperationValue(): void
    {
        $value = $this->runtime()->run(new ReferenceExecutionScope('e'), static fn (): int => 42);

        self::assertSame(42, $value);
    }

    public function testReturnsValueAcrossFiberSuspendResume(): void
    {
        $value = $this->runtime()->run(
            new ReferenceExecutionScope('e'),
            static function (): int {
                Fiber::suspend();

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
        self::assertFalse($capability->supportsCoroutines);
        self::assertFalse($capability->concurrent);
    }
}

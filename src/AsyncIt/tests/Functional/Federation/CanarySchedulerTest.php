<?php

declare(strict_types=1);

namespace Tests\Functional\Federation;

use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Federation\CanaryScheduler;
use ItPack\AsyncIt\Contract\TaskInterface;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\CanaryScheduler
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class CanarySchedulerTest extends TestCase
{
    public function testCanarySchedulerRoutesToBaselineWhenWeightIsZero(): void
    {
        $baseline = FunctionalTestHelper::makeScheduler();
        $canary = FunctionalTestHelper::makeScheduler();

        $canaryScheduler = new CanaryScheduler(
            $baseline,
            $canary,
            canaryWeight: 0.0,
            errorMargin: 0.1,
            latencyFactor: 2.0,
        );

        $task = $canaryScheduler->spawn(static fn (): string => 'baseline result');
        $canaryScheduler->run();

        self::assertSame('baseline result', $canaryScheduler->result($task));
        self::assertFalse($canaryScheduler->isRolledBack());
    }

    public function testCanarySchedulerDoesNotRollbackWhenCanaryUnused(): void
    {
        $baseline = FunctionalTestHelper::makeScheduler();
        $canary = FunctionalTestHelper::makeScheduler();

        $canaryScheduler = new CanaryScheduler(
            $baseline,
            $canary,
            canaryWeight: 0.0,
            errorMargin: 0.1,
            latencyFactor: 2.0,
            minCalls: 1,
        );

        // All dispatches go to baseline (weight 0.0), canary never used
        for ($i = 0; $i < 5; $i++) {
            try {
                $canaryScheduler->dispatch(
                    $this->makeTask(),
                    static fn (): never => throw new RuntimeException('baseline failure'),
                );
            } catch (RuntimeException) {
            }
        }

        self::assertFalse($canaryScheduler->isRolledBack());
    }

    private function makeTask(): TaskInterface
    {
        return new TaskFactory(new TaskIdFactory(new RandomIdentifierGenerator()))
            ->create(ExecutionId::fromString('exec-1'), new CancellationScopeFactory()->create())
        ;
    }
}

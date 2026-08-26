<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Security;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextStorage;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionOwnedInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Tests\Support\Fixture\RunItTestKit;

/**
 * M12.2 — tenant isolation across executions and runtimes.
 *
 * Each execution is an independent tenant of the ambient context storage. These
 * regression tests prove that no context or artifact state leaks from one
 * execution into another, on the synchronous stack, inside Fibers, and (where the
 * Swoole extension is present) inside coroutines.
 *
 * @group application
 * @group security
 * @group isolation
 */
final class TenantIsolationTest extends Unit
{
    private function definition(string $name): ExecutionDefinition
    {
        return ExecutionDefinition::create(ExecutionName::fromString($name), ExecutionKind::fromString('task'));
    }

    public function testSequentialExecutionsLeaveNoAmbientContext(): void
    {
        $kit = new RunItTestKit();
        $first = null;
        $second = null;

        $kit->runner->run(
            $this->definition('first'),
            static function (ExecutionScopeInterface $scope) use (&$first): void {
                $first = $scope->execution()->id()->toString();
            }
        );
        $kit->runner->run(
            $this->definition('second'),
            static function (ExecutionScopeInterface $scope) use (&$second): void {
                $second = $scope->execution()->id()->toString();
            }
        );

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first, $second);
        self::assertNull($kit->storage->current(), 'ambient context must not persist after execution completes');
    }

    public function testArtifactsAreScopedToTheirExecution(): void
    {
        $kit = new RunItTestKit();
        $first = null;
        $second = null;

        $kit->runner->run(
            $this->definition('first'),
            static function (ExecutionScopeInterface $scope) use (&$first): void {
                $first = $scope->execution()->id()->toString();
                $scope->observations()->record('first-observation', null);
            }
        );
        $kit->runner->run(
            $this->definition('second'),
            static function (ExecutionScopeInterface $scope) use (&$second): void {
                $second = $scope->execution()->id()->toString();
                $scope->observations()->record('second-observation', null);
            }
        );

        $ownedByFirst = 0;
        $ownedBySecond = 0;

        foreach ($kit->artifacts() as $artifact) {
            if (!$artifact instanceof ExecutionOwnedInterface) {
                continue;
            }

            $id = $artifact->executionId()->toString();

            if ($id === $first) {
                ++$ownedByFirst;

                continue;
            }

            if ($id === $second) {
                ++$ownedBySecond;

                continue;
            }

            self::fail('Artifact owned by an unknown execution: ' . $id);
        }

        self::assertGreaterThan(0, $ownedByFirst);
        self::assertGreaterThan(0, $ownedBySecond);
    }

    public function testParallelFibersMaintainIndependentContext(): void
    {
        $kit = new RunItTestKit(storage: new FiberContextStorage());
        $a = $this->definition('a');
        $b = $this->definition('b');
        $ids = [];

        $first = new \Fiber(static function () use ($kit, $a, &$ids): void {
            $kit->runner->run($a, static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['first'] = $scope->execution()->id()->toString();
            });
        });
        $second = new \Fiber(static function () use ($kit, $b, &$ids): void {
            $kit->runner->run($b, static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['second'] = $scope->execution()->id()->toString();
            });
        });

        $first->start();
        $second->start();

        self::assertArrayHasKey('first', $ids);
        self::assertArrayHasKey('second', $ids);
        self::assertNotSame($ids['first'], $ids['second']);
        self::assertNull($kit->storage->current(), 'fiber context must not leak to the main stack');
    }

    public function testCoroutineIsolationWhenSwooleAvailable(): void
    {
        if (!class_exists(\Swoole\Coroutine::class) && !class_exists(\OpenSwoole\Coroutine::class)) {
            self::markTestSkipped('Swoole/OpenSwoole extension not available.');
        }

        $kit = new RunItTestKit(storage: new CoroutineContextStorage(new SwooleCoroutineIdProvider()));
        $a = $this->definition('a');
        $b = $this->definition('b');
        $ids = [];

        \Swoole\Coroutine\run(static function () use ($kit, $a, $b, &$ids): void {
            $kit->runner->run($a, static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['first'] = $scope->execution()->id()->toString();
            });
            $kit->runner->run($b, static function (ExecutionScopeInterface $scope) use (&$ids): void {
                $ids['second'] = $scope->execution()->id()->toString();
            });
        });

        self::assertArrayHasKey('first', $ids);
        self::assertArrayHasKey('second', $ids);
        self::assertNotSame($ids['first'], $ids['second']);
        self::assertNull($kit->storage->current(), 'coroutine context must not leak after the coroutine completes');
    }
}

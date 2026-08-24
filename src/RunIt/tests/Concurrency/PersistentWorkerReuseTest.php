<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Codeception\Test\Unit;
use Fiber;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use Tests\Support\Fixture\RunItTestKit;

/**
 * Persistent-worker reuse: one long-lived runtime serves many sequential requests.
 *
 * Every run must leave the ambient scope exactly as it found it, so a later request
 * cannot observe a previous request's execution, and every request gets its own identity.
 */
final class PersistentWorkerReuseTest extends Unit
{
    public function testSequentialRunsStayIsolatedAndRestoreAmbientScope(): void
    {
        $kit = new RunItTestKit();
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('worker.iteration'),
            ExecutionKind::fromString('worker'),
        );

        $seenIds = [];

        foreach (range(1, 25) as $iteration) {
            $result = $kit->runner->run(
                $definition,
                static function () use ($kit, &$seenIds, $iteration): string {
                    $scope = $kit->storage->current();

                    $id = $scope?->execution()->id()->toString() ?? 'missing';
                    $seenIds[] = $id;

                    return "handled-{$iteration}";
                },
            );

            self::assertSame("handled-{$iteration}", $result);
            self::assertNull($kit->storage->current(), 'ambient scope must be restored after every request');
        }

        self::assertCount(25, array_unique($seenIds), 'every sequential request must own a distinct execution');
    }

    public function testParallelFibersShareOneRuntimeWithoutCrossTalk(): void
    {
        $runtime = RunItBuilder::create()
            ->withPublisher(new InMemoryArtifactPublisher())
            ->withClock(new SystemMonotonicClock())
            ->withSequenceAllocator(new InMemorySequenceAllocator())
            ->withSnapshotSerializer(new JsonSnapshotSerializer())
            ->withIdentifierGenerator(new RandomIdentifierGenerator())
            ->withContextStorage(new FiberContextStorage())
            ->build()
        ;
        $definition = static fn (): ExecutionDefinition => ExecutionDefinition::create(
            ExecutionName::fromString('fiber.task'),
            ExecutionKind::fromString('task'),
        );

        $fibers = [];

        foreach (range(1, 8) as $index) {
            $fibers[$index] = new Fiber(static function () use ($runtime, $definition, $index): array {
                // The ambient scope is only visible *inside* the operation: the
                // runner restores the prior context before run() returns.
                $outcome = $runtime->run($definition(), static function () use ($runtime, $index): array {
                    return [
                        'result' => $index * 10,
                        'ambient' => $runtime->currentExecution()?->execution()->id()->toString(),
                    ];
                });

                return is_array($outcome) ? $outcome : [];
            });

            $fibers[$index]->start();
        }

        $ambientIds = [];

        foreach ($fibers as $index => $fiber) {
            while (!$fiber->isTerminated()) {
                $fiber->resume();
            }

            $outcome = $fiber->getReturn();
            self::assertIsArray($outcome);

            $ambient = $outcome['ambient'] ?? null;
            self::assertIsString($ambient, "fiber {$index} must see its own ambient execution");

            self::assertSame($index * 10, $outcome['result'] ?? null, "fiber {$index} must receive its own result");
            $ambientIds[] = $ambient;
        }

        self::assertCount(8, array_unique($ambientIds), 'parallel fibers must not share an ambient execution');
    }
}

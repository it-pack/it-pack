<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * The reference AsyncIt runtime assembled for conformance.
 *
 * It wires the deterministic, `SelectPoller`-backed runtime: reference
 * scheduler, `SelectPoller`, `MemoryContextStorage`, `DeterministicMonotonicClock`,
 * and `NullArtifactPublisher`. The verifier's poller is a *separate* `SelectPoller`
 * instance so its handle count is independent of any scheduler activity.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ReferenceRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    private readonly SelectPoller $verifierPoller;

    private readonly MemoryContextStorage $storage;

    private readonly DeterministicMonotonicClock $clock;

    private readonly NullArtifactPublisher $publisher;

    private readonly FiberTaskScheduler $scheduler;

    public function __construct()
    {
        $this->verifierPoller = new SelectPoller();
        $this->storage = new MemoryContextStorage();
        $this->clock = new DeterministicMonotonicClock();
        $this->publisher = new NullArtifactPublisher();

        $this->scheduler = new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new SelectPoller(),
            $this->storage,
            new ReferenceExecutionScopeFactory(),
            $this->publisher,
        );
    }

    public function portName(): string
    {
        return 'AsyncRuntime';
    }

    public function scheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }

    public function poller(): PollerPortInterface
    {
        return $this->verifierPoller;
    }

    public function contextStorage(): ContextStorageInterface
    {
        return $this->storage;
    }

    public function clock(): MonotonicClockInterface
    {
        return $this->clock;
    }

    public function publisher(): ArtifactPublisherInterface
    {
        return $this->publisher;
    }

    public function activePollHandleCount(): int
    {
        return $this->verifierPoller->activeHandleCount();
    }

    public function makeExecutionScope(string $discriminant): ExecutionScopeInterface
    {
        return new ReferenceExecutionScope($discriminant);
    }
}

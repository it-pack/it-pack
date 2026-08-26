<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Shared wiring for a native-runtime conformance fixture.
 *
 * Supplies the scheduler (built on the runtime's poller), the runtime's context
 * storage, a deterministic clock, and a null publisher. A *separate* poller
 * instance is exposed for the verifiers so their handle accounting is independent
 * of scheduler activity (Assertion C).
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
abstract class AbstractRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    private readonly FiberTaskScheduler $scheduler;

    private readonly PollerPortInterface $verifierPoller;

    private readonly ContextStorageInterface $storage;

    private readonly DeterministicMonotonicClock $clock;

    private readonly NullArtifactPublisher $publisher;

    public function __construct(
        PollerPortInterface $verifierPoller,
        ContextStorageInterface $storage,
        PollerPortInterface $schedulerPoller,
    ) {
        $this->verifierPoller = $verifierPoller;
        $this->storage = $storage;
        $this->clock = new DeterministicMonotonicClock();
        $this->publisher = new NullArtifactPublisher();

        $this->scheduler = new FiberTaskScheduler(
            new TaskFactory(new RandomIdentifierGenerator()),
            new FiberFactory(),
            $schedulerPoller,
            $this->clock,
            $storage,
            new CancellationScopeFactory(),
            new ReferenceExecutionScopeFactory(),
            $this->publisher,
        );
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
        return new \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope($discriminant);
    }
}

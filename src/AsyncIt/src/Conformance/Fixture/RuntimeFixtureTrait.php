<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Factory\CancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;

/**
 * Shared wiring for a native-runtime conformance fixture.
 *
 * Supplies the scheduler (built on the runtime's poller), the runtime's context
 * storage, a deterministic clock, and a null publisher. A *separate* poller
 * instance is exposed for the verifiers so their handle accounting is independent
 * of scheduler activity (Assertion C).
 *
 * This trait replaces the former `AbstractRuntimeFixture` abstract class so that
 * every concrete fixture remains a `final class` with no inheritance, satisfying
 * the "no abstract class" rule. Each fixture calls {@see initRuntimeFixture()}
 * from its own constructor.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
trait RuntimeFixtureTrait
{
    private FiberTaskScheduler $scheduler;

    private PollerPortInterface $verifierPoller;

    private ContextStorageInterface $storage;

    private DeterministicMonotonicClock $clock;

    private NullArtifactPublisher $publisher;

    private LazyTelemetryBridge $telemetry;

    private CancellationScopeInterface $rootScope;

    private CancellationScopeFactory $cancellationScopeFactory;

    public function scheduler(): SchedulerInterface
    {
        return $this->scheduler;
    }

    public function cancellationScope(): CancellationScopeInterface
    {
        return $this->rootScope;
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

    public function telemetry(): ?TelemetryBridgeInterface
    {
        return $this->telemetry;
    }

    public function activePollHandleCount(): int
    {
        return $this->verifierPoller->activeHandleCount();
    }

    public function makeExecutionScope(string $discriminant): ExecutionScopeInterface
    {
        return new ReferenceExecutionScope($discriminant);
    }

    /**
     * Initialises the scheduler, clock, publisher, and poller/storage handles
     * from the supplied runtime primitives.
     *
     * Called by each concrete fixture's constructor after constructing the
     * runtime-specific poller and context storage.
     */
    private function initRuntimeFixture(
        PollerPortInterface $verifierPoller,
        ContextStorageInterface $storage,
        PollerPortInterface $schedulerPoller,
    ): void {
        $this->verifierPoller = $verifierPoller;
        $this->storage = $storage;
        $this->clock = new DeterministicMonotonicClock();
        $this->publisher = new NullArtifactPublisher();
        $this->telemetry = new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );
        $this->cancellationScopeFactory = new CancellationScopeFactory();
        $this->rootScope = $this->cancellationScopeFactory->create();

        $sequenceAllocator = new InMemorySequenceAllocator();
        $executionId = ExecutionId::fromString('ref-exec');

        $this->scheduler = new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(new RandomIdentifierGenerator()),
            $this->cancellationScopeFactory,
            $this->clock,
            $executionId,
            $schedulerPoller,
            $storage,
            new ReferenceExecutionScopeFactory(),
            $this->publisher,
            new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId),
            new TelemetryBridgeEventListener($this->telemetry),
        );
    }
}

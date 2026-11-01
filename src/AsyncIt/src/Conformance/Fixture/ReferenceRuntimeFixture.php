<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Application\Factory\CascadingCancellationScopeFactory;
use ItPack\AsyncIt\Application\Factory\FiberFactory;
use ItPack\AsyncIt\Application\Factory\ReferenceExecutionScopeFactory;
use ItPack\AsyncIt\Application\Factory\SchedulerLifecycleArtifactFactory;
use ItPack\AsyncIt\Application\Factory\TaskFactory;
use ItPack\AsyncIt\Application\Factory\TaskIdFactory;
use ItPack\AsyncIt\Application\Factory\TaskRecordFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Application\Publisher\NullArtifactPublisher;
use ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler;
use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\AsyncIt\Contract\TelemetryBridgeInterface;
use ItPack\AsyncIt\Domain\CancellationScope;
use ItPack\AsyncIt\SDK\Support\ArrayTelemetryRecorder;
use ItPack\AsyncIt\SDK\Support\LazyTelemetryBridge;
use ItPack\AsyncIt\SDK\Support\TelemetryBridgeEventListener;
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
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
use ItPack\RunIt\Domain\Execution\ExecutionId;

final readonly class ReferenceRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    public const string PORT_NAME = 'AsyncRuntime';

    private SelectPoller $verifierPoller;

    private MemoryContextStorage $storage;

    private DeterministicMonotonicClock $clock;

    private NullArtifactPublisher $publisher;

    private LazyTelemetryBridge $telemetry;

    private FiberTaskScheduler $scheduler;

    private CancellationScopeInterface $rootScope;

    public function __construct()
    {
        $this->verifierPoller = new SelectPoller();
        $this->storage = new MemoryContextStorage();
        $this->clock = new DeterministicMonotonicClock();
        $this->publisher = new NullArtifactPublisher();
        $this->telemetry = new LazyTelemetryBridge(
            static fn (): ArrayTelemetryRecorder => new ArrayTelemetryRecorder(),
        );
        $this->rootScope = new CancellationScope();

        $executionId = ExecutionId::fromString('ref-exec');
        $sequenceAllocator = new InMemorySequenceAllocator();

        $this->scheduler = new FiberTaskScheduler(
            new FiberFactory(),
            new TaskRecordFactory(),
            new TaskFactory(
                new TaskIdFactory(new RandomIdentifierGenerator()),
            ),
            new CascadingCancellationScopeFactory($this->rootScope),
            $this->clock,
            $executionId,
            $this->verifierPoller,
            $this->storage,
            new ReferenceExecutionScopeFactory(),
            $this->publisher,
            new SchedulerLifecycleArtifactFactory($sequenceAllocator, $executionId),
            new TelemetryBridgeEventListener($this->telemetry),
        );
    }

    public function portName(): string
    {
        return self::PORT_NAME;
    }

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

    public function telemetry(): TelemetryBridgeInterface
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
}

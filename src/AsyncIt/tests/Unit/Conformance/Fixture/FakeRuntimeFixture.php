<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Fixture;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * Configurable AsyncIt runtime fixture for unit-testing the verifiers.
 *
 * It injects the collaborators a verifier actually exercises and stubs the rest;
 * introspection values (`activePollHandleCount`) are set directly so conformance
 * Assertion C can be driven without a full reactor. It reuses the RunIt port
 * interfaces exactly as the production runtime must.
 */
final class FakeRuntimeFixture implements AsyncRuntimeFixtureInterface
{
    /**
     * @param SchedulerInterface|null $scheduler
     * @param PollerPortInterface|null $poller
     * @param MonotonicClockInterface|null $clock
     * @param ArtifactPublisherInterface|null $publisher
     */
    public function __construct(
        private readonly ContextStorageInterface $contextStorage,
        private readonly int $activeHandles,
        private readonly ?SchedulerInterface $scheduler = null,
        private readonly ?PollerPortInterface $poller = null,
        private readonly ?MonotonicClockInterface $clock = null,
        private readonly ?ArtifactPublisherInterface $publisher = null,
    ) {
    }

    public function portName(): string
    {
        return 'AsyncRuntime';
    }

    public function scheduler(): SchedulerInterface
    {
        return $this->scheduler ?? throw new \LogicException('scheduler not configured for this verifier');
    }

    public function poller(): PollerPortInterface
    {
        return $this->poller ?? throw new \LogicException('poller not configured for this verifier');
    }

    public function contextStorage(): ContextStorageInterface
    {
        return $this->contextStorage;
    }

    public function clock(): MonotonicClockInterface
    {
        return $this->clock ?? throw new \LogicException('clock not configured for this verifier');
    }

    public function publisher(): ArtifactPublisherInterface
    {
        return $this->publisher ?? throw new \LogicException('publisher not configured for this verifier');
    }

    public function activePollHandleCount(): int
    {
        return $this->activeHandles;
    }

    public function makeExecutionScope(string $discriminant): ExecutionScopeInterface
    {
        return new FakeExecutionScope();
    }
}

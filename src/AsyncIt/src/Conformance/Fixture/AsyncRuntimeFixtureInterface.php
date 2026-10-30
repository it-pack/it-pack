<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Fixture;

use ItPack\AsyncIt\Conformance\Contract\PortFixtureInterface;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\SchedulerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;

/**
 * The full runtime under test, supplied by an adapter author to exercise the
 * AsyncIt conformance suite.
 *
 * The fixture reuses RunIt's first-party port interfaces rather than forking
 * them: the runtime's context storage, clock, and artifact publisher are RunIt
 * ports, and the AsyncIt scheduler/poller are AsyncIt ports. A third-party
 * adapter provides one concrete fixture; AsyncIt's reference runtime provides
 * the first-party one.
 *
 * Introspection helpers (`activePollHandleCount()`, `makeExecutionScope()`)
 * exist solely so verifiers can observe leakage and isolation without a
 * concrete consumer; they are not part of the production port surface.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
interface AsyncRuntimeFixtureInterface extends PortFixtureInterface
{
    public function scheduler(): SchedulerInterface;

    /**
     * The root cancellation scope controlling the runtime under test.
     *
     * Used by GracefulShutdownVerifier (Assertion F) to trigger cascading
     * cancellation.
     */
    public function cancellationScope(): CancellationScopeInterface;

    public function poller(): PollerPortInterface;

    public function contextStorage(): ContextStorageInterface;

    public function clock(): MonotonicClockInterface;

    public function publisher(): ArtifactPublisherInterface;

    /**
     * Number of poll handles the runtime still holds and has not released.
     *
     * Must return 0 once every {@see PollerPortInterface::release()} the runtime
     * requested has been honoured — the basis for conformance Assertion C.
     */
    public function activePollHandleCount(): int;

    /**
     * Builds an execution scope carrying the given discriminant, used to prove
     * that re-attaching a token restores exactly its own scope (Assertion A).
     */
    public function makeExecutionScope(string $discriminant): ExecutionScopeInterface;
}

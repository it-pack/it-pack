<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionLifecycleCoordinatorInterface;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException;
use ItPack\RunIt\SDK\RunItInterface;
use Throwable;

/**
 * Immutable assembler for a {@see RunItInterface} runtime.
 *
 * Each `with*` method returns a new builder, so configuration can be shared and extended without
 * mutation. Port implementations (publisher, clock, sequence allocator, snapshot serializer, and
 * identifier generator) must be supplied by the caller: RunIt ships no concrete adapter defaults in
 * the SDK layer. Application and domain collaborators receive safe defaults when not configured.
 *
 * Call {@see self::build()} once the required ports are present. {@see self::build()} validates the
 * configuration and throws {@see \ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException} for any
 * required dependency that is still missing.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RunItBuilderInterface
{
    public static function create(): self;

    public function withPublisher(ArtifactPublisherInterface $publisher): self;

    public function withClock(MonotonicClockInterface $clock): self;

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): self;

    public function withSnapshotSerializer(SerializerInterface $serializer): self;

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): self;

    public function withContextStorage(ContextStorageInterface $storage): self;

    public function withSnapshotStore(?SnapshotStoreInterface $store): self;

    public function withThrowableMapper(ThrowableMapperInterface $mapper): self;

    /**
     * Overrides the default redactor. When this method is never called, the
     * built runtime installs the default redactor.
     * Its current throwable masking does not cover every artifact shape, and a
     * configured sampler receives the artifact before redaction. Call
     * `withRedactor($custom)` to supply a custom strategy — including one
     * configured with `sensitiveDataKeys` for occurrence/snapshot payload
     * masking — or `withRedactor(null)` to explicitly publish unredacted (the
     * choice is recorded, so null is an opt-out, not a reset to defaults).
     */
    public function withRedactor(?RedactorInterface $redactor): self;

    public function withSamplingPolicy(?SamplingPolicyInterface $policy): self;

    public function withNestedExecutionPolicy(NestedExecutionPolicyInterface $policy): self;

    public function withCompletionPolicy(ExecutionCompletionPolicyInterface $policy): self;

    /**
     * Runs consumer operations through the supplied engine (for example a Fiber engine) instead of
     * the default synchronous engine. The runner still owns execution semantics.
     */
    public function withExecutionEngine(ExecutionEngineInterface $engine): self;

    /**
     * Overrides the hash calculator used for snapshot integrity and artifact fingerprints.
     * Replaces the default {@see \ItPack\RunIt\Domain\Shared\DeterministicHashCalculator} and is also
     * wired into the default snapshot factory.
     */
    public function withHashCalculator(HashCalculatorInterface $hashCalculator): self;

    /**
     * Overrides the execution factory that materializes a domain execution from a definition.
     */
    public function withExecutionFactory(ExecutionFactoryInterface $executionFactory): self;

    /**
     * Overrides the lifecycle coordinator that owns the execution state machine.
     */
    public function withLifecycleCoordinator(
        ExecutionLifecycleCoordinatorInterface $coordinator,
    ): self;

    /**
     * Overrides the snapshot factory that builds execution snapshots.
     */
    public function withSnapshotFactory(ExecutionSnapshotFactoryInterface $snapshotFactory): self;

    /**
     * Overrides the data-protection bounds enforced by the runner's bounded publisher and the
     * activity-depth gate. Replaces the conservative {@see SecurityLimits::default()}; limits are
     * never widened silently at any later stage.
     */
    public function withSecurityLimits(SecurityLimits $limits): self;

    /**
     * Receives internal publication failures (adapter faults, terminal-publication faults, or
     * secondary completion-path faults) so they can be reported without ever replacing the
     * business result or exception. Without a handler, publication failures follow the fail-open
     * default and are not observable.
     *
     * The handler is invoked with exactly one argument (the fault). When the fault originates
     * from a bound-exceeded drop, a `BoundDropException` notice carrying the drop reason is
     * passed instead of a raw adapter error. Throwing from the handler is safe: the fault is
     * contained by the fallback sink and never reaches business flow.
     *
     * @param Closure(Throwable): void|null $handler
     */
    public function withPublicationFailureHandler(?Closure $handler): self;

    /**
     * Receives snapshot-store failures so persistence problems stay observable without aborting
     * the execution. Without a handler, store failures follow the fail-open default.
     *
     * @param Closure(Throwable): void|null $handler
     */
    public function withSnapshotStoreFailureHandler(?Closure $handler): self;

    /**
     * Overrides how the redaction/sampling stack is composed around the base publisher.
     * The default factory applies redaction closest to the sink and sampling outside it;
     * an override must preserve that security ordering.
     */
    public function withPublisherStackFactory(PublisherStackFactoryInterface $factory): self;

    /**
     * Builds an interface-backed runtime facade, validating required dependencies first.
     *
     * @throws MissingBuilderDependencyException when a required dependency is missing
     */
    public function build(): RunItInterface;
}

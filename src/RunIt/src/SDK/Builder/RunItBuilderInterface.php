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
    public static function create(): RunItBuilderInterface;

    public function withPublisher(ArtifactPublisherInterface $publisher): RunItBuilderInterface;

    public function withClock(MonotonicClockInterface $clock): RunItBuilderInterface;

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): RunItBuilderInterface;

    public function withSnapshotSerializer(SerializerInterface $serializer): RunItBuilderInterface;

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): RunItBuilderInterface;

    public function withContextStorage(ContextStorageInterface $storage): RunItBuilderInterface;

    public function withSnapshotStore(?SnapshotStoreInterface $store): RunItBuilderInterface;

    public function withThrowableMapper(ThrowableMapperInterface $mapper): RunItBuilderInterface;

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
    public function withRedactor(?RedactorInterface $redactor): RunItBuilderInterface;

    public function withSamplingPolicy(?SamplingPolicyInterface $policy): RunItBuilderInterface;

    public function withNestedExecutionPolicy(NestedExecutionPolicyInterface $policy): RunItBuilderInterface;

    public function withCompletionPolicy(ExecutionCompletionPolicyInterface $policy): RunItBuilderInterface;

    /**
     * Runs consumer operations through the supplied engine (for example a Fiber engine) instead of
     * the default synchronous engine. The runner still owns execution semantics.
     */
    public function withExecutionEngine(ExecutionEngineInterface $engine): RunItBuilderInterface;

    /**
     * Overrides the hash calculator used for snapshot integrity and artifact fingerprints.
     * Replaces the default {@see \ItPack\RunIt\Domain\Shared\DeterministicHashCalculator} and is also
     * wired into the default snapshot factory.
     */
    public function withHashCalculator(HashCalculatorInterface $hashCalculator): RunItBuilderInterface;

    /**
     * Overrides the execution factory that materializes a domain execution from a definition.
     */
    public function withExecutionFactory(ExecutionFactoryInterface $executionFactory): RunItBuilderInterface;

    /**
     * Overrides the lifecycle coordinator that owns the execution state machine.
     */
    public function withLifecycleCoordinator(
        ExecutionLifecycleCoordinatorInterface $coordinator,
    ): RunItBuilderInterface;

    /**
     * Overrides the snapshot factory that builds execution snapshots.
     */
    public function withSnapshotFactory(ExecutionSnapshotFactoryInterface $snapshotFactory): RunItBuilderInterface;

    /**
     * Overrides the data-protection bounds enforced by the runner's bounded publisher and the
     * activity-depth gate. Replaces the conservative {@see SecurityLimits::default()}; limits are
     * never widened silently at any later stage.
     */
    public function withSecurityLimits(SecurityLimits $limits): RunItBuilderInterface;

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
    public function withPublicationFailureHandler(?Closure $handler): RunItBuilderInterface;

    /**
     * Receives snapshot-store failures so persistence problems stay observable without aborting
     * the execution. Without a handler, store failures follow the fail-open default.
     *
     * @param Closure(Throwable): void|null $handler
     */
    public function withSnapshotStoreFailureHandler(?Closure $handler): RunItBuilderInterface;

    /**
     * Overrides how the redaction/sampling stack is composed around the base publisher.
     * The default factory applies redaction before sampling, so samplers receive redacted artifacts;
     * an override must preserve that security ordering.
     */
    public function withPublisherStackFactory(PublisherStackFactoryInterface $factory): RunItBuilderInterface;

    /**
     * Builds an interface-backed runtime facade, validating required dependencies first.
     *
     * @throws MissingBuilderDependencyException when a required dependency is missing
     */
    public function build(): RunItInterface;
}

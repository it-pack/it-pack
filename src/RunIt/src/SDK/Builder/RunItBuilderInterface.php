<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Builder;

use ItPack\RunIt\Contract\Application\ExecutionCompletionPolicyInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\SDK\RunItInterface;

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

    /**
     * Configures the monotonic clock; the single {@see MonotonicClockInterface} supplies both wall-clock
     * and monotonic time, so this is an alias of {@see self::withClock()}.
     */
    public function withMonotonicClock(MonotonicClockInterface $clock): RunItBuilderInterface;

    public function withSequenceAllocator(SequenceAllocatorInterface $allocator): RunItBuilderInterface;

    public function withSnapshotSerializer(SerializerInterface $serializer): RunItBuilderInterface;

    public function withIdentifierGenerator(IdentifierGeneratorInterface $generator): RunItBuilderInterface;

    public function withContextStorage(ContextStorageInterface $storage): RunItBuilderInterface;

    public function withSnapshotStore(?SnapshotStoreInterface $store): RunItBuilderInterface;

    public function withThrowableMapper(ThrowableMapperInterface $mapper): RunItBuilderInterface;

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
     * Builds an interface-backed runtime facade, validating required dependencies first.
     *
     * @throws \ItPack\RunIt\SDK\Exception\MissingBuilderDependencyException when a required dependency is missing
     */
    public function build(): RunItInterface;
}

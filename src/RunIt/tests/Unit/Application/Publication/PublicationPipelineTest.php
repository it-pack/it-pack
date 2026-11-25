<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\ArtifactTypeFilter;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferOverflowException;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\FilteringArtifactPublisher;
use ItPack\RunIt\Application\Publication\GuardedArtifactPublisher;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\ObservationSeverityFilter;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\PublicationGuard;
use ItPack\RunIt\Application\Publication\PublicationPipeline;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Contract\Port\HealthAwarePublisherStackFactoryInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\PublisherStackFactoryInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

/**
 * @group application
 * @group publication
 */
#[CoversClass(PublicationPipeline::class)]
#[UsesClass(DefaultArtifactByteEstimator::class)]
#[UsesClass(SystemMonotonicClock::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(PublisherStackFactory::class)]
#[UsesClass(ArtifactRedactionConfig::class)]
#[UsesClass(ArtifactTypeFilter::class)]
#[UsesClass(BufferConfiguration::class)]
#[UsesClass(CallbackArtifactPublisher::class)]
#[UsesClass(DefaultArtifactRedactor::class)]
#[UsesClass(FailClosedPolicy::class)]
#[UsesClass(GuardedArtifactPublisher::class)]
#[UsesClass(NeverSamplePolicy::class)]
#[UsesClass(ObservationSeverityFilter::class)]
#[UsesClass(BufferedArtifactPublisher::class)]
#[UsesClass(FailOpenPolicy::class)]
#[UsesClass(FailSafeArtifactPublisher::class)]
#[UsesClass(FilteringArtifactPublisher::class)]
#[UsesClass(PublicationGuard::class)]
#[UsesClass(RedactedArtifact::class)]
#[UsesClass(RedactingArtifactPublisher::class)]
#[UsesClass(SamplingArtifactPublisher::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
#[UsesClass(Occurrence::class)]
#[UsesClass(OccurrenceId::class)]
#[UsesClass(OccurrenceType::class)]
#[UsesClass(AttributeCollection::class)]
#[UsesClass(Instant::class)]
#[UsesClass(ProvenanceAuthority::class)]
#[UsesClass(BufferOverflowException::class)]
final class PublicationPipelineTest extends Unit
{
    private MonotonicClockInterface $clock;

    private ArtifactByteEstimatorInterface $estimator;

    protected function setUp(): void
    {
        $this->clock = new SystemMonotonicClock();
        $this->estimator = new DefaultArtifactByteEstimator();
    }

    private function observation(): ExecutionArtifactInterface
    {
        return Observation::info(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'login',
            attributes: AttributeCollection::fromArray(['user' => 'alice', 'password' => 'hunter2']),
        );
    }

    private function occurrence(): ExecutionArtifactInterface
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
        );
    }

    public function testBuildWrapsTheSinkWithBufferAndSamplingWhenConfigured(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            $sink,
            $this->clock,
            $this->estimator,
            sampling: new NeverSamplePolicy(),
            buffer: new BufferConfiguration(
                maxItems: 8,
                maxEstimatedBytes: 10_000,
                maxAgeSeconds: 60,
                batchSize: 4,
                flushIntervalSeconds: 0,
                shutdownFlushBudget: 1024,
                overflow: OverflowStrategy::DropOldest,
            ),
            stackFactory: new UnsafePipelineTestStackFactory(),
        );

        $publisher = $pipeline->build();

        self::assertInstanceOf(GuardedArtifactPublisher::class, $publisher, 'the guard is the outermost stage');
        self::assertInstanceOf(FlushablePublisherInterface::class, $publisher);

        $publisher->publish($this->observation());
        $publisher->flush();

        self::assertSame(
            [],
            $sink->published(),
            'sampling dropped everything before the sink'
        );
    }

    public function testBufferingHoldsAcceptedAndRejectedUntilFlush(): void
    {
        $sampler = new class () implements SamplingPolicyInterface {
            private bool $acceptNext = true;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $decision = $this->acceptNext;
                $this->acceptNext = !$this->acceptNext;
                return $decision;
            }
        };

        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            sampling: $sampler,
            buffer: new BufferConfiguration(
                maxItems: 8,
                maxEstimatedBytes: 10_000,
                maxAgeSeconds: 60,
                batchSize: 4,
                flushIntervalSeconds: 0,
                shutdownFlushBudget: 1024,
                overflow: OverflowStrategy::DropOldest,
            ),
            stackFactory: new UnsafePipelineTestStackFactory(),
        )->build();

        $first = $this->observation();
        $second = Observation::info(ObservationId::fromString('second'), ExecutionId::fromString('exec-1'), 'second');

        $pipeline->publish($first);
        $pipeline->publish($second);

        self::assertSame([], $sink->published(), 'Neither artifact reaches the sink before flush.');

        self::assertInstanceOf(FlushablePublisherInterface::class, $pipeline);
        $pipeline->flush();

        self::assertCount(1, $sink->published(), 'Only the accepted artifact reaches the sink after flush.');
        self::assertSame(
            [$first->normalize()['id'] ?? null],
            array_map(
                static function (ExecutionArtifactInterface $artifact): mixed {
                    $normalized = $artifact->normalize();

                    return is_array($normalized) ? ($normalized['id'] ?? null) : null;
                },
                $sink->published(),
            ),
        );
    }

    #[DataProvider('bufferFailurePolicies')]
    public function testBufferedOverflowAppliesFailurePolicy(bool $failClosed): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $first = $this->observation();
        $second = Observation::info(ObservationId::fromString('second'), ExecutionId::fromString('exec-1'), 'second');
        $firstId = $authority->bindArtifact('observation', 'observation', 'exec-1', 'obs', 'execution-observation');
        $secondId = $authority->bindArtifact(
            'observation',
            'observation',
            'exec-1',
            'second',
            'execution-observation',
        );
        self::assertTrue($authority->attest($first, $firstId));
        self::assertTrue($authority->attest($second, $secondId));

        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            buffer: new BufferConfiguration(
                maxItems: 1,
                maxEstimatedBytes: 10_000,
                maxAgeSeconds: 60,
                batchSize: 1,
                flushIntervalSeconds: 0,
                shutdownFlushBudget: 1,
                overflow: OverflowStrategy::Fail,
            ),
            failurePolicy: $failClosed ? new FailClosedPolicy() : new FailOpenPolicy(),
            stackFactory: new VerifiedPipelineTestStackFactory($authority),
        )->build();

        $pipeline->publish($first);
        self::assertSame([], $sink->published(), 'The first artifact is buffered, not immediately delivered.');

        $failure = null;
        try {
            $pipeline->publish($second);
        } catch (BufferOverflowException $bufferOverflowException) {
            $failure = $bufferOverflowException;
        }

        if ($failClosed) {
            self::assertInstanceOf(BufferOverflowException::class, $failure);
            self::assertSame('Artifact buffer is full.', $failure->getMessage());
        } else {
            self::assertNull($failure);
        }

        self::assertSame([], $sink->published());

        self::assertInstanceOf(FlushablePublisherInterface::class, $pipeline);
        $pipeline->flush();
        self::assertCount(1, $sink->published());
        self::assertSame([$firstId], array_map(
            static function (ExecutionArtifactInterface $artifact): mixed {
                $normalized = $artifact->normalize();

                return is_array($normalized) ? ($normalized['id'] ?? null) : null;
            },
            $sink->published(),
        ));

        $pipeline->publish($second);
        self::assertCount(1, $sink->published());
        $pipeline->flush();
        self::assertCount(2, $sink->published());
        self::assertSame([$firstId, $secondId], array_map(
            static function (ExecutionArtifactInterface $artifact): mixed {
                $normalized = $artifact->normalize();

                return is_array($normalized) ? ($normalized['id'] ?? null) : null;
            },
            $sink->published(),
        ));
    }

    public function testBufferedOverflowDefaultPolicyDoesNotThrow(): void
    {
        $authority = new ProvenanceAuthority();
        $authority->issueExportId('execution', 'execution', 'exec-1', 'exec-1');

        $first = $this->observation();
        $second = Observation::info(ObservationId::fromString('second'), ExecutionId::fromString('exec-1'), 'second');
        $firstId = $authority->bindArtifact('observation', 'observation', 'exec-1', 'obs', 'execution-observation');
        $secondId = $authority->bindArtifact(
            'observation',
            'observation',
            'exec-1',
            'second',
            'execution-observation',
        );
        self::assertTrue($authority->attest($first, $firstId));
        self::assertTrue($authority->attest($second, $secondId));

        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            buffer: new BufferConfiguration(
                maxItems: 1,
                maxEstimatedBytes: 10_000,
                maxAgeSeconds: 60,
                batchSize: 1,
                flushIntervalSeconds: 0,
                shutdownFlushBudget: 1,
                overflow: OverflowStrategy::Fail,
            ),
            stackFactory: new VerifiedPipelineTestStackFactory($authority),
        )->build();

        $pipeline->publish($first);
        self::assertSame([], $sink->published(), 'The first artifact is buffered, not immediately delivered.');

        $failure = null;
        try {
            $pipeline->publish($second);
        } catch (BufferOverflowException $bufferOverflowException) {
            $failure = $bufferOverflowException;
        }

        self::assertNull($failure, 'The default failure policy must not throw on overflow.');

        self::assertSame([], $sink->published());

        self::assertInstanceOf(FlushablePublisherInterface::class, $pipeline);
        $pipeline->flush();
        self::assertCount(1, $sink->published());
        self::assertSame([$firstId], array_map(
            static function (ExecutionArtifactInterface $artifact): mixed {
                $normalized = $artifact->normalize();

                return is_array($normalized) ? ($normalized['id'] ?? null) : null;
            },
            $sink->published(),
        ));
    }

    /** @return array<string, array{bool}> */
    public static function bufferFailurePolicies(): array
    {
        return ['fail open' => [false], 'fail closed' => [true]];
    }

    public function testPipelineFiltersRedactsAndPublishes(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            filter: new ArtifactTypeFilter([Observation::class]),
            redactor: new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password'])),
            stackFactory: new UnsafePipelineTestStackFactory(),
        )->build();

        $pipeline->publish($this->observation());
        $pipeline->publish($this->occurrence());

        self::assertCount(1, $sink->published(), 'The occurrence is filtered out before the sink.');
        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        /** @var array<string, mixed> $attrs */
        $attrs = $normalized['attributes'];
        self::assertSame('[redacted]', $attrs['password']);
    }

    public function testPipelineFailClosedPropagates(): void
    {
        $sink = new CallbackArtifactPublisher(static function (): never {
            throw new RuntimeException('transport down');
        });
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            failurePolicy: new FailClosedPolicy(),
            stackFactory: new UnsafePipelineTestStackFactory(),
        )->build();

        $thrown = null;
        try {
            $pipeline->publish($this->observation());
        } catch (RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        self::assertNotNull($thrown);
    }

    public function testPipelineFailOpenSwallows(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            $sink,
            $this->clock,
            $this->estimator,
            stackFactory: new UnsafePipelineTestStackFactory(),
        )->build();

        $pipeline->publish($this->observation());

        self::assertCount(1, $sink->published());
    }

    public function testPipelineSeverityFloorDropsNoiseBeforeSink(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            filter: new ObservationSeverityFilter(ObservationSeverity::Error->level()),
            stackFactory: new UnsafePipelineTestStackFactory(),
        )->build();

        $pipeline->publish(Observation::debug(
            ObservationId::fromString('d'),
            ExecutionId::fromString('exec-1'),
            'noise'
        ));
        $pipeline->publish(Observation::error(
            ObservationId::fromString('e'),
            ExecutionId::fromString('exec-1'),
            'boom'
        ));

        self::assertCount(1, $sink->published());
    }

    public function testBuildUsesHealthAwareStackFactoryWhenHealthIsProvided(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
            }
        };
        $stackFactory = new class () implements HealthAwarePublisherStackFactoryInterface {
            public function compose(
                ArtifactPublisherInterface $base,
                ?RedactorInterface $redactor,
                ?SamplingPolicyInterface $samplingPolicy,
            ): ArtifactPublisherInterface {
                return $base;
            }

            public function composeWithHealth(
                ArtifactPublisherInterface $base,
                ?RedactorInterface $redactor,
                ?SamplingPolicyInterface $samplingPolicy,
                InternalHealthReporterInterface $health,
            ): ArtifactPublisherInterface {
                return $base;
            }
        };

        $sink = new InMemoryArtifactPublisher();
        $pipeline = new PublicationPipeline(
            sink: $sink,
            clock: $this->clock,
            byteEstimator: $this->estimator,
            health: $health,
            stackFactory: $stackFactory,
        );

        $publisher = $pipeline->build();

        $publisher->publish($this->observation());
        self::assertCount(1, $sink->published());
    }
}

/** Uses the production classifier stack with a test-owned provenance authority. */
final readonly class VerifiedPipelineTestStackFactory implements PublisherStackFactoryInterface
{
    public function __construct(private ProvenanceAuthority $authority)
    {
    }

    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        return new PublisherStackFactory()->compose($base, $redactor, $samplingPolicy, authority: $this->authority);
    }
}

/** Deliberately bypasses provenance for isolated pipeline-stage tests. */
final class UnsafePipelineTestStackFactory implements PublisherStackFactoryInterface
{
    public function compose(
        ArtifactPublisherInterface $base,
        ?RedactorInterface $redactor,
        ?SamplingPolicyInterface $samplingPolicy,
    ): ArtifactPublisherInterface {
        if ($samplingPolicy instanceof SamplingPolicyInterface) {
            $base = new SamplingArtifactPublisher($samplingPolicy, $base);
        }

        return $redactor instanceof RedactorInterface ? new RedactingArtifactPublisher($redactor, $base) : $base;
    }
}

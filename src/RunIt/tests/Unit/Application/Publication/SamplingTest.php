<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Factory\PublisherStackFactory;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\ClassifiedArtifact;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\DefaultSdkRedactor;
use ItPack\RunIt\Application\Publication\ErrorOverrideSamplePolicy;
use ItPack\RunIt\Application\Publication\ExecutionKindSamplePolicy;
use ItPack\RunIt\Application\Publication\HeadBasedSamplingPolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Publication\RatioSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingDropException;
use ItPack\RunIt\Application\Publication\SeveritySamplePolicy;
use ItPack\RunIt\Application\Publication\VerifiedArtifactProjection;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Duration;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use stdClass;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;
use Throwable;

/**
 * @group application
 * @group publication
 */
#[CoversClass(PublisherStackFactory::class)]
#[CoversClass(AlwaysSamplePolicy::class)]
#[CoversClass(ClassifyingArtifactPublisher::class)]
#[CoversClass(ErrorOverrideSamplePolicy::class)]
#[CoversClass(ExecutionKindSamplePolicy::class)]
#[CoversClass(HeadBasedSamplingPolicy::class)]
#[CoversClass(NeverSamplePolicy::class)]
#[CoversClass(RatioSamplePolicy::class)]
#[CoversClass(SamplingArtifactPublisher::class)]
#[CoversClass(SeveritySamplePolicy::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(ArtifactRedactionConfig::class)]
#[UsesClass(DefaultArtifactRedactor::class)]
#[UsesClass(RedactedArtifact::class)]
#[UsesClass(RedactingArtifactPublisher::class)]
#[UsesClass(VerifiedArtifactProjection::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(ExecutionKind::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
#[UsesClass(AttributeCollection::class)]
#[UsesClass(SecurityLimits::class)]
final class SamplingTest extends Unit
{
    public function testVerifiedTerminalSnapshotIsSanitizedBeforeSamplerAndSink(): void
    {
        $authority = new ProvenanceAuthority();
        $executionId = $authority->issueExportId('execution', 'execution', 'business-exec', 'business-exec');
        $snapshotId = $authority->bindArtifact(
            'snapshot',
            'terminal-snapshot',
            'business-exec',
            'business-snapshot',
            'execution-terminal-snapshot',
        );
        $snapshot = SecretFieldsSnapshot::create(
            SnapshotId::fromString('business-snapshot'),
            ExecutionId::fromString('business-exec'),
            SnapshotType::fromString('secret-snapshot-type'),
            1,
            ['id' => 'business-secret'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return 'secret-hash';
                }
            },
            classification: SnapshotClassification::Terminal,
            contentType: 'secret-content-type',
            attributes: AttributeCollection::fromArray(['secret-key' => 'secret-attribute']),
        );
        self::assertTrue($authority->attest($snapshot, $snapshotId));
        $policy = new class () implements SamplingPolicyInterface {
            /** @var list<array<string, mixed>> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact->normalize();
                return true;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        new PublisherStackFactory()->compose($sink, new DefaultSdkRedactor(), $policy, authority: $authority)
            ->publish($snapshot)
        ;

        self::assertCount(1, $policy->seen);
        self::assertCount(1, $sink->published());
        self::assertArrayNotHasKey('payload', $policy->seen[0]);
        self::assertArrayNotHasKey('content_hash', $policy->seen[0]);
        foreach (['type', 'content_type', 'attributes', 'extra_secret', 'schema_version'] as $field) {
            self::assertArrayNotHasKey($field, $policy->seen[0]);
            self::assertArrayNotHasKey($field, $sink->published()[0]->normalize());
        }

        self::assertSame($executionId, $policy->seen[0]['execution_id']);
        self::assertSame($snapshotId, $policy->seen[0]['id']);
        self::assertSame('terminal', $policy->seen[0]['classification']);
        self::assertSame($snapshot->timestamp(), $policy->seen[0]['created_at']);
        self::assertSame($snapshot->sequence(), $policy->seen[0]['sequence']);
        self::assertEquals($policy->seen[0], $sink->published()[0]->normalize());
        self::assertSame(['id' => 'business-secret'], $snapshot->normalize()['payload']);

        $optOutSink = new InMemoryArtifactPublisher();
        new PublisherStackFactory()->compose($optOutSink, null, $policy, authority: $authority)
            ->publish($snapshot)
        ;
        self::assertCount(2, $policy->seen);
        self::assertCount(1, $optOutSink->published());
        self::assertEquals($policy->seen[0], $policy->seen[1]);
        self::assertEquals($policy->seen[1], $optOutSink->published()[0]->normalize());
        self::assertStringNotContainsString('secret', json_encode($policy->seen[1], JSON_THROW_ON_ERROR));
    }

    public function testVerifiedCustomSnapshotClassificationOmitsUnclassifiedPayload(): void
    {
        foreach ([true, false] as $terminal) {
            $health = $this->createMock(InternalHealthReporterInterface::class);
            $health->expects($this->never())->method('report');
            $authority = new ProvenanceAuthority($health);
            $executionId = $authority->issueExportId('execution', 'execution', 'business-exec', 'business-exec');
            $snapshotId = $authority->bindArtifact(
                'snapshot',
                $terminal ? 'terminal-snapshot' : 'snapshot',
                'business-exec',
                'business-snapshot',
                $terminal ? 'execution-terminal-snapshot' : 'execution-snapshot',
            );
            $classification = new readonly class ($terminal) implements SnapshotClassificationInterface {
                public function __construct(private bool $terminal)
                {
                }

                public function label(): string
                {
                    return $this->terminal ? 'terminal' : 'checkpoint';
                }

                public function isTerminal(): bool
                {
                    return $this->terminal;
                }
            };
            $snapshot = Snapshot::create(
                SnapshotId::fromString('business-snapshot'),
                ExecutionId::fromString('business-exec'),
                SnapshotType::fromString('snapshot'),
                1,
                ['secret' => 'business-secret'],
                new class () implements HashCalculatorInterface {
                    public function calculate(string $content): string
                    {
                        return 'secret-hash';
                    }
                },
                classification: $classification,
            );
            self::assertTrue($authority->attest($snapshot, $snapshotId));
            $policy = new class () implements SamplingPolicyInterface {
                /** @var list<array<string, mixed>> */
                public array $seen = [];

                public function sample(ExecutionArtifactInterface $artifact): bool
                {
                    $this->seen[] = $artifact->normalize();
                    return true;
                }
            };
            $sink = new InMemoryArtifactPublisher();
            new PublisherStackFactory()->compose(
                $sink,
                new DefaultSdkRedactor(),
                $policy,
                $health,
                authority: $authority
            )
                ->publish($snapshot)
            ;

            self::assertCount(1, $policy->seen);
            self::assertSame($terminal ? 'terminal' : 'checkpoint', $policy->seen[0]['classification']);
            self::assertSame($executionId, $policy->seen[0]['execution_id']);
            self::assertSame($snapshotId, $policy->seen[0]['id']);
            self::assertArrayNotHasKey('payload', $policy->seen[0]);
            self::assertArrayNotHasKey('content_hash', $policy->seen[0]);
            self::assertCount(1, $sink->published());
            self::assertEquals($policy->seen[0], $sink->published()[0]->normalize());

            self::assertSame(['secret' => 'business-secret'], $snapshot->normalize()['payload']);

            if (!$terminal) {
                foreach ([null, new DefaultArtifactRedactor()] as $index => $redactor) {
                    $optOutSink = new InMemoryArtifactPublisher();
                    new PublisherStackFactory()->compose($optOutSink, $redactor, $policy, authority: $authority)
                        ->publish($snapshot)
                    ;
                    self::assertArrayNotHasKey('payload', $policy->seen[$index + 1]);
                    self::assertArrayNotHasKey('content_hash', $policy->seen[$index + 1]);
                    self::assertSame($executionId, $policy->seen[$index + 1]['execution_id']);
                    self::assertSame($snapshotId, $policy->seen[$index + 1]['id']);
                    self::assertStringNotContainsString(
                        'secret',
                        json_encode($policy->seen[$index + 1], JSON_THROW_ON_ERROR),
                    );
                    self::assertEquals($policy->seen[$index + 1], $optOutSink->published()[0]->normalize());
                }
            }
        }
    }

    public function testInvalidSnapshotTimingDropsWithPayloadFreeHealth(): void
    {
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->expects($this->once())->method('report')->willReturnCallback(
            static function (Throwable $failure, ExecutionArtifactInterface $notice): void {
                self::assertStringContainsString('provenance', strtolower($failure->getMessage()));
                self::assertStringNotContainsString('secret', $failure->getMessage());
                self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $notice->normalize());
            },
        );
        $authority = new ProvenanceAuthority($health);
        $authority->issueExportId('execution', 'execution', 'business-exec', 'business-exec');

        $snapshotId = $authority->bindArtifact(
            'snapshot',
            'snapshot',
            'business-exec',
            'business-snapshot',
            'execution-snapshot',
        );
        $snapshot = InvalidTimingSnapshot::create(
            SnapshotId::fromString('business-snapshot'),
            ExecutionId::fromString('business-exec'),
            SnapshotType::fromString('secret-type'),
            1,
            ['secret' => 'secret-payload'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return 'secret-hash';
                }
            },
        );
        self::assertTrue($authority->attest($snapshot, $snapshotId));
        $sampler = $this->createMock(SamplingPolicyInterface::class);
        $sampler->expects($this->never())->method('sample');
        $sink = new InMemoryArtifactPublisher();
        new PublisherStackFactory()->compose($sink, new DefaultSdkRedactor(), $sampler, $health, authority: $authority)
            ->publish($snapshot)
        ;
        self::assertSame([], $sink->published());
    }

    public function testVerifiedSnapshotClassificationTamperingDropsBeforeSampling(): void
    {
        $cases = [
            'terminal to checkpoint' => [SnapshotClassification::Terminal, 'checkpoint', false],
            'checkpoint to terminal' => [SnapshotClassification::Checkpoint, 'terminal', false],
            'removed classification' => [SnapshotClassification::Terminal, null, true],
            'null classification' => [SnapshotClassification::Terminal, null, false],
            'unknown classification' => [SnapshotClassification::Terminal, 'invalid', false],
            'non-string classification' => [SnapshotClassification::Terminal, 123, false],
        ];

        foreach ($cases as $label => [$classification, $replacement, $remove]) {
            $health = $this->createMock(InternalHealthReporterInterface::class);
            $health->expects($this->once())->method('report');
            $authority = new ProvenanceAuthority($health);
            $authority->issueExportId('execution', 'execution', 'business-exec', 'business-exec');
            $terminal = $classification === SnapshotClassification::Terminal;
            $snapshotId = $authority->bindArtifact(
                'snapshot',
                $terminal ? 'terminal-snapshot' : 'snapshot',
                'business-exec',
                'business-snapshot',
                $terminal ? 'execution-terminal-snapshot' : 'execution-snapshot',
            );
            $snapshot = Snapshot::create(
                SnapshotId::fromString('business-snapshot'),
                ExecutionId::fromString('business-exec'),
                SnapshotType::fromString('snapshot'),
                1,
                ['secret' => 'business-secret'],
                new class () implements HashCalculatorInterface {
                    public function calculate(string $content): string
                    {
                        return 'secret-hash';
                    }
                },
                classification: $classification,
            );
            self::assertTrue($authority->attest($snapshot, $snapshotId), $label);

            $controlSink = new InMemoryArtifactPublisher();
            $controlPolicy = $this->createMock(SamplingPolicyInterface::class);
            $controlPolicy->expects($this->once())->method('sample')->willReturn(true);
            new PublisherStackFactory()->compose(
                $controlSink,
                null,
                $controlPolicy,
                authority: $authority,
            )->publish($snapshot);
            self::assertCount(1, $controlSink->published(), $label);

            $redactor = $this->createMock(RedactorInterface::class);
            $redactor->expects($this->once())->method('redact')->willReturnCallback(
                static function (ExecutionArtifactInterface $artifact) use ($replacement, $remove): RedactedArtifact {
                    $fields = $artifact->normalize();
                    if ($remove) {
                        unset($fields['classification']);
                    } else {
                        $fields['classification'] = $replacement;
                    }

                    return new RedactedArtifact($fields, 'snapshot');
                },
            );
            $policy = $this->createMock(SamplingPolicyInterface::class);
            $policy->expects($this->never())->method('sample');
            $sink = new InMemoryArtifactPublisher();
            new PublisherStackFactory()->compose($sink, $redactor, $policy, authority: $authority)->publish($snapshot);
            self::assertSame([], $sink->published(), $label);
        }
    }

    public function testVerifiedUnredactedProjectionRetainsSamplingBehavior(): void
    {
        $info = new VerifiedArtifactProjection(['execution_id' => 'exec-1', 'level' => 200], 'observation');
        $error = new VerifiedArtifactProjection(['execution_id' => 'exec-1', 'level' => 400], 'observation');
        $inner = $this->createMock(SamplingPolicyInterface::class);
        $inner->expects($this->once())->method('sample')->willReturn(false);
        $head = new HeadBasedSamplingPolicy($inner);
        self::assertFalse($head->sample($info));
        self::assertFalse($head->sample($error));

        $override = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());
        self::assertFalse($override->sample($info));
        self::assertTrue($override->sample($error));
        $severity = new SeveritySamplePolicy(400);
        self::assertFalse($severity->sample($info));
        self::assertTrue($severity->sample($error));

        $kind = new ExecutionKindSamplePolicy(
            static fn (ExecutionIdInterface $id): ?ExecutionKind => $id->toString() === 'exec-1'
                ? ExecutionKind::fromString('job') : null,
            ['job' => true],
        );
        self::assertTrue($kind->sample($info));
        self::assertTrue($kind->sample(new VerifiedArtifactProjection(['id' => 'exec-1'], 'execution')));
        self::assertTrue($kind->sample(new RedactedArtifact(['id' => 'exec-1'], 'execution')));
        self::assertFalse($kind->sample(new RedactedArtifact(['execution_id' => 'exec-1'], 'execution')));
    }

    public function testClassifierPreservesTypedActivityDuration(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $activity = RunItArtifactFactory::activity('act-1', 'exec-1', 'work', 'task', 'running')
            ->complete(ActivityStatus::Succeeded, null, Instant::of(1_200), Duration::ofNanoseconds(123))
        ;

        new ClassifyingArtifactPublisher($sink)->publish($activity);

        $published = $sink->published()[0];
        self::assertInstanceOf(Activity::class, $published);
        self::assertSame(123, $published->duration()?->nanoseconds());
    }

    /**
     * @throws Throwable
     */
    public function testClassifierReconstructsLifecycleArtifactsAndSnapshot(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new ClassifyingArtifactPublisher($sink);
        $execution = RunItArtifactFactory::execution('exec-1', 'job', 'task', 'failed');
        $activity = RunItArtifactFactory::activity('act-1', 'exec-1', 'work', 'task', 'failed');
        $snapshot = RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['safe' => 'value']);
        $artifacts = [$execution, $activity, $snapshot];

        foreach ($artifacts as $artifact) {
            $classifier->publish($artifact);
        }

        foreach ([ExecutionInterface::class, ActivityInterface::class, SnapshotInterface::class] as $i => $type) {
            self::assertInstanceOf($type, $sink->published()[$i]);
            self::assertSame(
                $artifacts[$i]->normalize(),
                $sink->published()[$i]->normalize()
            );
            self::assertNotSame([$execution, $activity, $snapshot][$i], $sink->published()[$i]);
        }

        $fields = $snapshot->normalize();
        $fields['content_hash'] = 'invalid';
        $classifier->publish(new RedactedArtifact($fields, 'snapshot'));
        self::assertInstanceOf(SnapshotInterface::class, $sink->published()[3]);
        self::assertSame('invalid', $sink->published()[3]->contentHash());
    }

    public function testClassifierPreservesCustomSnapshotHash(): void
    {
        $snapshot = Snapshot::create(
            SnapshotId::fromString('snap-custom'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('checkpoint'),
            1,
            ['safe' => 'value'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha512', $content);
                }
            },
        );
        $sink = new InMemoryArtifactPublisher();
        new ClassifyingArtifactPublisher($sink)->publish($snapshot);

        self::assertCount(1, $sink->published());
        self::assertInstanceOf(SnapshotInterface::class, $sink->published()[0]);
        self::assertSame($snapshot->normalize(), $sink->published()[0]->normalize());
        self::assertSame($snapshot->contentHash(), $sink->published()[0]->contentHash());
    }

    /**
     * @throws Throwable
     */
    public function testClassifierRejectsUnsupportedArtifactsWithoutExposingPayload(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->expects($this->exactly(3))->method('report')->willReturnCallback(
            static function (Throwable $error, ExecutionArtifactInterface $artifact): void {
                self::assertStringNotContainsString('secret', $error->getMessage());
                self::assertStringNotContainsString('secret', json_encode($artifact->normalize(), JSON_THROW_ON_ERROR));
            },
        );
        $classifier = new ClassifyingArtifactPublisher($sink, $health);
        $classifier->publish(new ClassifiedArtifact(['secret' => str_repeat('x', 10000)], 'unsupported'));
        $classifier->publish(new RedactedArtifact(['secret' => new stdClass()], 'execution_definition'));
        $classifier->publish(new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                throw new RuntimeException('secret should not be normalized');
            }
        });

        self::assertSame([], $sink->published());
    }

    public function testClassifierBoundsTrustedProjection(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $fields = ['id' => 'obs-1', 'execution_id' => 'exec-1', 'message' => str_repeat('x', 5000)];
        new ClassifyingArtifactPublisher($sink)->publish(new ClassifiedArtifact($fields, 'observation'));

        self::assertCount(1, $sink->published());
        self::assertSame(str_repeat('x', 4096), $sink->published()[0]->normalize()['message']);
    }

    public function testClassifierReconstructsOnlySanitizedThrowableDescriptions(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new ClassifyingArtifactPublisher($sink);
        $original = Observation::error(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'message',
            throwableDescription: ThrowableDescription::create('Exception', 'secret', 1, null, 'trace-secret'),
        );
        $fields = $original->normalize();
        self::assertIsArray($fields['throwable']);
        $fields['throwable']['message'] = '[redacted]';
        $fields['throwable']['trace'] = null;
        $classifier->publish(new RedactedArtifact($fields, 'observation'));
        $published = $sink->published()[0];
        self::assertInstanceOf(ObservationInterface::class, $published);
        $throwable = $published->throwableDescription();
        self::assertNotNull($throwable);
        self::assertSame('[redacted]', $throwable->message());
        self::assertNull($throwable->trace());
        self::assertStringNotContainsString('secret', json_encode($published->normalize(), JSON_THROW_ON_ERROR));
        unset($fields['throwable']['code']);
        $classifier->publish(new RedactedArtifact($fields, 'observation'));
        self::assertInstanceOf(ClassifiedArtifact::class, $sink->published()[1]);
    }

    public function testClassifierReconstructsMeasurementsFromSanitizedFields(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new ClassifyingArtifactPublisher($sink);
        $original = Measurement::counter(
            MeasurementName::fromString('requests'),
            ExecutionId::fromString('exec-1'),
            7,
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive']),
        );
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['secret']));
        $classifier->publish($redactor->redact($original));
        $published = $sink->published()[0];
        self::assertInstanceOf(MeasurementInterface::class, $published);
        self::assertNotSame($original, $published);
        self::assertSame('[redacted]', $published->attributes()['secret']);
        self::assertSame(7, $published->value());

        $fields = $original->normalize();
        unset($fields['unit']);
        $classifier->publish(new RedactedArtifact($fields, 'measurement'));
        self::assertInstanceOf(ClassifiedArtifact::class, $sink->published()[1]);
        self::assertArrayNotHasKey('unit', $sink->published()[1]->normalize());
    }

    public function testClassifierDeliversTypedObservationOnlyFromRedactedFields(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new PublisherStackFactory()->compose(
            $sink,
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveAttributeKeys: ['secret'])),
            null,
        );
        $original = Observation::warning(
            ObservationId::fromString('obs-typed'),
            ExecutionId::fromString('exec-typed'),
            'sensitive-message',
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive-attribute']),
        );
        $publisher->publish($original);

        self::assertCount(1, $sink->published());
        $published = $sink->published()[0];
        self::assertInstanceOf(ObservationInterface::class, $published);
        self::assertNotSame($original, $published);
        self::assertStringNotContainsString('sensitive-message', $published->message());
        self::assertSame('[redacted]', $published->attributes()['secret']);
        self::assertStringNotContainsString(
            'sensitive-attribute',
            json_encode($published->normalize(), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @throws Throwable
     */
    public function testClassifierDropsSnapshotsWhenPayloadOrHashChanges(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->expects($this->once())->method('report');
        $classifier = new ClassifyingArtifactPublisher($sink, $health);
        $classifier->publish(RunItArtifactFactory::snapshot('snap-1', 'exec-1', ['safe' => 'value']));
        $classifier->publish(new RedactedArtifact([
            'id' => 'snap-2', 'execution_id' => 'exec-1', 'content_hash' => 'hash',
            'payload' => ['large' => str_repeat('x', 262144)],
        ], 'snapshot'));
        $snapshot = RunItArtifactFactory::snapshot('snap-3', 'exec-1', ['password' => 'secret']);
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveDataKeys: ['password']));
        $classifier->publish($redactor->redact($snapshot));

        self::assertCount(2, $sink->published());
        self::assertArrayNotHasKey('content_hash', $sink->published()[1]->normalize());
        self::assertSame(['password' => '[redacted]'], $sink->published()[1]->normalize()['payload']);
        self::assertSame($snapshot->normalize()['content_hash'], $snapshot->contentHash());
    }

    public function testClassifierPreservesSnapshotPayloadBeyondAttributeCount(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $payload = array_fill_keys(array_map(static fn (int $i): string => 'key' . $i, range(1, 65)), 'value');
        $fields = ['id' => 'snap-many', 'execution_id' => 'exec-1', 'payload' => $payload, 'content_hash' => 'hash'];

        new ClassifyingArtifactPublisher($sink)->publish(new RedactedArtifact($fields, 'snapshot'));

        self::assertCount(1, $sink->published());
        self::assertSame($fields, $sink->published()[0]->normalize());
    }

    public function testClassifierPreservesLongAndDeepSnapshotPayloadWithinLimits(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $payload = str_repeat('x', 5000);
        for ($i = 0; $i < 20; ++$i) {
            $payload = ['a' => $payload];
        }

        $fields = ['id' => 'snap-1', 'execution_id' => 'exec-1', 'content_hash' => 'hash', 'payload' => $payload];

        new ClassifyingArtifactPublisher($sink)->publish(new RedactedArtifact($fields, 'snapshot'));

        self::assertCount(1, $sink->published());
        self::assertSame($fields, $sink->published()[0]->normalize());
    }

    /**
     * @throws Throwable
     */
    public function testClassifierEnforcesSnapshotPayloadByteAndDepthLimits(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->expects($this->exactly(4))->method('report');
        $classifier = new ClassifyingArtifactPublisher(
            $sink,
            $health,
            new SecurityLimits(maxSnapshotPayloadBytes: 5, maxSerializationDepth: 2),
        );
        foreach ([['a' => '1234'], ['a' => ['b' => '123']]] as $payload) {
            $classifier->publish(new RedactedArtifact([
                'id' => 'snap-1', 'execution_id' => 'exec-1', 'payload' => $payload, 'content_hash' => 'hash',
            ], 'snapshot'));
        }

        foreach ([['a' => '12345'], ['a' => ['b' => []]], ['a' => new stdClass()], ['a' => INF]] as $payload) {
            $classifier->publish(new RedactedArtifact([
                'id' => 'snap-2', 'execution_id' => 'exec-1', 'payload' => $payload, 'content_hash' => 'hash',
            ], 'snapshot'));
        }

        self::assertCount(2, $sink->published());
        self::assertSame(['a' => '1234'], $sink->published()[0]->normalize()['payload']);
        self::assertSame(['a' => ['b' => '123']], $sink->published()[1]->normalize()['payload']);
    }

    public function testSnapshotPayloadDoesNotUseGenericNodeOrTotalByteBudgets(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new ClassifyingArtifactPublisher(
            $sink,
            limits: new SecurityLimits(maxSnapshotPayloadBytes: 1_200_000),
        );
        foreach ([array_fill(0, 1100, null), str_repeat('x', 1_100_000)] as $payload) {
            $classifier->publish(new RedactedArtifact([
                'id' => 'snap-1', 'execution_id' => 'exec-1', 'content_hash' => 'hash', 'payload' => $payload,
            ], 'snapshot'));
        }

        self::assertCount(2, $sink->published());
        self::assertSame(array_fill(0, 1100, null), $sink->published()[0]->normalize()['payload']);
        self::assertSame(str_repeat('x', 1_100_000), $sink->published()[1]->normalize()['payload']);
    }

    public function testClassifierDropsChangedHashlessRedactedSnapshotPayload(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $health = $this->createMock(InternalHealthReporterInterface::class);
        $health->expects($this->once())->method('report');
        $classifier = new ClassifyingArtifactPublisher($sink, $health);
        $classifier->publish(new RedactedArtifact([
            'id' => 'snap-1', 'execution_id' => 'exec-1', 'payload' => ['large' => str_repeat('x', 262144)],
        ], 'snapshot'));

        self::assertSame([], $sink->published());
    }

    /**
     * @throws Throwable
     */
    public function testClassifierPreservesRequiredFieldsAndBoundsNestedMaps(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $classifier = new ClassifyingArtifactPublisher($sink, limits: new SecurityLimits(maxAttributeCount: 1));
        $fields = [
            'attributes' => ['first' => 'kept', 'second' => 'omitted'],
            'message' => 'message',
            'id' => 'obs-1',
            'execution_id' => 'exec-1',
        ];
        $classifier->publish(new RedactedArtifact($fields, 'observation'));
        $classifier->publish(new RedactedArtifact([
            'id' => 'obs-1',
            'attributes' => $fields['attributes'],
            'execution_id' => 'exec-1',
            'message' => 'message',
        ], 'observation'));

        self::assertCount(2, $sink->published());
        foreach ($sink->published() as $artifact) {
            $projection = $artifact->normalize();
            self::assertSame('obs-1', $projection['id']);
            self::assertSame('exec-1', $projection['execution_id']);
            self::assertSame(['first' => 'kept'], $projection['attributes']);
            self::assertSame('message', $projection['message']);
            self::assertCount(4, $projection);
        }
    }

    public function testHeadBasedSamplingFallsBackToAnEmptyKeyWhenTheArtifactHasNoStringId(): void
    {
        $inner = new NeverSamplePolicy();
        $policy = new HeadBasedSamplingPolicy($inner);

        $idless = new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return ['sequence' => 1];
            }
        };

        self::assertFalse(
            $policy->sample($idless),
            'an id-less artifact keys on an empty string and follows the inner policy'
        );
    }

    private function observation(string $severity = 'info', string $execution = 'exec-1'): ExecutionArtifactInterface
    {
        $id = ObservationId::fromString('obs');
        $executionId = ExecutionId::fromString($execution);

        return match ($severity) {
            'debug' => Observation::debug($id, $executionId, 'msg'),
            'notice' => Observation::notice($id, $executionId, 'msg'),
            'warning' => Observation::warning($id, $executionId, 'msg'),
            'error' => Observation::error($id, $executionId, 'msg'),
            'critical' => Observation::critical($id, $executionId, 'msg'),
            'alert' => Observation::alert($id, $executionId, 'msg'),
            'emergency' => Observation::emergency($id, $executionId, 'msg'),
            default => Observation::info($id, $executionId, 'msg'),
        };
    }

    public function testAlwaysAndNever(): void
    {
        $alwaysResult = new AlwaysSamplePolicy()->sample($this->observation());
        $neverResult = new NeverSamplePolicy()->sample($this->observation());
        self::assertTrue($alwaysResult);
        self::assertFalse($neverResult);
    }

    public function testRatioIsDeterministic(): void
    {
        self::assertTrue(new RatioSamplePolicy(0.5, static fn (): float => 0.1)->sample($this->observation()));
        self::assertFalse(new RatioSamplePolicy(0.5, static fn (): float => 0.9)->sample($this->observation()));
        self::assertFalse(new RatioSamplePolicy(0.0)->sample($this->observation()));
        self::assertTrue(new RatioSamplePolicy(1.0)->sample($this->observation()));
    }

    public function testRatioApproximatesOverVolume(): void
    {
        $policy = new RatioSamplePolicy(0.5);
        $kept = 0;
        $total = 4000;

        for ($i = 0; $i < $total; $i++) {
            if ($policy->sample($this->observation())) {
                $kept++;
            }
        }

        $rate = $kept / $total;
        self::assertGreaterThan(0.40, $rate);
        self::assertLessThan(0.60, $rate);
    }

    public function testExecutionKindPolicy(): void
    {
        $resolver = static fn (ExecutionIdInterface $id): ?ExecutionKind => match ($id->toString()) {
            'exec-1' => ExecutionKind::fromString('http.request'),
            default => null,
        };

        $policy = new ExecutionKindSamplePolicy($resolver, ['http.request' => true, 'cli.command' => false]);

        self::assertTrue($policy->sample($this->observation('info', 'exec-1')));
        self::assertFalse($policy->sample($this->observation('info', 'exec-2')));
    }

    public function testSeverityPolicyKeepsErrorsAndDropsNoise(): void
    {
        $policy = new SeveritySamplePolicy(ObservationSeverity::Warning->level());

        self::assertFalse($policy->sample($this->observation('debug')));
        self::assertFalse($policy->sample($this->observation('info')));
        self::assertTrue($policy->sample($this->observation('warning')));
        self::assertTrue($policy->sample($this->observation('error')));
    }

    public function testErrorOverrideWinsOverOrdinaryPolicy(): void
    {
        $policy = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());

        self::assertTrue($policy->sample($this->observation('error')));
        self::assertFalse($policy->sample($this->observation('info')));
    }

    public function testGenericNonObservationCannotUseSeveritySampling(): void
    {
        $severity = new SeveritySamplePolicy(ObservationSeverity::Warning->level());
        $override = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());

        foreach (['unknown', 'measurement', 'execution', 'occurrence', 'snapshot', 'activity'] as $type) {
            foreach ([ObservationSeverity::Debug->level(), ObservationSeverity::Error->level()] as $level) {
                $artifact = new RedactedArtifact(['level' => $level], $type);
                self::assertTrue($severity->sample($artifact), $type);
                self::assertFalse($override->sample($artifact), $type);
            }
        }
    }

    public function testHeadBasedPinsDecisionPerExecution(): void
    {
        $inner = new class () implements SamplingPolicyInterface {
            public int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->calls++;

                return $this->calls === 1;
            }
        };

        $policy = new HeadBasedSamplingPolicy($inner);

        $first = $policy->sample($this->observation('info', 'exec-1'));
        $second = $policy->sample($this->observation('info', 'exec-1'));

        self::assertTrue($first);
        self::assertTrue($second, 'The second artifact follows the first execution decision (head-based).');
        self::assertSame(1, $inner->calls, 'The wrapped policy is consulted only once per execution.');
    }

    public function testHeadBasedPinsRedactedExecutionAndItsChildrenToOneDecision(): void
    {
        $inner = new class () implements SamplingPolicyInterface {
            public int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                return ++$this->calls === 1;
            }
        };
        $policy = new HeadBasedSamplingPolicy($inner);

        self::assertTrue($policy->sample(new RedactedArtifact(['id' => 'exec-1'], 'execution')));
        self::assertTrue($policy->sample(new RedactedArtifact(['execution_id' => 'exec-1'], 'observation')));
        self::assertTrue($policy->sample(new RedactedArtifact(['id' => 'exec-1'], 'execution')));
        self::assertFalse($policy->sample(new RedactedArtifact(['id' => 'exec-2'], 'execution')));
        self::assertFalse($policy->sample(new RedactedArtifact(['execution_id' => 'exec-2'], 'snapshot')));
        self::assertSame(2, $inner->calls);
    }

    public function testDecisionCacheIsBoundedAndOldestKeyIsReDecided(): void
    {
        $inner = new class () implements SamplingPolicyInterface {
            public int $calls = 0;

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->calls++;

                return true;
            }
        };

        $policy = new HeadBasedSamplingPolicy(
            $inner,
            limits: new SecurityLimits(maxTrackedExecutions: 1),
        );

        $policy->sample($this->observation('info', 'exec-1'));
        self::assertSame(1, $inner->calls);

        // A second execution evicts the first key from the bounded cache...
        $policy->sample($this->observation('info', 'exec-2'));
        self::assertSame(2, $inner->calls);

        // ...so the first execution is re-decided instead of being retained forever.
        $policy->sample($this->observation('info', 'exec-1'));
        self::assertSame(3, $inner->calls, 'an evicted execution key must be re-decided through the wrapped policy');
    }

    /**
     * @throws Throwable
     */
    public function testSamplingPublisherDropsRejectedArtifacts(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $sink);

        $publisher->publish($this->observation('info'));
        $publisher->publish($this->observation('error'));

        self::assertCount(0, $sink->published());

        $keep = new SamplingArtifactPublisher(new AlwaysSamplePolicy(), new InMemoryArtifactPublisher());
        $keepSink = new InMemoryArtifactPublisher();
        $keep = new SamplingArtifactPublisher(new AlwaysSamplePolicy(), $keepSink);
        $keep->publish($this->observation('info'));

        self::assertCount(1, $keepSink->published());
    }

    /**
     * @throws Throwable
     */
    public function testSamplingHealthReportIsPayloadFreeAndGuardsReentry(): void
    {
        $artifact = Observation::info(
            ObservationId::fromString('secret-id'),
            ExecutionId::fromString('secret-execution'),
            'secret-message',
            attributes: AttributeCollection::fromArray(['secret' => 'secret-value']),
        );
        $health = new class ($artifact) implements InternalHealthReporterInterface {
            public ?SamplingArtifactPublisher $publisher = null;

            /** @var list<array{Throwable, ExecutionArtifactInterface}> */
            public array $reports = [];

            public function __construct(private readonly ExecutionArtifactInterface $original)
            {
            }

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                $this->reports[] = [$error, $artifact];
                if (count($this->reports) < 3) {
                    $this->publisher?->publish($this->original);
                }
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $sink, $health);
        $health->publisher = $publisher;

        $publisher->publish($artifact);
        self::assertCount(1, $health->reports);
        $publisher->publish($artifact);

        self::assertCount(2, $health->reports);
        foreach ($health->reports as [$error, $notice]) {
            self::assertInstanceOf(SamplingDropException::class, $error);
            self::assertSame('Artifact dropped by sampling policy.', $error->getMessage());
            self::assertNotSame($artifact, $notice);
            self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $notice->normalize());
        }

        self::assertSame([], $sink->published());
    }

    /**
     * @throws Throwable
     */
    public function testSamplingHealthReporterFailureIsContainedAndGuardResets(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public int $calls = 0;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
                throw new RuntimeException('health failure');
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $sink, $health);

        $publisher->publish($this->observation());
        $publisher->publish($this->observation());

        self::assertSame(2, $health->calls);
        self::assertSame([], $sink->published());
    }

    /**
     * @throws Throwable
     */
    public function testHealthAwareFactoryReportsSamplingDrops(): void
    {
        $health = new class () implements InternalHealthReporterInterface {
            public int $calls = 0;

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ++$this->calls;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $publisher = new PublisherStackFactory()->composeWithHealth($sink, null, new NeverSamplePolicy(), $health);

        $publisher->publish($this->observation());

        self::assertSame(1, $health->calls);
        self::assertSame([], $sink->published());
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesRedactedArtifactsAfterRedaction(): void
    {
        $policy = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;

                return true;
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $id = ExecutionId::fromString('exec-1');
        $changed = Observation::info(ObservationId::fromString('changed'), $id, 'keep', attributes: $attributes);
        $rejected = Observation::info(ObservationId::fromString('rejected'), $id, 'reject', attributes: $attributes);
        $unchanged = $this->observation();

        $publisher->publish($changed);
        $publisher->publish($rejected);
        $publisher->publish($unchanged);

        // The sampler sees redacted artifacts, not originals.
        self::assertCount(3, $policy->seen);
        self::assertSame(['secret' => '[redacted]'], $policy->seen[0]->normalize()['attributes']);
        self::assertSame(['secret' => '[redacted]'], $policy->seen[1]->normalize()['attributes']);
        self::assertSame([], $policy->seen[2]->normalize()['attributes']);
        self::assertSame('[redacted]', $policy->seen[2]->normalize()['message']);

        // The sink receives redacted artifacts.
        self::assertCount(3, $sink->published());
        self::assertNotSame($changed, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertSame('[redacted]', $sink->published()[0]->normalize()['message']);
        self::assertNotSame($rejected, $sink->published()[1]);
        self::assertNotSame($unchanged, $sink->published()[2]);
    }

    public function testFactoryHandlesNullDecorators(): void
    {
        $factory = new PublisherStackFactory();
        $sink = new InMemoryArtifactPublisher();
        $artifact = $this->observation();
        self::assertSame($sink, $factory->compose($sink, null, null));

        $sampling = new class () implements SamplingPolicyInterface {
            /** @var list<ExecutionArtifactInterface> */
            public array $seen = [];

            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                $this->seen[] = $artifact;

                return true;
            }
        };
        $factory->compose($sink, null, $sampling)->publish($artifact);
        self::assertCount(1, $sampling->seen);
        self::assertInstanceOf(Observation::class, $sampling->seen[0]);
        self::assertSame($artifact->normalize(), $sampling->seen[0]->normalize());
        self::assertCount(1, $sink->published());
        self::assertInstanceOf(ObservationInterface::class, $sink->published()[0]);
        self::assertSame($artifact->normalize(), $sink->published()[0]->normalize());

        $changed = Observation::info(
            ObservationId::fromString('secret'),
            ExecutionId::fromString('exec-1'),
            'message',
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive']),
        );
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $redactedSink = new InMemoryArtifactPublisher();
        $factory->compose($redactedSink, $redactor, null)->publish($changed);
        self::assertCount(1, $redactedSink->published());
        self::assertNotSame($changed, $redactedSink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $redactedSink->published()[0]->normalize()['attributes']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesObservationSeverityAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new SeveritySamplePolicy(ObservationSeverity::Warning->level());
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $id = ExecutionId::fromString('exec-1');
        $low = Observation::info(ObservationId::fromString('low'), $id, 'low', attributes: $attributes);
        $high = Observation::warning(ObservationId::fromString('high'), $id, 'high', attributes: $attributes);

        // A redacted observation carries its level in normalized data, so the
        // severity floor applies to redacted artifacts as well.
        self::assertFalse($policy->sample($redactor->redact($low)), 'Redacted info is below the warning floor.');
        self::assertTrue($policy->sample($redactor->redact($high)), 'Redacted warning meets the warning floor.');
        $publisher->publish($low);
        $publisher->publish($high);

        self::assertCount(1, $sink->published());
        self::assertSame('[redacted]', $sink->published()[0]->normalize()['message']);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesErrorOverrideAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new ErrorOverrideSamplePolicy(new NeverSamplePolicy());
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $id = ExecutionId::fromString('exec-1');
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $error = Observation::error(ObservationId::fromString('error'), $id, 'error', attributes: $attributes);
        $info = Observation::info(ObservationId::fromString('info'), $id, 'info', attributes: $attributes);
        $unchanged = $this->observation('error');
        $redacted = $redactor->redact($error);

        // Error override applies to redacted observations via normalized level.
        self::assertTrue($policy->sample($error));
        self::assertTrue($policy->sample($redacted));
        self::assertNotSame($unchanged, $redactor->redact($unchanged));
        self::assertSame('[redacted]', $redactor->redact($unchanged)->normalize()['message']);
        $publisher->publish($error);
        $publisher->publish($info);
        $publisher->publish($unchanged);

        self::assertCount(2, $sink->published());
        self::assertNotSame($error, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertNotSame($unchanged, $sink->published()[1]);
        self::assertSame('[redacted]', $sink->published()[1]->normalize()['message']);
    }

    /**
     * @throws Throwable
     */
    public function testFactorySamplesExecutionKindAfterRedaction(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
            redactObservationMessages: true,
        ));
        $policy = new ExecutionKindSamplePolicy(
            static fn (ExecutionIdInterface $id): ?ExecutionKind => match ($id->toString()) {
                'exec-1' => ExecutionKind::fromString('http.request'),
                'exec-2' => ExecutionKind::fromString('cli.command'),
                default => null,
            },
            ['http.request' => true, 'cli.command' => false],
        );
        $publisher = new PublisherStackFactory()->compose($sink, $redactor, $policy);
        $attributes = AttributeCollection::fromArray(['secret' => 'sensitive']);
        $kept = Observation::info(
            ObservationId::fromString('kept'),
            ExecutionId::fromString('exec-1'),
            'kept',
            attributes: $attributes,
        );
        $dropped = Observation::info(
            ObservationId::fromString('dropped'),
            ExecutionId::fromString('exec-2'),
            'dropped',
            attributes: $attributes,
        );
        $unknown = $this->observation('info', 'exec-3');
        $unchanged = $this->observation();

        // Execution kind resolves from redacted normalized execution_id.
        self::assertTrue($policy->sample($kept));
        self::assertTrue($policy->sample($redactor->redact($kept)));
        self::assertFalse($policy->sample($redactor->redact($dropped)));
        self::assertNotSame($unchanged, $redactor->redact($unchanged));
        self::assertSame('[redacted]', $redactor->redact($unchanged)->normalize()['message']);
        $publisher->publish($kept);
        $publisher->publish($dropped);
        $publisher->publish($unknown);
        $publisher->publish($unchanged);

        self::assertCount(2, $sink->published());
        self::assertNotSame($kept, $sink->published()[0]);
        self::assertSame(['secret' => '[redacted]'], $sink->published()[0]->normalize()['attributes']);
        self::assertNotSame($unchanged, $sink->published()[1]);
        self::assertSame('[redacted]', $sink->published()[1]->normalize()['message']);
    }

    /** Redacting an already-redacted artifact preserves the first projection's trusted artifact type. */
    public function testRedactingRedactedArtifactPreservesArtifactType(): void
    {
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret'],
        ));
        $observation = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'kept',
            attributes: AttributeCollection::fromArray(['secret' => 'sensitive']),
        );

        $first = $redactor->redact($observation);
        self::assertInstanceOf(RedactedArtifactInterface::class, $first);
        self::assertSame('observation', $first->artifactType());

        $second = $redactor->redact($first);
        self::assertInstanceOf(RedactedArtifactInterface::class, $second);
        self::assertSame($first->artifactType(), $second->artifactType());
        self::assertSame('observation', $second->artifactType());
    }
}

readonly class SecretFieldsSnapshot extends Snapshot
{
    public function normalize(): array
    {
        return parent::normalize() + ['extra_secret' => 'secret-extra-value'];
    }
}

readonly class InvalidTimingSnapshot extends Snapshot
{
    public function normalize(): array
    {
        return array_replace(parent::normalize(), ['created_at' => 'secret-timing']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Execution\ExecutionSuccess;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultArtifactRedactor::class)]
final class DefaultArtifactRedactorTest extends Unit
{
    public function testUnchangedArtifactIsReturnedAsIs(): void
    {
        $config = ArtifactRedactionConfig::disabled();
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'plain message',
            attributes: AttributeCollection::fromArray(['password' => 'secret'])
        );

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function testSensitiveAttributeKeysAreMasked(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password', 'token']
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'plain message',
            attributes: AttributeCollection::fromArray([
                'safe' => 'visible',
                'password' => 'secret',
                'token' => 'abc123',
            ])
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $attributes = $normalized['attributes'];
        self::assertIsArray($attributes);
        self::assertSame('visible', $attributes['safe']);
        self::assertSame('[redacted]', $attributes['password']);
        self::assertSame('[redacted]', $attributes['token']);
    }

    public function testObservationMessageIsMaskedByDefault(): void
    {
        $redactor = new DefaultArtifactRedactor();
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'sensitive message'
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        self::assertSame('[redacted]', $normalized['message']);
    }

    public function testObservationMessageNotMaskedWhenDisabled(): void
    {
        $config = new ArtifactRedactionConfig(redactObservationMessages: false);
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'sensitive message'
        );

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function testMeasurementValueIsMaskedWhenEnabled(): void
    {
        $config = new ArtifactRedactionConfig(redactMeasurementValues: true);
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Measurement::counter(
            MeasurementName::fromString('requests'),
            ExecutionId::fromString('exec-1'),
            42
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        self::assertSame('[redacted]', $normalized['value']);
        self::assertSame(MeasurementKind::Counter->value, $normalized['kind']);
    }

    public function testThrowableMessageIsMasked(): void
    {
        $redactor = new DefaultArtifactRedactor();
        $throwable = ThrowableDescription::create(
            RuntimeException::class,
            'secret error',
            1,
            null,
            'trace line'
        );
        $failure = ExecutionFailure::of(
            ExecutionStatus::Failed,
            $throwable
        );
        $artifact = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task')
            ),
            Instant::of(1000)
        )->beginRunning()->complete($failure, Instant::of(2000));

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        self::assertArrayHasKey('result', $normalized);
        $resultField = $normalized['result'];
        self::assertIsArray($resultField);
        self::assertArrayHasKey('throwable', $resultField);
        $throwableField = $resultField['throwable'];
        self::assertIsArray($throwableField);
        self::assertSame('[redacted]', $throwableField['message']);
        self::assertNull($throwableField['trace']);
    }

    public function testSnapshotPayloadIsMaskedAndHashOmitted(): void
    {
        $config = new ArtifactRedactionConfig(sensitiveDataKeys: ['secret']);
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('state.v1'),
            1,
            ['secret' => 'data'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha256', $content);
                }
            },
            classification: SnapshotClassification::Checkpoint,
            createdAt: Instant::of(1000),
            contentType: 'application/json'
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $payload = $normalized['payload'];
        self::assertIsArray($payload);
        self::assertSame('[redacted]', $payload['secret']);
        self::assertArrayNotHasKey('content_hash', $normalized);
    }

    public function testDisabledConfigMasksNothing(): void
    {
        $config = ArtifactRedactionConfig::disabled();
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'sensitive message',
            attributes: AttributeCollection::fromArray(['password' => 'secret'])
        );

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function testUnknownArtifactTypeIsMaskedAndReturned(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password']
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'unknown-kind';
            }

            public function normalize(): array
            {
                return [
                    'attributes' => ['password' => 'secret', 'safe' => 'visible'],
                ];
            }
        };

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        self::assertSame('unknown', $result->artifactType());
        $normalized = $result->normalize();
        $attributes = $normalized['attributes'];
        self::assertIsArray($attributes);
        self::assertSame('[redacted]', $attributes['password']);
        self::assertSame('visible', $attributes['safe']);
    }

    public function testObservationMessageNotMaskedOnNonObservation(): void
    {
        $config = new ArtifactRedactionConfig(redactObservationMessages: true);
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Execution::create(
            ExecutionId::fromString('exec-1'),
            ExecutionDefinition::create(
                ExecutionName::fromString('op'),
                ExecutionKind::fromString('task')
            ),
            Instant::of(1000)
        )->beginRunning()->complete(
            ExecutionSuccess::create(),
            Instant::of(2000)
        );

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function testMeasurementValueNotMaskedOnNonMeasurement(): void
    {
        $config = new ArtifactRedactionConfig(
            redactObservationMessages: false,
            redactMeasurementValues: true
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'message'
        );

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function testSnapshotAttributesNotMaskedOnNonSnapshot(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['secret']
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'message',
            attributes: AttributeCollection::fromArray(['secret' => 'data'])
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $attributes = $normalized['attributes'];
        self::assertIsArray($attributes);
        self::assertSame('[redacted]', $attributes['secret']);
    }

    public function testOccurrenceDataMaskedWhenEnabled(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveDataKeys: ['secret'],
            redactOccurrenceData: true
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Occurrence::create(
            OccurrenceId::fromString('occ-1'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.custom.v1'),
            'source',
            Instant::of(1000),
            ['secret' => 'data', 'safe' => 'visible'],
            null,
            null,
            null,
            'application/json',
            AttributeCollection::empty(),
            ArtifactSequence::initial()
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $data = $normalized['data'];
        self::assertIsArray($data);
        self::assertSame('[redacted]', $data['secret']);
        self::assertSame('visible', $data['safe']);
    }

    public function testSnapshotPayloadMaskedWhenEnabled(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveDataKeys: ['secret'],
            redactSnapshotPayloads: true
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('state.v1'),
            1,
            ['secret' => 'data', 'safe' => 'visible'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha256', $content);
                }
            },
            classification: SnapshotClassification::Checkpoint,
            createdAt: Instant::of(1000),
            contentType: 'application/json'
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $payload = $normalized['payload'];
        self::assertIsArray($payload);
        self::assertSame('[redacted]', $payload['secret']);
        self::assertSame('visible', $payload['safe']);
        self::assertArrayNotHasKey('content_hash', $normalized);
    }

    public function testSnapshotPayloadWithContentHashHasHashRemovedWhenMasked(): void
    {
        $config = new ArtifactRedactionConfig(
            sensitiveDataKeys: ['secret'],
            redactSnapshotPayloads: true
        );
        $redactor = $this->createArtifactRedactor($config);
        $artifact = Snapshot::create(
            SnapshotId::fromString('snap-1'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('state.v1'),
            1,
            ['secret' => 'data'],
            new class () implements HashCalculatorInterface {
                public function calculate(string $content): string
                {
                    return hash('sha256', $content);
                }
            },
            classification: SnapshotClassification::Checkpoint,
            createdAt: Instant::of(1000),
            contentType: 'application/json'
        );

        $result = $redactor->redact($artifact);

        self::assertInstanceOf(RedactedArtifactInterface::class, $result);
        $normalized = $result->normalize();
        $payload = $normalized['payload'];
        self::assertIsArray($payload);
        self::assertSame('[redacted]', $payload['secret']);
        self::assertArrayNotHasKey('content_hash', $normalized);
    }

    public function testTruncationReportsThroughHealthReporter(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $failure,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = $failure->getMessage();
        });

        $redactor = new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(
                sensitiveDataKeys: ['secret'],
                redactSnapshotPayloads: true,
            ),
            $health,
        );

        $ref = new ReflectionMethod(DefaultArtifactRedactor::class, 'reportTruncation');
        $ref->invoke($redactor);

        self::assertCount(1, $reports);
        self::assertStringContainsString('traversal limits exceeded', $reports[0]);
    }

    public function testTruncationIsDeduplicated(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $failure,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = $failure->getMessage();
        });

        $redactor = new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(),
            $health,
        );

        $ref = new ReflectionMethod(DefaultArtifactRedactor::class, 'reportTruncation');
        $ref->invoke($redactor);
        $ref->invoke($redactor);
        $ref->invoke($redactor);

        self::assertCount(1, $reports);
    }

    public function testTruncationIsNoopWhenAlreadyPublishing(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(static function (
            Throwable $failure,
            ExecutionArtifactInterface $artifact,
        ) use (&$reports): void {
            $reports[] = $failure->getMessage();
        });

        $redactor = new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(),
            $health,
        );

        $ref = new ReflectionMethod(DefaultArtifactRedactor::class, 'reportTruncation');
        $publishingRef = new ReflectionProperty(DefaultArtifactRedactor::class, 'publishingHealthReport');
        $publishingRef->setValue($redactor, true);

        $ref->invoke($redactor);

        self::assertCount(0, $reports);
    }

    public function testThrowableClassChangedIsDetected(): void
    {
        $redactor = new DefaultArtifactRedactor();
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1000)
        )->beginRunning()->complete(
            ExecutionFailure::of(
                ExecutionStatus::Failed,
                ThrowableDescription::create('OriginalClass', 'msg', 1, null, null)
            ),
            Instant::of(2000)
        );

        $result = $redactor->redact($execution);

        self::assertInstanceOf(RedactedArtifact::class, $result);
        /** @var array{result: array{throwable: array{class: string|int|null}}} $normalized */
        $normalized = $result->normalize();
        self::assertSame('OriginalClass', $normalized['result']['throwable']['class'] ?? null);
    }

    public function testThrowableCodeChangedIsDetected(): void
    {
        $redactor = new DefaultArtifactRedactor();
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
        $execution = Execution::create(
            ExecutionId::fromString('exec-1'),
            $definition,
            Instant::of(1000)
        )->beginRunning()->complete(
            ExecutionFailure::of(
                ExecutionStatus::Failed,
                ThrowableDescription::create('X', 'msg', 1, null, null)
            ),
            Instant::of(2000)
        );

        $result = $redactor->redact($execution);

        self::assertInstanceOf(RedactedArtifact::class, $result);
        /** @var array{result: array{throwable: array{code: string|int|null}}} $normalized */
        $normalized = $result->normalize();
        self::assertSame(1, $normalized['result']['throwable']['code'] ?? null);
    }

    public function testThrowableMessageChangedIsDetected(): void
    {
        $redactor = new DefaultArtifactRedactor();
        $artifact = new class () implements ExecutionArtifactInterface {
            public function artifactType(): string
            {
                return 'execution';
            }

            public function normalize(): array
            {
                return [
                    'result' => [
                        'throwable' => [
                            'class' => 'X',
                            'message' => '[redacted]',
                            'code' => 1,
                            'previous' => null,
                            'trace' => null,
                        ],
                    ],
                ];
            }
        };

        $result = $redactor->redact($artifact);

        self::assertSame($artifact, $result);
    }

    public function createArtifactRedactor(ArtifactRedactionConfig $config): DefaultArtifactRedactor
    {
        return new DefaultArtifactRedactor($config);
    }
}

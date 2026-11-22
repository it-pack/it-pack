<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
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
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DefaultArtifactRedactor::class)]
final class DefaultArtifactRedactorTest extends Unit
{
    public function testUnchangedArtifactIsReturnedAsIs(): void
    {
        $config = ArtifactRedactionConfig::disabled();
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
        $redactor = new DefaultArtifactRedactor($config);
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
}

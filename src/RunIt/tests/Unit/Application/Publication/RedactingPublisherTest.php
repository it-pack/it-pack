<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\Fixture\SensitiveExceptionChain;

/**
 * @group application
 * @group publication
 */
#[CoversClass(DefaultArtifactRedactor::class)]
#[CoversClass(RedactingArtifactPublisher::class)]
#[CoversClass(Observation::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(ExecutionSnapshot::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(ArtifactRedactionConfig::class)]
#[UsesClass(RedactedArtifact::class)]
#[UsesClass(Execution::class)]
#[UsesClass(ExecutionDefinition::class)]
#[UsesClass(ExecutionFailure::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(ExecutionKind::class)]
#[UsesClass(ExecutionName::class)]
#[UsesClass(ObservationId::class)]
#[UsesClass(OccurrenceId::class)]
#[UsesClass(OccurrenceType::class)]
#[UsesClass(AttributeCollection::class)]
#[UsesClass(DefaultThrowableMapper::class)]
#[UsesClass(DeterministicHashCalculator::class)]
#[UsesClass(Instant::class)]
#[UsesClass(SnapshotId::class)]
#[UsesClass(SnapshotType::class)]
final class RedactingPublisherTest extends Unit
{
    private function redactor(): DefaultArtifactRedactor
    {
        return new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: ['password', 'token'],
            sensitiveDataKeys: ['password', 'ssn'],
            marker: '[redacted]',
            redactThrowableMessages: true,
            redactOccurrenceData: true,
            redactSnapshotPayloads: true,
        ));
    }

    private function publish(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor(), $sink);
        $publisher->publish($artifact);

        return $sink->published()[0];
    }

    public function testSensitiveAttributesAreMasked(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'login',
            attributes: AttributeCollection::fromArray(['user' => 'alice', 'password' => 'hunter2']),
        );

        /** @var array<string, mixed> $redacted */
        $redacted = $this->publish($original)->normalize();
        /** @var array<string, mixed> $attrs */
        $attrs = $redacted['attributes'];
        /** @var array<string, mixed> $originalAttrs */
        $originalAttrs = $original->normalize()['attributes'];

        self::assertSame('alice', $attrs['user']);
        self::assertSame('[redacted]', $attrs['password']);
        self::assertSame('hunter2', $originalAttrs['password'], 'The original artifact is unchanged.');
    }

    public function testThrowableMessageIsMasked(): void
    {
        $throwable = new DefaultThrowableMapper()->describe(new RuntimeException('token=abc123'));
        $original = Observation::error(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'failed',
            throwableDescription: $throwable,
        );

        /** @var array<string, mixed> $redacted */
        $redacted = $this->publish($original)->normalize();
        /** @var array<string, mixed> $throwableData */
        $throwableData = $redacted['throwable'];
        /** @var array<string, mixed> $originalThrowable */
        $originalThrowable = $original->normalize()['throwable'];

        self::assertSame('[redacted]', $throwableData['message']);
        self::assertSame('token=abc123', $originalThrowable['message']);
    }

    public function testNestedThrowableSecretsAreRedacted(): void
    {
        $original = Observation::error(
            ObservationId::fromString('obs-nested'),
            ExecutionId::fromString('exec-1'),
            'failed',
            throwableDescription: SensitiveExceptionChain::classifiedChain(),
        );

        $encoded = json_encode($this->publish($original)->normalize(), JSON_THROW_ON_ERROR);
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString(
                $secret,
                $encoded,
                'no classified secret may reach the published output',
            );
        }
    }

    public function testFailedExecutionThrowableCurrentlyBypassesRedaction(): void
    {
        $original = Execution::start(
            ExecutionId::fromString('exec-failed'),
            ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );
        $before = $original->normalize();
        $sink = new InMemoryArtifactPublisher();
        new RedactingArtifactPublisher(new DefaultArtifactRedactor(), $sink)->publish($original);
        $published = $sink->published()[0]->normalize();

        // Characterizes a known leak, not the required redaction policy.
        self::assertIsArray($before['result']);
        self::assertIsArray($published['result']);
        self::assertSame($before['result']['throwable'], $published['result']['throwable']);
        $encoded = json_encode($published, JSON_THROW_ON_ERROR);
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringContainsString($secret, $encoded);
        }

        self::assertSame($before, $original->normalize());
    }

    public function testObservationThrowableMaskingPreservesMetadataAtEveryDepth(): void
    {
        $original = Observation::error(
            ObservationId::fromString('obs-metadata'),
            ExecutionId::fromString('exec-1'),
            'failed',
            throwableDescription: SensitiveExceptionChain::classifiedChain(),
        );
        $before = $original->normalize();
        $published = $this->publish($original)->normalize();
        $throwable = $published['throwable'];

        foreach ([['RuntimeException', 2], ['PDOException', 1], ['RuntimeException', 0]] as [$class, $code]) {
            self::assertIsArray($throwable);
            self::assertSame($class, $throwable['class']);
            self::assertSame($code, $throwable['code']);
            self::assertSame('[redacted]', $throwable['message']);
            self::assertSame('[redacted]', $throwable['trace']);
            $throwable = $throwable['previous'];
        }

        self::assertNull($throwable);
        self::assertSame($before, $original->normalize());
    }

    public function testThrowableMaskingCanBeExplicitlyDisabled(): void
    {
        $original = Observation::error(
            ObservationId::fromString('obs-opt-out'),
            ExecutionId::fromString('exec-1'),
            'failed',
            throwableDescription: SensitiveExceptionChain::classifiedChain(),
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(redactThrowableMessages: false)),
            $sink,
        );
        $publisher->publish($original);

        self::assertSame($original, $sink->published()[0]);
        self::assertSame($original->normalize()['throwable'], $sink->published()[0]->normalize()['throwable']);
    }

    public function testOccurrenceDataIsMaskedRecursively(): void
    {
        $original = Occurrence::create(
            OccurrenceId::fromString('occ'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['user' => 'alice', 'nested' => ['ssn' => '123-45-6789']],
        );

        /** @var array<string, mixed> $redacted */
        $redacted = $this->publish($original)->normalize();
        /** @var array<string, mixed> $data */
        $data = $redacted['data'];
        /** @var array<string, mixed> $nested */
        $nested = $data['nested'];
        /** @var array<string, mixed> $originalData */
        $originalData = $original->normalize()['data'];
        /** @var array<string, mixed> $originalNested */
        $originalNested = $originalData['nested'];

        self::assertSame('alice', $data['user']);
        self::assertSame('[redacted]', $nested['ssn']);
        self::assertSame('123-45-6789', $originalNested['ssn']);
    }

    public function testOccurrenceExtensionAttributesAreNotCoveredByConfiguredKeys(): void
    {
        $original = Occurrence::create(
            OccurrenceId::fromString('occ-extensions'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['items' => [['password' => 'data-secret']]],
            extensionAttributes: AttributeCollection::fromArray(['password' => 'extension-secret']),
        );

        $published = $this->publish($original)->normalize();
        self::assertSame(['items' => [['password' => '[redacted]']]], $published['data']);
        self::assertSame(['password' => 'extension-secret'], $published['extension_attributes']);
    }

    public function testUnsupportedCustomProjectionIsNotValidatedOrTraversed(): void
    {
        $object = (object) ['password' => 'object-secret'];
        // Deliberately violates NormalizableInterface to characterize the boundary.
        $artifact = new readonly class ($object) implements ExecutionArtifactInterface {
            public function __construct(private object $value)
            {
            }

            public function normalize(): array
            {
                return [
                    'attributes' => ['password' => 'attribute-secret'],
                    'unknown' => ['password' => 'unknown-secret'],
                    'data' => ['password' => 'data-secret'],
                    'object' => $this->value,
                ];
            }
        };

        $published = $this->publish($artifact)->normalize();
        self::assertSame(['password' => '[redacted]'], $published['attributes']);
        self::assertSame(['password' => 'unknown-secret'], $published['unknown']);
        self::assertSame(['password' => 'data-secret'], $published['data']);
        self::assertSame($object, $published['object']);
    }

    public function testArrayValuedThrowableTracePassesThroughInCustomProjection(): void
    {
        $artifact = new readonly class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [
                    'throwable' => [
                        'message' => 'message-secret',
                        'trace' => [['password' => 'trace-secret']],
                        'previous' => null,
                    ],
                ];
            }
        };

        $before = $artifact->normalize();
        $published = $this->publish($artifact)->normalize();

        // The projection is primitive and finite, but its trace is not a string.
        self::assertIsArray($published['throwable']);
        self::assertSame('[redacted]', $published['throwable']['message']);
        self::assertSame([['password' => 'trace-secret']], $published['throwable']['trace']);
        self::assertSame($before, $artifact->normalize());
    }

    public function testSnapshotPayloadIsMasked(): void
    {
        $original = ExecutionSnapshot::create(
            SnapshotId::fromString('snap'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('test.snapshot'),
            1,
            ['state' => 'ok', 'password' => 'hunter2'],
            new DeterministicHashCalculator(),
        );

        /** @var array<string, mixed> $redacted */
        $redacted = $this->publish($original)->normalize();
        /** @var array<string, mixed> $payload */
        $payload = $redacted['payload'];

        self::assertSame('ok', $payload['state']);
        self::assertSame('[redacted]', $payload['password']);
    }

    public function testFailedExecutionSnapshotPayloadMaskingAndOptOut(): void
    {
        $execution = Execution::start(
            ExecutionId::fromString('snapshot-exec'),
            ExecutionDefinition::create(ExecutionName::fromString('import'), ExecutionKind::fromString('batch')),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );
        $snapshot = ExecutionSnapshot::create(
            SnapshotId::fromString('snapshot-failed'),
            ExecutionId::fromString('snapshot-exec'),
            SnapshotType::fromString('execution.terminal'),
            1,
            $execution->normalize(),
            new DeterministicHashCalculator(),
        );
        $before = $snapshot->normalize();
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['message', 'trace'],
        ));
        $published = $redactor->redact($snapshot)->normalize();
        $encoded = json_encode($published['payload'], JSON_THROW_ON_ERROR);

        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString($secret, $encoded);
        }

        self::assertIsArray($published['payload']);
        self::assertIsArray($published['payload']['result']);
        $throwable = $published['payload']['result']['throwable'];
        foreach ([['RuntimeException', 2], ['PDOException', 1], ['RuntimeException', 0]] as [$class, $code]) {
            self::assertIsArray($throwable);
            self::assertSame($class, $throwable['class']);
            self::assertSame($code, $throwable['code']);
            self::assertSame('[redacted]', $throwable['message']);
            self::assertSame('[redacted]', $throwable['trace']);
            $throwable = $throwable['previous'];
        }

        self::assertNull($throwable);
        self::assertSame($before['content_hash'], $published['content_hash']);
        self::assertSame($before, $snapshot->normalize());
        $optOut = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['message', 'trace'],
            redactSnapshotPayloads: false,
        ));
        self::assertSame($snapshot, $optOut->redact($snapshot));
        self::assertSame($before, $optOut->redact($snapshot)->normalize());
    }

    public function testRedactorAndNormalizeShareFieldVocabulary(): void
    {
        // Regression for S10: the redactor reaches into normalized keys that must
        // match the keys emitted by normalize(). Both reference the shared
        // ArtifactField vocabulary, so renaming a field cannot silently desync
        // redaction from the normalized projection.
        $observation = Observation::info(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'm',
            attributes: AttributeCollection::fromArray(['a' => 'b']),
        );
        $obsNormalized = $observation->normalize();
        self::assertArrayHasKey(ArtifactField::ID, $obsNormalized);
        self::assertArrayHasKey(ArtifactField::ATTRIBUTES, $obsNormalized);
        self::assertArrayHasKey(ArtifactField::THROWABLE, $obsNormalized);

        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
        );
        $occNormalized = $occurrence->normalize();
        self::assertArrayHasKey(ArtifactField::ID, $occNormalized);
        self::assertArrayHasKey(ArtifactField::DATA, $occNormalized);

        $snapshot = ExecutionSnapshot::create(
            SnapshotId::fromString('snap'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('test.snapshot'),
            1,
            ['state' => 'ok'],
            new DeterministicHashCalculator(),
        );
        $snapNormalized = $snapshot->normalize();
        self::assertArrayHasKey(ArtifactField::ID, $snapNormalized);
        self::assertArrayHasKey(ArtifactField::PAYLOAD, $snapNormalized);
        self::assertArrayHasKey(ArtifactField::ATTRIBUTES, $snapNormalized);
    }

    public function testNothingRedactedWhenNoMatch(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs'),
            ExecutionId::fromString('exec-1'),
            'hello',
            attributes: AttributeCollection::fromArray(['user' => 'alice']),
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(ArtifactRedactionConfig::disabled()),
            $sink
        );
        $publisher->publish($original);

        self::assertSame($original, $sink->published()[0], 'With no redaction the same instance is published.');
    }
}

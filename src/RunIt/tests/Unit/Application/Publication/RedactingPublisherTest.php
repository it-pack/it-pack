<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\BoundDropException;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Domain\Execution\Execution;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionFailure;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Execution\ExecutionStatus;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
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
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\Fixture\SensitiveExceptionChain;
use Throwable;

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
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(BoundDropException::class)]
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

    public function testFailedExecutionResultThrowableIsMasked(): void
    {
        $original = Execution::start(
            ExecutionId::fromString('exec-failed'),
            ExecutionDefinition::create(
                ExecutionName::fromString('import'),
                ExecutionKind::fromString('batch')
            ),
            Instant::of(1000),
        )->complete(
            ExecutionFailure::of(ExecutionStatus::Failed, SensitiveExceptionChain::classifiedChain()),
            Instant::of(1001),
        );
        $before = $original->normalize();
        $sink = new InMemoryArtifactPublisher();
        new RedactingArtifactPublisher(new DefaultArtifactRedactor(), $sink)->publish($original);
        $published = $sink->published()[0]->normalize();

        /** @var array<string, mixed> $result */
        $result = $published['result'];
        /** @var array<string, mixed> $throwable */
        $throwable = $result['throwable'];
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString($secret, json_encode($throwable, JSON_THROW_ON_ERROR));
        }

        self::assertSame('[redacted]', $throwable['message']);
        self::assertNull($throwable['trace']);

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
            self::assertNull($throwable['trace']);
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
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                redactThrowableMessages: false,
                redactObservationMessages: false,
            )),
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

    public function testNestedClassDataIsNotMistakenForThrowable(): void
    {
        $original = Occurrence::create(
            OccurrenceId::fromString('occ-class'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['item' => ['class' => 'Order', 'name' => 'book', 'password' => 'secret'],
                'throwable' => ['class' => 'RuntimeException', 'message' => 'secret']],
        );

        $data = $this->publish($original)->normalize()['data'];
        self::assertIsArray($data);
        self::assertSame(['class' => 'Order', 'name' => 'book', 'password' => '[redacted]'], $data['item']);
        self::assertSame(['class' => 'RuntimeException', 'message' => '[redacted]'], $data['throwable']);
    }

    public function testRecursiveNodeBudgetAccumulatesAndSignalsTruncation(): void
    {
        $payload = ['first' => array_fill(0, 600, 'safe'), 'second' => array_fill(0, 600, 'safe'),
            'password' => 'secret', 'later' => 'another-secret'];
        $original = self::createStub(SnapshotInterface::class);
        $original->method('normalize')->willReturn(['payload' => $payload]);

        $data = $this->publish($original)->normalize()['payload'];
        self::assertIsArray($data);
        self::assertIsArray($data['first']);
        self::assertIsArray($data['second']);
        self::assertCount(600, $data['first']);
        self::assertLessThan(600, count($data['second']));
        self::assertSame('[redacted]', end($data['second']));
        self::assertSame('[redacted]', $data['password']);
        self::assertArrayNotHasKey('later', $data);
        self::assertStringNotContainsString('secret', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertSame($payload, $original->normalize()['payload']);
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
        self::assertSame(['password' => '[redacted]'], $published['extension_attributes']);
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

    public function testArrayValuedThrowableTraceIsOmittedInCustomProjection(): void
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

        self::assertIsArray($published['throwable']);
        self::assertSame('[redacted]', $published['throwable']['message']);
        self::assertNull($published['throwable']['trace']);
        self::assertSame($before, $artifact->normalize());
    }

    public function testCustomThrowableUnknownFieldsAndNonArrayCauseAreOmitted(): void
    {
        $artifact = new readonly class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [
                    'throwable' => [
                        'class' => 'Outer', 'code' => 42, 'message' => 'outer-secret', 'trace' => 'trace-secret',
                        'detail' => ['message' => 'unknown-secret'],
                        'previous' => [
                            'class' => 'Inner', 'code' => 7, 'message' => 'inner-secret',
                            'trace' => null, 'previous' => 'unsupported-cause-secret',
                        ],
                    ],
                ];
            }
        };
        $before = $artifact->normalize();
        $published = $this->publish($artifact)->normalize();
        $throwable = $published['throwable'];

        self::assertIsArray($throwable);
        self::assertSame('Outer', $throwable['class']);
        self::assertSame(42, $throwable['code']);
        self::assertSame('[redacted]', $throwable['message']);
        self::assertNull($throwable['trace']);
        self::assertArrayNotHasKey('detail', $throwable);
        self::assertIsArray($throwable['previous']);
        self::assertSame('Inner', $throwable['previous']['class']);
        self::assertSame(7, $throwable['previous']['code']);
        self::assertSame('[redacted]', $throwable['previous']['message']);
        self::assertNull($throwable['previous']['trace']);
        self::assertNull($throwable['previous']['previous']);
        self::assertSame($before, $artifact->normalize());
        $disabled = new DefaultArtifactRedactor(ArtifactRedactionConfig::disabled());
        self::assertSame($artifact, $disabled->redact($artifact));
        self::assertSame($before, $disabled->redact($artifact)->normalize());
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
            ExecutionDefinition::create(
                ExecutionName::fromString('import'),
                ExecutionKind::fromString('batch')
            ),
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
        // Default redaction masks throwables inside snapshot payloads even without sensitiveDataKeys.
        $default = new DefaultArtifactRedactor()->redact($snapshot);
        self::assertNotSame($snapshot, $default);
        $defaultPayload = json_encode($default->normalize()['payload'], JSON_THROW_ON_ERROR);
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString(
                $secret,
                $defaultPayload,
                'Default redaction must mask throwable secrets inside snapshot payloads.'
            );
        }

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
            self::assertNull($throwable['trace']);
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

    public function testCyclicThrowableCauseIsBoundedAndUnknownFieldsAreOmitted(): void
    {
        $artifact = new readonly class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                $cause = ['class' => 'RuntimeException', 'message' => 'secret', 'detail' => 'secret'];
                $cause['previous'] = &$cause;

                return ['throwable' => $cause];
            }
        };

        $throwable = $this->publish($artifact)->normalize()['throwable'];
        $depth = 0;
        while (is_array($throwable)) {
            self::assertSame('[redacted]', $throwable['message']);
            self::assertArrayNotHasKey('detail', $throwable);
            $throwable = $throwable['previous'];
            ++$depth;
        }

        self::assertSame(16, $depth);
    }

    public function testCyclicSnapshotDataTraversalIsBoundedWithoutMutation(): void
    {
        $payload = ['password' => 'secret'];
        $payload['next'] = &$payload;
        $snapshot = new readonly class (['payload' => $payload]) implements SnapshotInterface {
            /** @param array<array-key, mixed> $normalized */
            public function __construct(
                private array $normalized,
            ) {
            }

            public function id(): SnapshotIdInterface
            {
                return SnapshotId::fromString('snapshot-cyclic');
            }

            public function type(): SnapshotTypeInterface
            {
                return SnapshotType::fromString('snapshot.cyclic');
            }

            public function classification(): SnapshotClassificationInterface
            {
                return SnapshotClassification::Checkpoint;
            }

            public function schemaVersion(): int
            {
                return 1;
            }

            public function payload(): mixed
            {
                return $this->normalized;
            }

            public function contentType(): string
            {
                return 'application/json';
            }

            public function contentHash(): string
            {
                return 'hash';
            }

            public function predecessorId(): ?SnapshotIdInterface
            {
                return null;
            }

            public function normalize(): array
            {
                return ['payload' => $this->normalized];
            }

            public function executionId(): ExecutionIdInterface
            {
                return ExecutionId::fromString('exec-cyclic');
            }

            public function activityId(): ?ActivityIdInterface
            {
                return null;
            }

            public function timestamp(): int
            {
                return 1000;
            }

            public function count(): int
            {
                return 0;
            }

            public function attributes(): array
            {
                return [];
            }
        };

        /** @var array<string, mixed> $normalized */
        $normalized = $this->publish($snapshot)->normalize();
        /** @var array<string, mixed> $published */
        $published = $normalized['payload'];
        /** @var array<string, mixed> $result */
        $result = $published['payload'];
        $depth = 0;
        while (is_array($result) && $depth < 16) {
            self::assertArrayHasKey('password', $result);
            self::assertSame('[redacted]', $result['password']);
            $result = $result['next'];
            ++$depth;
        }

        self::assertSame('[redacted]', $result);
        self::assertSame('secret', $payload['password']);
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

    public function testObservationMessageIsMasked(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs-message'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 secret message',
            attributes: AttributeCollection::fromArray(['user' => 'alice']),
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                redactObservationMessages: true,
            )),
            $sink,
        );
        $publisher->publish($original);

        /** @var array<string, mixed> $published */
        $published = $sink->published()[0]->normalize();

        self::assertSame('[redacted]', $published['message']);
        /** @var array<string, mixed> $attrs */
        $attrs = $published['attributes'];
        self::assertSame('alice', $attrs['user']);
        self::assertSame('token=abc123 secret message', $original->normalize()['message']);
    }

    public function testObservationMessageMaskingCanBeOptedOut(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs-optout'),
            ExecutionId::fromString('exec-1'),
            'visible message',
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                redactObservationMessages: false,
            )),
            $sink,
        );
        $publisher->publish($original);

        self::assertSame('visible message', $sink->published()[0]->normalize()['message']);
        self::assertSame($original, $sink->published()[0]);
    }

    public function testMeasurementValueIsMasked(): void
    {
        $original = Measurement::counter(
            MeasurementName::fromString('requests.total'),
            ExecutionId::fromString('exec-1'),
            42,
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                sensitiveAttributeKeys: ['password'],
                redactMeasurementValues: true,
            )),
            $sink,
        );
        $publisher->publish($original);

        /** @var array<string, mixed> $published */
        $published = $sink->published()[0]->normalize();

        self::assertSame('[redacted]', $published['value']);
        /** @var array<string, mixed> $attrs */
        $attrs = $published['attributes'];
        self::assertSame('[redacted]', $attrs['password']);
        self::assertSame(42, $original->normalize()['value']);
    }

    public function testMeasurementValueMaskingCanBeOptedOut(): void
    {
        $original = Measurement::counter(
            MeasurementName::fromString('requests.total'),
            ExecutionId::fromString('exec-1'),
            42,
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                redactMeasurementValues: false,
            )),
            $sink,
        );
        $publisher->publish($original);

        self::assertSame(42, $sink->published()[0]->normalize()['value']);
    }

    public function testOccurrenceExtensionAttributesAreMasked(): void
    {
        $original = Occurrence::create(
            OccurrenceId::fromString('occ-ext'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['items' => [['ok' => true]]],
            extensionAttributes: AttributeCollection::fromArray(['password' => 'extension-secret', 'user' => 'alice']),
        );

        /** @var array<string, mixed> $published */
        $published = $this->publish($original)->normalize();

        /** @var array<string, mixed> $extAttrs */
        $extAttrs = $published['extension_attributes'];
        self::assertSame('[redacted]', $extAttrs['password']);
        self::assertSame('alice', $extAttrs['user']);
        /** @var array<string, mixed> $originalExt */
        $originalExt = $original->normalize()['extension_attributes'];
        self::assertSame(['password' => 'extension-secret', 'user' => 'alice'], $originalExt);
    }

    public function testSnapshotAttributesAreMasked(): void
    {
        $original = ExecutionSnapshot::create(
            SnapshotId::fromString('snap-attrs'),
            ExecutionId::fromString('exec-1'),
            SnapshotType::fromString('test.snapshot'),
            1,
            ['state' => 'ok'],
            new DeterministicHashCalculator(),
            attributes: AttributeCollection::fromArray(['password' => 'snapshot-secret', 'env' => 'prod']),
        );

        /** @var array<string, mixed> $published */
        $published = $this->publish($original)->normalize();

        /** @var array<string, mixed> $attrs */
        $attrs = $published['attributes'];
        self::assertSame('[redacted]', $attrs['password']);
        self::assertSame('prod', $attrs['env']);
        /** @var array<string, mixed> $originalAttrs */
        $originalAttrs = $original->normalize()['attributes'];
        self::assertSame('snapshot-secret', $originalAttrs['password']);
        self::assertSame('prod', $originalAttrs['env']);
    }

    public function testDropRedactedObservationRequiresAHealthReporter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dropRedactedObservations requires a health reporter.');

        new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                sensitiveAttributeKeys: ['password'],
                redactObservationMessages: true,
            )),
            new InMemoryArtifactPublisher(),
            dropRedactedObservations: true,
        );
    }

    public function testRedactedObservationIsDroppedAndReportedWhenDropRedactedObservationsIsEnabled(): void
    {
        /** @var list<array{0: Throwable, 1: ExecutionArtifactInterface}> $reports */
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$reports): void {
                $reports[] = [$error, $artifact];
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                sensitiveAttributeKeys: ['password'],
                redactObservationMessages: true,
            )),
            $sink,
            $health,
            dropRedactedObservations: true,
        );

        $original = Observation::info(
            ObservationId::fromString('obs-drop'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );
        $before = $original->normalize();

        $publisher->publish($original);

        self::assertCount(0, $sink->published(), 'A redacted observation must not reach the sink.');
        self::assertCount(1, $reports);
        self::assertInstanceOf(BoundDropException::class, $reports[0][0]);
        $expectedMessage = 'Artifact dropped by data-protection bound "drop-redacted-observation".';
        self::assertSame($expectedMessage, $reports[0][0]->getMessage());
        $reportedArtifact = $reports[0][1];
        self::assertInstanceOf(RedactedArtifact::class, $reportedArtifact);
        $reportedNormalized = $reportedArtifact->normalize();
        // The health notice carries only a bounded identity projection, not the full
        // redacted payload, so no attribute secrets can leak through the health channel.
        self::assertSame('obs-drop', $reportedNormalized['id']);
        self::assertSame('runit.artifact.redacted', $reportedNormalized['type']);
        self::assertArrayNotHasKey('message', $reportedNormalized);
        self::assertArrayNotHasKey('attributes', $reportedNormalized);
        self::assertSame($before, $original->normalize(), 'The original observation is unchanged.');
    }

    public function testRedactedObservationIsPublishedWhenDropRedactedObservationsIsDisabled(): void
    {
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                sensitiveAttributeKeys: ['password'],
                redactObservationMessages: true,
            )),
            $sink,
            dropRedactedObservations: false,
        );

        $original = Observation::info(
            ObservationId::fromString('obs-publish'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );

        $publisher->publish($original);

        $published = $sink->published();
        self::assertCount(1, $published, 'A redacted observation is published when dropping is disabled.');
        self::assertInstanceOf(RedactedArtifact::class, $published[0]);
        $normalized = $published[0]->normalize();
        $attributes = $normalized['attributes'] ?? null;
        self::assertIsArray($attributes);
        self::assertSame('[redacted]', $attributes['password']);
        self::assertSame('[redacted]', $normalized['message']);
    }

    public function testOnlyObservationRedactedArtifactsAreDroppedWhenDropRedactedObservationsIsEnabled(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error) use (&$reports): void {
                $reports[] = $error;
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveDataKeys: ['password'])),
            $sink,
            $health,
            dropRedactedObservations: true,
        );

        $occurrence = Occurrence::create(
            OccurrenceId::fromString('occ-drop'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['password' => 'secret'],
        );

        $publisher->publish($occurrence);

        self::assertCount(1, $sink->published(), 'Redacted non-observation artifacts are still published.');
        self::assertCount(0, $reports, 'No health report for non-observation drops.');
        $published = $sink->published()[0];
        self::assertInstanceOf(RedactedArtifactInterface::class, $published);
        $normalized = $published->normalize();
        $data = $normalized['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame('[redacted]', $data['password'] ?? null, 'The occurrence data value must be masked.');
    }

    public function testRedactedObservationIsPublishedWhenNothingWasRedactedEvenWithDropEnabled(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error) use (&$reports): void {
                $reports[] = $error;
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            new DefaultArtifactRedactor(new ArtifactRedactionConfig(
                redactObservationMessages: false,
            )),
            $sink,
            $health,
            dropRedactedObservations: true,
        );

        $original = Observation::info(
            ObservationId::fromString('obs-clean'),
            ExecutionId::fromString('exec-1'),
            'hello',
        );

        $publisher->publish($original);

        self::assertCount(1, $sink->published(), 'An observation that needed no redaction is still published.');
        self::assertCount(0, $reports, 'No health report when nothing was redacted.');
        self::assertSame($original, $sink->published()[0], 'The unmodified instance is passed through.');
    }
}

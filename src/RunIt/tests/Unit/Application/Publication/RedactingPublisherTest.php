<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
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
use RuntimeException;
use Tests\Support\Fixture\SensitiveExceptionChain;

/**
 * @group application
 * @group publication
 */
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
            null,
            null,
            AttributeCollection::fromArray(['user' => 'alice', 'password' => 'hunter2']),
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
            null,
            null,
            null,
            $throwable,
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

    public function testCurrentRedactionLeavesNestedThrowableSecretsExposed(): void
    {
        $original = Observation::error(
            ObservationId::fromString('obs-nested'),
            ExecutionId::fromString('exec-1'),
            'failed',
            null,
            null,
            null,
            SensitiveExceptionChain::classifiedChain(),
        );

        $encoded = json_encode($this->publish($original)->normalize(), JSON_THROW_ON_ERROR);
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringContainsString($secret, $encoded);
        }
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
            null,
            null,
            AttributeCollection::fromArray(['a' => 'b']),
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
            null,
            null,
            AttributeCollection::fromArray(['user' => 'alice']),
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

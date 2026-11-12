<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\RedactingArtifactPublisher;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\SamplingPolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\Support\Fixture\SensitiveExceptionChain;
use Throwable;

/**
 * Characterizes the current publication security boundary.
 *
 * These tests document the *current* deny-list redaction behavior: only configured
 * keys and the toggled throwable/message/value fields are masked. Caller-supplied
 * identifiers, relationships, arbitrary metadata and unclassified attributes pass
 * through to samplers and sinks unchanged. No delivery policy is changed here;
 * the assertions capture the gap that the Phase 2 decision gate must resolve.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
#[CoversClass(DefaultArtifactRedactor::class)]
#[CoversClass(RedactingArtifactPublisher::class)]
#[CoversClass(SamplingArtifactPublisher::class)]
#[UsesClass(ArtifactRedactionConfig::class)]
#[UsesClass(CallbackHealthReporter::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
final class RedactionSecurityCharacterizationTest extends Unit
{
    /** @param list<string>|null $sensitiveAttributeKeys */
    private function redactor(
        ?array $sensitiveAttributeKeys = null,
        bool $redactObservationMessages = true,
    ): DefaultArtifactRedactor {
        return new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveAttributeKeys: $sensitiveAttributeKeys ?? [],
            redactThrowableMessages: true,
            redactObservationMessages: $redactObservationMessages,
            redactMeasurementValues: false,
        ));
    }

    public function testUnclassifiedAttributeKeyPassesThroughUnmaskedToSink(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs-unclassified'),
            ExecutionId::fromString('exec-1'),
            'hello',
            attributes: AttributeCollection::fromArray([
                'user' => 'alice',
                'api_key' => 'sk-live-secret',
                'password' => 'hunter2',
            ]),
        );
        $before = $original->normalize();

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink);
        $publisher->publish($original);

        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        self::assertIsArray($normalized['attributes']);
        // 'api_key' is not a configured sensitive key, so it is not classified
        // and reaches the sink unchanged.
        self::assertSame('sk-live-secret', $normalized['attributes']['api_key']);
        self::assertSame('alice', $normalized['attributes']['user']);
        self::assertSame($before, $original->normalize(), 'The original artifact is unchanged.');
    }

    public function testCallerSuppliedIdentifiersPassThroughUnclassified(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs-ids'),
            ExecutionId::fromString('exec-1'),
            'hello',
            attributes: AttributeCollection::fromArray([
                'user' => 'alice',
            ]),
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink);
        $publisher->publish($original);

        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        // Identity and relationship fields are caller-supplied strings; the
        // redactor does not classify them, so they reach the sink unredacted.
        self::assertSame('exec-1', $normalized[ArtifactField::EXECUTION_ID]);
        self::assertArrayNotHasKey(ArtifactField::PARENT_EXECUTION_ID, $normalized);
    }

    public function testUnclassifiedOccurrenceDataPassesThroughUnmasked(): void
    {
        $original = Occurrence::create(
            OccurrenceId::fromString('occ-unclassified'),
            ExecutionId::fromString('exec-1'),
            OccurrenceType::fromString('itpack.test.event.v1'),
            'source',
            Instant::of(1000),
            ['credit_card' => '4111-1111-1111-1111', 'user' => 'alice'],
        );
        $before = $original->normalize();

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink);
        $publisher->publish($original);

        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        self::assertIsArray($normalized['data']);
        self::assertSame('4111-1111-1111-1111', $normalized['data']['credit_card']);
        self::assertSame('alice', $normalized['data']['user']);
        self::assertSame($before, $original->normalize(), 'The original occurrence is unchanged.');
    }

    public function testMeasurementValuePassesThroughUnmaskedByDefault(): void
    {
        $original = Measurement::counter(
            MeasurementName::fromString('requests.total'),
            ExecutionId::fromString('exec-1'),
            42,
            attributes: AttributeCollection::fromArray(['api_key' => 'sk-live-secret']),
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink);
        $publisher->publish($original);

        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        self::assertIsArray($normalized['attributes']);
        // Measurement values are opt-in (redactMeasurementValues defaults false),
        // so the value and unclassified attributes reach the sink unchanged.
        self::assertSame(42, $normalized['value']);
        self::assertSame('sk-live-secret', $normalized['attributes']['api_key']);
    }

    public function testSamplingDropReportsPayloadFreeNoticeToHealthStandalone(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$reports): void {
                $reports[] = [$error, $artifact];
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $sink, $health);

        $original = Observation::info(
            ObservationId::fromString('obs-drop'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );

        $publisher->publish($original);

        self::assertCount(0, $sink->published(), 'The dropped artifact never reaches the sink.');
        self::assertCount(1, $reports, 'The sampling drop is reported through the health channel.');
        self::assertNotSame($original, $reports[0][1]);
        self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $reports[0][1]->normalize());
    }

    public function testComposedStackSamplingDropReportsPayloadFreeNotice(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$reports): void {
                $reports[] = [$error, $artifact];
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $policy = new class () implements SamplingPolicyInterface {
            public function sample(ExecutionArtifactInterface $artifact): bool
            {
                return false;
            }
        };
        // PublisherStackFactory::compose does not wire a health reporter, so the
        // stack is assembled by hand to preserve order: redactor → sampling → sink.
        $sampling = new SamplingArtifactPublisher($policy, $sink, $health);
        $publisher = new RedactingArtifactPublisher(
            $this->redactor(['password']),
            $sampling,
        );

        $original = Observation::info(
            ObservationId::fromString('obs-composed'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2', 'user' => 'alice']),
        );

        $publisher->publish($original);

        self::assertCount(0, $sink->published(), 'Nothing reaches the sink when sampling drops everything.');
        self::assertCount(1, $reports, 'The sampling drop is reported through the health channel.');
        self::assertNotSame($original, $reports[0][1]);
        self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $reports[0][1]->normalize());
    }

    public function testDropRedactedObservationReportsPayloadFreeIdentityOnly(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$reports): void {
                $reports[] = [$error, $artifact];
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            $this->redactor(['password']),
            $sink,
            $health,
            dropRedactedObservations: true,
        );

        $original = Observation::info(
            ObservationId::fromString('obs-drop-report'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );

        $publisher->publish($original);

        self::assertCount(0, $sink->published(), 'A redacted observation is dropped when the option is enabled.');
        self::assertCount(1, $reports);
        /** @var array<string, mixed> $normalized */
        $normalized = $reports[0][1]->normalize();
        // The notice carries only safe identity, no untrusted identifier or payload.
        self::assertSame('redacted', $normalized['id']);
        self::assertSame('runit.artifact.redacted', $normalized['type']);
        self::assertArrayNotHasKey('message', $normalized);
        self::assertArrayNotHasKey('attributes', $normalized);
        self::assertArrayNotHasKey(ArtifactField::EXECUTION_ID, $normalized);
    }

    public function testRedactedObservationIsPublishedAsIsWhenNothingWasRedacted(): void
    {
        $reports = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error) use (&$reports): void {
                $reports[] = $error;
            },
        );
        $sink = new InMemoryArtifactPublisher();
        // Observation messages are not masked here so nothing is redacted.
        $publisher = new RedactingArtifactPublisher(
            $this->redactor(['password'], false),
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

        self::assertCount(1, $sink->published(), 'An observation needing no redaction is still published.');
        self::assertCount(0, $reports, 'No health report when nothing was redacted.');
        self::assertSame($original, $sink->published()[0], 'The unmodified instance is passed through.');
    }

    public function testSensitiveExceptionChainSecretsDoNotLeakThroughTheSinkWhenConfigured(): void
    {
        $throwable = ThrowableDescription::create(
            'RuntimeException',
            'db password leaked: ' . SensitiveExceptionChain::dbPassword(),
            2,
            previous: null,
            trace: 'trace: ' . SensitiveExceptionChain::apiKey(),
        );
        $original = Observation::error(
            ObservationId::fromString('obs-chain'),
            ExecutionId::fromString('exec-1'),
            'failed',
            throwableDescription: $throwable,
        );

        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink);
        $publisher->publish($original);

        /** @var array<string, mixed> $normalized */
        $normalized = $sink->published()[0]->normalize();
        self::assertIsArray($normalized['throwable']);
        self::assertSame('[redacted]', $normalized['throwable']['message']);
        self::assertNull($normalized['throwable']['trace']);
    }

    public function testRedactorReturnsSameInstanceWhenNothingMatches(): void
    {
        $original = Observation::info(
            ObservationId::fromString('obs-nothing'),
            ExecutionId::fromString('exec-1'),
            'hello',
            attributes: AttributeCollection::fromArray(['user' => 'alice']),
        );

        $sink = new InMemoryArtifactPublisher();
        // No redaction is configured so nothing matches and the same instance passes through.
        $publisher = new RedactingArtifactPublisher($this->redactor([], false), $sink);
        $publisher->publish($original);

        self::assertSame($original, $sink->published()[0], 'With no redaction the same instance is published.');
    }

    public function testRedactedObservationHealthReEntryIsDroppedAndGuardResets(): void
    {
        $reports = [];
        $holder = new class () {
            public ?RedactingArtifactPublisher $publisher = null;
        };
        $original = Observation::info(
            ObservationId::fromString('obs-reentry'),
            ExecutionId::fromString('exec-1'),
            'token=abc123 message',
            attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
        );
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (
                &$reports,
                $holder,
                $original,
            ): void {
                $reports[] = $artifact->normalize();
                // Bound recursion so a missing guard fails the assertion rather than exhausting the stack.
                if (count($reports) < 3 && $holder->publisher instanceof RedactingArtifactPublisher) {
                    $holder->publisher->publish($original);
                }
            },
        );
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher(
            $this->redactor(['password']),
            $sink,
            $health,
            dropRedactedObservations: true,
        );
        $holder->publisher = $publisher;

        $publisher->publish($original);
        self::assertCount(1, $reports, 'Re-entry must not trigger another health report.');
        $publisher->publish($original);

        self::assertCount(2, $reports, 'A later independent drop must still be reported.');
        self::assertSame(['id' => 'redacted', 'type' => 'runit.artifact.redacted'], $reports[0]);
        self::assertSame($reports[0], $reports[1]);
        self::assertCount(0, $sink->published(), 'Neither original nor re-entrant observations reach the sink.');
    }

    public function testRedactedObservationHealthGuardResetsAfterReporterThrows(): void
    {
        $entered = 0;
        $health = new readonly class (function () use (&$entered): never {
            $entered++;
            throw new RuntimeException('health failure');
        }) implements InternalHealthReporterInterface {
            public function __construct(private Closure $callback)
            {
            }

            public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
            {
                ($this->callback)();
            }
        };
        $sink = new InMemoryArtifactPublisher();
        $publisher = new RedactingArtifactPublisher($this->redactor([]), $sink, $health, true);
        $original = Observation::info(
            ObservationId::fromString('obs-throwing-health'),
            ExecutionId::fromString('exec-1'),
            'secret',
        );

        $publisher->publish($original);
        $publisher->publish($original);

        self::assertSame(2, $entered, 'A reporter failure must not suppress subsequent reports.');
        self::assertCount(0, $sink->published());
    }
}

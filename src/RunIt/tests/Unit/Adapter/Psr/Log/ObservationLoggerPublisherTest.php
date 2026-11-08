<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use Tests\Support\Fixture\Psr\InMemoryLogger;
use Tests\Support\Fixture\SensitiveExceptionChain;

final class ObservationLoggerPublisherTest extends Unit
{
    public function testPublishesObservationWithLevelMessageContextAndCorrelation(): void
    {
        $logger = new InMemoryLogger();
        $publisher = new ObservationLoggerPublisher($logger);

        $observation = Observation::error(
            ObservationId::fromString('o-1'),
            ExecutionId::fromString('exec-1'),
            'request failed',
            diagnosticCode: 'NETWORK',
            attributes: AttributeCollection::fromArray(['attempt' => 3]),
            throwableDescription: ThrowableDescription::create(
                'RuntimeException',
                'boom',
                0,
                previous: null,
                trace: null,
            ),
        );

        $publisher->publish($observation);

        self::assertSame(1, $logger->count());
        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('error', $entry['level']);
        self::assertSame('request failed', $entry['message']);
        self::assertSame(3, $entry['context']['attempt']);
        self::assertSame('exec-1', $entry['context']['execution_id']);
        self::assertSame('NETWORK', $entry['context']['diagnostic_code']);
        self::assertArrayHasKey('exception', $entry['context']);
        $exceptionContext = $entry['context']['exception'];
        self::assertIsArray($exceptionContext);
        self::assertSame('boom', $exceptionContext['message']);
    }

    public function testRedactionIsAppliedBeforeLogging(): void
    {
        $logger = new InMemoryLogger();
        $redactor = new class () implements RedactorInterface {
            public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
            {
                return new RedactedArtifact([
                    'kind' => 'observation',
                    'message' => 'secret action',
                    'level' => 400,
                    'severity' => 'error',
                    'attributes' => ['password' => '***'],
                    'execution_id' => 'exec-9',
                    'activity_id' => null,
                    'id' => 'o-9',
                    'diagnostic_code' => null,
                    'sequence' => 1,
                    'timestamp' => 1,
                    'throwable' => null,
                ]);
            }
        };

        $publisher = new ObservationLoggerPublisher($logger, $redactor);

        $publisher->publish(
            Observation::info(
                ObservationId::fromString('o-9'),
                ExecutionId::fromString('exec-9'),
                'secret action',
                attributes: AttributeCollection::fromArray(['password' => 'hunter2']),
            ),
        );

        $entry = $logger->last();
        self::assertNotNull($entry);
        $context = $entry['context'];
        self::assertSame('***', $context['password']);
        self::assertSame('exec-9', $context['execution_id']);
    }

    public function testConfiguredRedactorMasksValuesAndPreservesMetadata(): void
    {
        $logger = new InMemoryLogger();
        $publisher = new ObservationLoggerPublisher($logger, new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']),
        ));
        $observation = Observation::error(
            ObservationId::fromString('o-mask'),
            ExecutionId::fromString('exec-mask'),
            'request failed',
            activityId: ActivityId::fromString('activity-mask'),
            diagnosticCode: 'NETWORK',
            attributes: AttributeCollection::fromArray(['password' => 'private-value', 'attempt' => 3]),
            throwableDescription: ThrowableDescription::create(
                'RuntimeException',
                'private message',
                42,
                previous: ThrowableDescription::create(
                    'Exception',
                    'private cause',
                    7,
                    previous: null,
                    trace: 'private cause trace',
                ),
                trace: 'private trace',
            ),
        );

        $publisher->publish($observation);

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('error', $entry['level']);
        self::assertSame('request failed', $entry['message']);
        self::assertSame('[redacted]', $entry['context']['password']);
        self::assertSame(3, $entry['context']['attempt']);
        self::assertSame('exec-mask', $entry['context']['execution_id']);
        self::assertSame('activity-mask', $entry['context']['activity_id']);
        self::assertSame('o-mask', $entry['context']['observation_id']);
        self::assertSame('NETWORK', $entry['context']['diagnostic_code']);
        self::assertSame($observation->sequence(), $entry['context']['sequence']);
        self::assertSame($observation->timestamp(), $entry['context']['timestamp']);
        self::assertSame('error', $entry['context']['severity']);
        self::assertSame([
            'class' => 'RuntimeException',
            'message' => '[redacted]',
            'code' => 42,
            'previous' => [
                'class' => 'Exception',
                'message' => '[redacted]',
                'code' => 7,
                'previous' => null,
                'trace' => '[redacted]',
            ],
            'trace' => '[redacted]',
        ], $entry['context']['exception']);
        self::assertSame('private-value', $observation->attributes()['password']);
    }

    /** Characterizes nested attribute exposure and throwable masking at the logger boundary. */
    public function testNestedAttributeKeysRemainVisibleWhileThrowableChainIsMasked(): void
    {
        $logger = new InMemoryLogger();
        $publisher = new ObservationLoggerPublisher($logger, new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']),
        ));
        $observation = Observation::error(
            ObservationId::fromString('o-nested'),
            ExecutionId::fromString('exec-nested'),
            'request failed',
            attributes: AttributeCollection::fromArray([
                'password' => 'top-level-secret',
                'request' => ['password' => 'nested-secret', 'method' => 'POST'],
            ]),
            throwableDescription: SensitiveExceptionChain::classifiedChain(),
        );
        $before = $observation->normalize();

        $publisher->publish($observation);

        self::assertSame(1, $logger->count());
        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('[redacted]', $entry['context']['password']);
        self::assertSame(['password' => 'nested-secret', 'method' => 'POST'], $entry['context']['request']);
        $throwable = $entry['context']['exception'];
        foreach ([['RuntimeException', 2], ['PDOException', 1], ['RuntimeException', 0]] as [$class, $code]) {
            self::assertIsArray($throwable);
            self::assertSame($class, $throwable['class']);
            self::assertSame($code, $throwable['code']);
            self::assertSame('[redacted]', $throwable['message']);
            self::assertSame('[redacted]', $throwable['trace']);
            $throwable = $throwable['previous'];
        }

        self::assertNull($throwable);
        foreach (SensitiveExceptionChain::secrets() as $secret) {
            self::assertStringNotContainsString($secret, serialize($entry));
        }

        self::assertSame($before, $observation->normalize());
    }

    /** Characterizes the separate observation message at the PSR-3 boundary. */
    public function testObservationMessageRemainsVisibleWhenOtherFieldsAreRedacted(): void
    {
        $logger = new InMemoryLogger();
        $publisher = new ObservationLoggerPublisher($logger, new DefaultArtifactRedactor(
            new ArtifactRedactionConfig(sensitiveAttributeKeys: ['password']),
        ));
        $observation = Observation::error(
            ObservationId::fromString('o-message'),
            ExecutionId::fromString('exec-message'),
            'message-secret',
            attributes: AttributeCollection::fromArray(['password' => 'attribute-secret']),
            throwableDescription: ThrowableDescription::create(
                'RuntimeException',
                'throwable-secret',
                42,
                previous: null,
                trace: 'trace-secret',
            ),
        );
        $before = $observation->normalize();

        $publisher->publish($observation);

        self::assertSame(1, $logger->count());
        $entry = $logger->last();
        self::assertNotNull($entry);
        // Current behavior, not an assertion that message export is safe.
        self::assertSame('message-secret', $entry['message']);
        self::assertSame('[redacted]', $entry['context']['password']);
        self::assertSame([
            'class' => 'RuntimeException',
            'message' => '[redacted]',
            'code' => 42,
            'previous' => null,
            'trace' => '[redacted]',
        ], $entry['context']['exception']);
        self::assertSame('exec-message', $entry['context']['execution_id']);
        self::assertSame($before, $observation->normalize());
    }

    public function testDisabledRedactionPreservesBenignThrowableDetails(): void
    {
        $logger = new InMemoryLogger();
        $publisher = new ObservationLoggerPublisher(
            $logger,
            new DefaultArtifactRedactor(ArtifactRedactionConfig::disabled()),
        );
        $publisher->publish(Observation::error(
            ObservationId::fromString('o-opt-out'),
            ExecutionId::fromString('exec-opt-out'),
            'operation failed',
            throwableDescription: ThrowableDescription::create(
                'RuntimeException',
                'example',
                2,
                previous: null,
                trace: null,
            ),
        ));

        $entry = $logger->last();
        self::assertNotNull($entry);
        self::assertSame('exec-opt-out', $entry['context']['execution_id']);
        self::assertSame('o-opt-out', $entry['context']['observation_id']);
        $exception = $entry['context']['exception'];
        self::assertIsArray($exception);
        self::assertSame('example', $exception['message']);
        self::assertSame(2, $exception['code']);
    }
}

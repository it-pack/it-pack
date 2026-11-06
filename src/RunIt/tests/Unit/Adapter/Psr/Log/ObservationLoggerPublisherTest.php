<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Log;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Log\ObservationLoggerPublisher;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use Tests\Support\Fixture\Psr\InMemoryLogger;

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
}

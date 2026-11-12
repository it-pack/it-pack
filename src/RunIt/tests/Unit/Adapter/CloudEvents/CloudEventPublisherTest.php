<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Application\Publication\ArtifactRedactionConfig;
use ItPack\RunIt\Application\Publication\ClassifiedArtifact;
use ItPack\RunIt\Application\Publication\ClassifyingArtifactPublisher;
use ItPack\RunIt\Application\Publication\DefaultArtifactRedactor;
use ItPack\RunIt\Application\Publication\RedactedArtifact;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use Tests\Support\Fixture\OpenTelemetry\RunItArtifactFactory;

/**
 * M10.5 / M10 acceptance — CloudEvent publisher dispatches occurrences only.
 */
final class CloudEventPublisherTest extends Unit
{
    public function testPublishesOccurrenceAsCloudEvent(): void
    {
        $captured = null;
        $publisher = new CloudEventPublisher(
            new DefaultOccurrenceToCloudEventMapper(),
            new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }),
        );

        $publisher->publish(
            RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'itpack.configuration.loaded.v1'),
        );

        self::assertInstanceOf(CloudEvent::class, $captured);
        self::assertSame('occ-1', $captured->id);
        self::assertSame('exec-1', $captured->extensions['executionid']);
    }

    public function testIgnoresNonOccurrenceArtifacts(): void
    {
        $calls = 0;
        $publisher = new CloudEventPublisher(
            new DefaultOccurrenceToCloudEventMapper(),
            new CallbackCloudEventSink(static function () use (&$calls): void {
                $calls++;
            }),
        );

        $nonOccurrence = RunItArtifactFactory::execution('exec-1', 'import', 'queue.consumer.process', 'succeeded');
        self::assertInstanceOf(ExecutionArtifactInterface::class, $nonOccurrence);

        $publisher->publish($nonOccurrence);
        $publisher->publish(new ClassifiedArtifact(['id' => 'exec-1'], 'execution'));
        $publisher->publish(new RedactedArtifact(['id' => 'measurement-1'], 'measurement'));

        self::assertSame(0, $calls);
    }

    public function testClassifiedOccurrenceProducesSuppressedEvent(): void
    {
        $captured = null;
        $publisher = new CloudEventPublisher(
            new DefaultOccurrenceToCloudEventMapper(),
            new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }),
        );
        $publisher->publish(new ClassifiedArtifact([
            'id' => 'occ-1', 'execution_id' => 'exec-1', 'type' => 'test.event.v1',
        ], 'occurrence'));

        self::assertInstanceOf(CloudEvent::class, $captured);
        self::assertSame('occ-1', $captured->id);
        self::assertTrue($captured->extensions['redacted']);
    }

    public function testClassifiedOccurrenceUsesNormalMappingWithoutLeakingRedactedData(): void
    {
        $captured = null;
        $publisher = new ClassifyingArtifactPublisher(new CloudEventPublisher(
            new DefaultOccurrenceToCloudEventMapper(),
            new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }),
        ));
        $original = RunItArtifactFactory::occurrence('occ-1', 'exec-1', 'test.event.v1', [
            'password' => 'secret-value', 'safe' => 'visible',
        ]);
        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(sensitiveDataKeys: ['password']));
        $publisher->publish($redactor->redact($original));

        self::assertInstanceOf(CloudEvent::class, $captured);
        self::assertIsArray($captured->data);
        self::assertSame('visible', $captured->data['safe']);
        self::assertNotSame('secret-value', $captured->data['password']);
        self::assertArrayNotHasKey('redacted', $captured->extensions);
    }

    public function testBuildsFromBridgeConfig(): void
    {
        $captured = null;
        $config = CloudEventsBridgeConfig::builder()
            ->withSink(new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }))
            ->build()
        ;

        $publisher = CloudEventPublisher::fromConfig($config);
        $publisher->publish(
            RunItArtifactFactory::occurrence('occ-2', 'exec-2', 'itpack.job.finished.v1'),
        );

        self::assertInstanceOf(CloudEvent::class, $captured);
        self::assertSame('occ-2', $captured->id);
    }

    public function testRequiresSinkInBuilder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CloudEventsBridgeConfig::builder()->build();
    }

    public function testBridgeConfigAcceptsPolicyAndCustomMapper(): void
    {
        $captured = null;
        $config = CloudEventsBridgeConfig::builder()
            ->withSink(new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }))
            ->withPolicy(new CloudEventMappingPolicy(specVersion: '1.0'))
            ->withMapper(new DefaultOccurrenceToCloudEventMapper())
            ->build()
        ;

        $publisher = CloudEventPublisher::fromConfig($config);
        $publisher->publish(
            RunItArtifactFactory::occurrence('occ-3', 'exec-3', 'itpack.configuration.loaded.v1'),
        );

        self::assertInstanceOf(CloudEvent::class, $captured);
        self::assertSame('1.0', $captured->specVersion);
        self::assertSame('occ-3', $captured->id);
    }

    public function testRedactedOccurrenceProducesVisibleSuppressedDelivery(): void
    {
        $captured = null;
        $publisher = new CloudEventPublisher(
            new DefaultOccurrenceToCloudEventMapper(),
            new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }),
        );

        $redactor = new DefaultArtifactRedactor(new ArtifactRedactionConfig(
            sensitiveDataKeys: ['password'],
            redactOccurrenceData: true,
        ));

        $occurrence = RunItArtifactFactory::occurrence(
            'occ-red',
            'exec-1',
            'itpack.test.event.v1',
            ['password' => 'hunter2']
        );
        $redacted = $redactor->redact($occurrence);

        self::assertInstanceOf(
            RedactedArtifactInterface::class,
            $redacted,
            'A redacted artifact is marked as suppressed.'
        );

        $publisher->publish($redacted);

        self::assertInstanceOf(
            CloudEvent::class,
            $captured,
            'A redacted occurrence is still delivered, not silently dropped.'
        );
        self::assertSame('occ-red', $captured->id);
        self::assertSame('itpack.test.event.v1', $captured->type);
        self::assertTrue($captured->extensions['redacted'], 'The suppressed delivery declares redacted=true.');
    }
}

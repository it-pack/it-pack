<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventPublisher;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultOccurrenceToCloudEventMapper;
use ItPack\RunIt\Adapter\CloudEvents\Sink\CallbackCloudEventSink;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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

        self::assertSame(0, $calls);
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
        $this->expectException(\InvalidArgumentException::class);

        CloudEventsBridgeConfig::builder()->build();
    }

    public function testBridgeConfigAcceptsPolicyAndCustomMapper(): void
    {
        $captured = null;
        $config = CloudEventsBridgeConfig::builder()
            ->withSink(new CallbackCloudEventSink(static function (CloudEvent $event) use (&$captured): void {
                $captured = $event;
            }))
            ->withPolicy(new \ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy(specVersion: '1.0'))
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
}

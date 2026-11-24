<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfigBuilder;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use ItPack\RunIt\Adapter\CloudEvents\Contract\OccurrenceToCloudEventMapperInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudEventsBridgeConfigBuilder::class)]
final class CloudEventsBridgeConfigBuilderTest extends Unit
{
    public function testBuildWithoutSinkThrows(): void
    {
        $builder = new CloudEventsBridgeConfigBuilder();

        try {
            $builder->build();
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A CloudEvent sink is required to build the bridge config.',
                $invalidArgumentException->getMessage()
            );
        }
    }

    public function testBuildWithSink(): void
    {
        $sink = $this->createSink();

        $config = new CloudEventsBridgeConfigBuilder()
            ->withSink($sink)
            ->build()
        ;

        self::assertSame($sink, $config->sink);
    }

    public function testWithPolicy(): void
    {
        $policy = CloudEventMappingPolicy::default();

        $config = new CloudEventsBridgeConfigBuilder()
            ->withSink(
                $this->createSink()
            )->withPolicy(
                $policy
            )->build()
        ;

        self::assertSame($policy, $config->policy);
    }

    public function testWithMapper(): void
    {
        $mapper = new class () implements OccurrenceToCloudEventMapperInterface {
            public function map(OccurrenceInterface $occurrence, ?CloudEventMappingPolicy $policy = null): CloudEvent
            {
                return CloudEvent::create('id', 'source', 'type');
            }
        };

        $config = new CloudEventsBridgeConfigBuilder()
            ->withSink(
                $this->createSink()
            )->withMapper(
                $mapper
            )->build()
        ;

        self::assertSame($mapper, $config->mapper);
    }

    private function createSink(): CloudEventSinkInterface
    {
        return new class () implements CloudEventSinkInterface {
            public function send(object $event): void
            {
            }
        };
    }

}

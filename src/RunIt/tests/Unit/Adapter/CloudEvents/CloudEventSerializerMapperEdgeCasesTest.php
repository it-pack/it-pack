<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultCloudEventToOccurrenceMapper;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultCloudEventJsonSerializer::class)]
#[CoversClass(DefaultCloudEventToOccurrenceMapper::class)]
final class CloudEventSerializerMapperEdgeCasesTest extends Unit
{
    /** @return array<string, array{string}> */
    public static function invalidStructuredEvents(): array
    {
        return [
            'invalid URI escape' => ['{"specversion":"1.0","id":"id","source":"/bad%zz","type":"example"}'],
            'invalid calendar date' => [
                '{"specversion":"1.0","id":"id","source":"/s","type":"example","time":"2026-02-30T12:00:00Z"}',
            ],
            'excess timestamp precision' => [
                '{"specversion":"1.0","id":"id","source":"/s","type":"example","time":"2026-02-01T12:00:00.1234567Z"}',
            ],
            'empty subject' => ['{"specversion":"1.0","id":"id","source":"/s","type":"example","subject":""}'],
            'binary data field' => [
                '{"specversion":"1.0","id":"id","source":"/s","type":"example","data_base64":"YQ=="}',
            ],
            'non-scalar extension' => ['{"specversion":"1.0","id":"id","source":"/s","type":"example","extra":[1]}'],
        ];
    }

    #[DataProvider('invalidStructuredEvents')]
    public function testStrictDeserializerRejectsUnrepresentableEvents(string $payload): void
    {
        $this->expectException(InvalidCloudEventException::class);

        new DefaultCloudEventJsonSerializer()->deserialize($payload);
    }

    public function testLegacyFallbackPreservesAnUnsupportedExtension(): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(allowLegacyFallback: true));
        $event = CloudEvent::create('id', '/s', 'example', extensions: ['custom-key' => 'value']);

        $json = $serializer->serialize($event);

        self::assertSame('value', $serializer->deserialize($json)->extensions['custom-key']);
    }

    public function testSerializerRejectsReservedExtensionCollision(): void
    {
        $event = CloudEvent::create('id', '/s', 'example')->withExtensions(['data_base64' => 'YQ==']);

        $this->expectException(InvalidCloudEventException::class);
        $this->expectExceptionMessage('collides with a reserved field');
        new DefaultCloudEventJsonSerializer()->serialize($event);
    }

    public function testMapperPreservesNonStringCorrelationAsAnOrdinaryAttribute(): void
    {
        $event = CloudEvent::create('id', '/s', 'example.event.v1', extensions: [
            CloudEventExtensionKey::ExecutionId->value => 'exec-1',
            CloudEventExtensionKey::CorrelationId->value => 123,
            CloudEventExtensionKey::Sequence->value => 'not-numeric',
        ]);

        $occurrence = new DefaultCloudEventToOccurrenceMapper()->map($event);

        self::assertSame(0, $occurrence->sequence());
        self::assertSame(123, $occurrence->extensionAttributes()[CloudEventExtensionKey::CorrelationId->value]);
    }
}

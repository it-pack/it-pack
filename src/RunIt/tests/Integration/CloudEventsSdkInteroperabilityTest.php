<?php

declare(strict_types=1);

namespace Tests\Integration;

use CloudEvents\Serializers\JsonDeserializer;
use CloudEvents\Serializers\JsonSerializer;
use CloudEvents\V1\CloudEventImmutable;
use CloudEvents\V1\CloudEventInterface as SdkCloudEventInterface;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

final class CloudEventsSdkInteroperabilityTest extends Unit
{
    public function testSdkReadsRunItStructuredEvent(): void
    {
        $event = CloudEvent::create(
            'event-1',
            '/orders',
            'com.example.created',
            ['items' => [1, 2]],
            'order-1',
            null,
            'application/json',
            'https://example.com/schema',
            ['tenant' => 'acme', 'sequence' => 7, 'active' => true],
        );
        $serializer = new DefaultCloudEventJsonSerializer();
        $json = $serializer->serialize($event);
        $sdk = JsonDeserializer::create()->deserializeStructured($json);

        self::assertInstanceOf(SdkCloudEventInterface::class, $sdk);
        self::assertSame('1.0', $sdk->getSpecVersion());
        self::assertSame('event-1', $sdk->getId());
        self::assertSame('/orders', $sdk->getSource());
        self::assertSame('com.example.created', $sdk->getType());
        self::assertSame('order-1', $sdk->getSubject());
        self::assertSame('application/json', $sdk->getDataContentType());
        self::assertSame('https://example.com/schema', $sdk->getDataSchema());
        self::assertSame(['items' => [1, 2]], $sdk->getData());
        self::assertSame(['tenant' => 'acme', 'sequence' => 7, 'active' => true], $sdk->getExtensions());
    }

    public function testRunItReadsSdkStructuredEvent(): void
    {
        $sdk = new CloudEventImmutable(
            'event-2',
            '/orders',
            'com.example.updated',
            ['amount' => 42],
            'application/json',
            'https://example.com/schema',
            'order-2',
            extensions: ['tenant' => 'acme', 'sequence' => 8],
        );
        $json = JsonSerializer::create()->serializeStructured($sdk);
        $serializer = new DefaultCloudEventJsonSerializer();
        $event = $serializer->deserialize($json);

        self::assertSame('1.0', $event->specVersion);
        self::assertSame('event-2', $event->id);
        self::assertSame('/orders', $event->source);
        self::assertSame('com.example.updated', $event->type);
        self::assertSame('order-2', $event->subject);
        self::assertSame('application/json', $event->dataContentType);
        self::assertSame('https://example.com/schema', $event->dataSchema);
        self::assertSame(['amount' => 42], $event->data);
        self::assertSame(['tenant' => 'acme', 'sequence' => 8], $event->extensions);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function incompatibleExtensions(): array
    {
        return [
            'hyphenated name' => ['tenant-id', 'acme', '1.0'],
            'array value' => ['extra', ['a' => 1], '1.0'],
            'float value' => ['extra', 1.5, '1.0'],
            'null value' => ['extra', null, '1.0'],
            'unsupported version' => ['extra', 'value', '0.3'],
        ];
    }

    #[DataProvider('incompatibleExtensions')]
    public function testStrictRejectsLegacyEvent(string $name, mixed $value, string $version): void
    {
        $event = CloudEvent::create(
            'event-3',
            '/orders',
            'com.example.created',
            extensions: [$name => $value],
            specVersion: $version,
        );
        $serializer = new DefaultCloudEventJsonSerializer();
        $this->expectException(InvalidCloudEventException::class);
        $serializer->serialize($event);
    }

    #[DataProvider('incompatibleExtensions')]
    public function testFallbackPreservesWholeLegacyEvent(string $name, mixed $value, string $version): void
    {
        $event = CloudEvent::create(
            'event-3',
            '/orders',
            'com.example.created',
            ['nested' => ['value' => 12]],
            'subject',
            null,
            'application/json',
            extensions: [$name => $value],
            specVersion: $version,
        );
        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(allowLegacyFallback: true));
        $json = $serializer->serialize($event);

        self::assertSame($event->toArray(), json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($event->toArray(), $serializer->deserialize($json)->toArray());
    }

    #[DataProvider('incompatibleExtensions')]
    public function testStrictRejectsLegacyInput(string $name, mixed $value, string $version): void
    {
        $event = CloudEvent::create(
            'event-3',
            '/orders',
            'com.example.created',
            extensions: [$name => $value],
            specVersion: $version,
        );
        $json = json_encode($event->toArray(), JSON_THROW_ON_ERROR);
        $serializer = new DefaultCloudEventJsonSerializer();
        $this->expectException(InvalidCloudEventException::class);
        $serializer->deserialize($json);
    }

    #[DataProvider('policies')]
    public function testLargeJsonIntegerIsNotSilentlyRounded(bool $fallback): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(allowLegacyFallback: $fallback));
        $event = $serializer->deserialize(
            '{"specversion":"1.0","id":"1","source":"/s","type":"t","data":{"n":9223372036854775808}}',
        );
        self::assertSame(['n' => '9223372036854775808'], $event->data);
    }

    public function testSerializerDoesNotInheritAnotherSerializerPolicy(): void
    {
        $event = CloudEvent::create('1', '/s', 't', extensions: ['tenant-id' => 'acme']);
        $legacy = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(allowLegacyFallback: true));
        $json = $legacy->serialize($event);
        self::assertSame('acme', $legacy->deserialize($json)->extensions['tenant-id']);

        $strict = new DefaultCloudEventJsonSerializer();
        $this->expectException(InvalidCloudEventException::class);
        $strict->deserialize($json);
    }

    public function testStrictRejectsExtensionCollidingWithStandardField(): void
    {
        $event = CloudEvent::create('real-id', '/s', 't', extensions: ['id' => 'forged-id']);
        $serializer = new DefaultCloudEventJsonSerializer();
        $this->expectException(InvalidCloudEventException::class);
        $serializer->serialize($event);
    }

    public function testFallbackStillEnforcesOutputLimitOnIncompatibleEvent(): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(
            new CloudEventMappingPolicy(maxSerializedBytes: 10, allowLegacyFallback: true),
        );
        $this->expectException(InvalidCloudEventException::class);
        $serializer->serialize(CloudEvent::create('1', '/s', 't', extensions: ['tenant-id' => 'acme']));
    }

    public function testFallbackStillEnforcesDepthLimitOnIncompatibleInput(): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(
            new CloudEventMappingPolicy(allowLegacyFallback: true),
            new SecurityLimits(maxSerializationDepth: 3),
        );
        $this->expectException(InvalidCloudEventException::class);
        $serializer->deserialize(
            '{"specversion":"1.0","id":"1","source":"/s","type":"t","tenant-id":"x","data":{"a":{"b":1}}}',
        );
    }

    /** @return array<string, array{bool}> */
    public static function policies(): array
    {
        return ['strict' => [false], 'fallback' => [true]];
    }

    #[DataProvider('policies')]
    public function testMalformedJsonCannotFallBack(bool $fallback): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(allowLegacyFallback: $fallback));
        $this->expectException(InvalidCloudEventException::class);
        $serializer->deserialize('{"specversion":"1.0",');
    }

    #[DataProvider('policies')]
    public function testInputByteLimitCannotBeBypassed(bool $fallback): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(
            new CloudEventMappingPolicy(maxSerializedBytes: 10, allowLegacyFallback: $fallback),
        );
        $this->expectException(InvalidCloudEventException::class);
        $serializer->deserialize('{"specversion":"1.0","id":"1","source":"/s","type":"t"}');
    }

    #[DataProvider('policies')]
    public function testOutputByteLimitCannotBeBypassed(bool $fallback): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(
            new CloudEventMappingPolicy(maxSerializedBytes: 10, allowLegacyFallback: $fallback),
        );
        $this->expectException(InvalidCloudEventException::class);
        $serializer->serialize(CloudEvent::create('1', '/s', 't'));
    }

    #[DataProvider('policies')]
    public function testDepthLimitCannotBeBypassed(bool $fallback): void
    {
        $serializer = new DefaultCloudEventJsonSerializer(
            new CloudEventMappingPolicy(allowLegacyFallback: $fallback),
            new SecurityLimits(maxSerializationDepth: 3),
        );
        $this->expectException(InvalidCloudEventException::class);
        $serializer->deserialize('{"specversion":"1.0","id":"1","source":"/s","type":"t","data":{"a":{"b":1}}}');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSerializerInterface;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use ItPack\RunIt\Adapter\CloudEvents\Serialization\DefaultCloudEventJsonSerializer;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;

/**
 * M10.4 — structured JSON serialization, size limits, and malformed handling.
 */
final class CloudEventJsonSerializerTest extends Unit
{
    private CloudEventSerializerInterface $serializer;

    protected function _before(): void
    {
        $this->serializer = new DefaultCloudEventJsonSerializer();
    }

    private function event(): CloudEvent
    {
        return CloudEvent::create(
            'occ-1',
            '/source',
            'com.example.thing.happened.v1',
            ['outcome' => 'ok'],
            'subject-1',
            '2026-08-20T14:05:47Z',
            'application/json',
            null,
            ['executionid' => 'exec-1', 'tenantflag' => 'acme'],
        );
    }

    public function testRoundTripPreservesAllFields(): void
    {
        $serialized = $this->serializer->serialize($this->event());

        self::assertJson($serialized);

        $decoded = $this->serializer->deserialize($serialized);

        self::assertSame($this->event()->id, $decoded->id);
        self::assertSame($this->event()->type, $decoded->type);
        self::assertSame($this->event()->data, $decoded->data);
        self::assertSame('acme', $decoded->extensions['tenantflag']);
        self::assertSame('exec-1', $decoded->extensions['executionid']);
    }

    public function testUsesStructuredContentTypeConstant(): void
    {
        self::assertSame('application/cloudevents+json', DefaultCloudEventJsonSerializer::CONTENT_TYPE);
    }

    public function testRejectsOversizedSerialization(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(maxSerializedBytes: 10));
        $serializer->serialize($this->event());
    }

    public function testRejectsOversizedInput(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $serializer = new DefaultCloudEventJsonSerializer(new CloudEventMappingPolicy(maxSerializedBytes: 10));
        $serializer->deserialize('{"specversion":"1.0","id":"x","source":"/s","type":"t"}');
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $this->serializer->deserialize('{not json');
    }

    public function testRejectsMissingRequiredField(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        $this->serializer->deserialize('{"specversion":"1.0","id":"x","source":"/s"}');
    }
}

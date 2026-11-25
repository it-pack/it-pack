<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(CloudEvent::class)]
final class CloudEventTest extends Unit
{
    public function testCreateBuildsEvent(): void
    {
        $event = CloudEvent::create('id-1', 'source-1', 'type-1');

        self::assertSame('id-1', $event->id);
        self::assertSame('source-1', $event->source);
        self::assertSame('type-1', $event->type);
    }

    public function testCreateRejectsEmptyRequiredField(): void
    {
        $this->expectException(InvalidCloudEventException::class);
        CloudEvent::create('', 'source-1', 'type-1');
    }

    public function testCreateRejectsUnsupportedExtensionKey(): void
    {
        $this->expectException(InvalidCloudEventException::class);
        CloudEvent::create('id-1', 'source-1', 'type-1', extensions: ['Bad Key' => 'value']);
    }

    public function testCreateRejectsUnsupportedExtensionValue(): void
    {
        $this->expectException(InvalidCloudEventException::class);
        CloudEvent::create('id-1', 'source-1', 'type-1', extensions: ['key' => new stdClass()]);
    }

    public function testIsValidExtensionKey(): void
    {
        self::assertTrue(CloudEvent::isValidExtensionKey('valid-key'));
        self::assertTrue(CloudEvent::isValidExtensionKey('a-b-c'));
        self::assertFalse(CloudEvent::isValidExtensionKey('Invalid'));
        self::assertFalse(CloudEvent::isValidExtensionKey('with.dots'));
        self::assertFalse(CloudEvent::isValidExtensionKey(''));
    }

    public function testWithExtensionsAddsExtensions(): void
    {
        $event = CloudEvent::create('id-1', 'source-1', 'type-1');
        $extended = $event->withExtensions(['extra' => 'value', 'another' => 1]);

        self::assertSame(['extra' => 'value', 'another' => 1], $extended->extensions);
        self::assertSame([], $event->extensions);
    }

    public function testToArrayIncludesOptionalFieldsAndExtensions(): void
    {
        $event = CloudEvent::create(
            'id-1',
            'source-1',
            'type-1',
            data: ['payload' => true],
            subject: 'subject-1',
            time: '2024-01-01T00:00:00Z',
            dataContentType: 'application/json',
            dataSchema: 'schema-1',
            extensions: ['ext' => 'val'],
        );

        $array = $event->toArray();

        self::assertSame('subject-1', $array['subject']);
        self::assertSame('2024-01-01T00:00:00Z', $array['time']);
        self::assertSame('application/json', $array['datacontenttype']);
        self::assertSame('schema-1', $array['dataschema']);
        self::assertSame(['payload' => true], $array['data']);
        self::assertSame('val', $array['ext']);
        self::assertSame('1.0', $array['specversion']);
        self::assertSame('id-1', $array['id']);
        self::assertSame('source-1', $array['source']);
        self::assertSame('type-1', $array['type']);
    }
}

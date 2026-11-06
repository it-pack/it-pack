<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Contract\Exception\InvalidCloudEventException;
use stdClass;

/**
 * M10.2 / M10.4 — CloudEvent envelope validation and structure.
 */
final class CloudEventTest extends Unit
{
    public function testCreateProducesStructuredRepresentation(): void
    {
        $event = CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1');

        self::assertSame('1.0', $event->specVersion);
        self::assertSame('id-1', $event->id);
        self::assertSame('/source', $event->source);
        self::assertSame('com.example.thing.happened.v1', $event->type);

        $array = $event->toArray();
        self::assertSame('1.0', $array['specversion']);
        self::assertSame('id-1', $array['id']);
        self::assertArrayNotHasKey('subject', $array);
        self::assertArrayNotHasKey('data', $array);
    }

    public function testOptionalFieldsAreIncludedWhenPresent(): void
    {
        $event = CloudEvent::create(
            'id-1',
            '/source',
            'com.example.thing.happened.v1',
            ['outcome' => 'ok'],
            'subject-1',
            '2026-08-20T14:05:47Z',
            'application/json',
            'https://schema.example/thing',
            ['customflag' => true],
        );

        $array = $event->toArray();
        self::assertSame('subject-1', $array['subject']);
        self::assertSame('2026-08-20T14:05:47Z', $array['time']);
        self::assertSame('application/json', $array['datacontenttype']);
        self::assertSame('https://schema.example/thing', $array['dataschema']);
        self::assertSame(['outcome' => 'ok'], $array['data']);
        self::assertSame(true, $array['customflag']);
    }

    public function testRejectsEmptyRequiredFields(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('', '/source', 'com.example.thing.happened.v1');
    }

    public function testRejectsEmptySpecVersion(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1', extensions: [], specVersion: '');
    }

    public function testRejectsEmptyType(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '/source', '');
    }

    public function testRejectsEmptySource(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '', 'com.example.thing.happened.v1');
    }

    public function testRejectsInvalidExtensionKey(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1', extensions: [
            'BadKey' => 'x',
        ]);
    }

    public function testRejectsMalformedTime(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1', time: 'not-a-time');
    }

    public function testRejectsUnsupportedData(): void
    {
        $this->expectException(InvalidCloudEventException::class);

        CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1', new stdClass());
    }

    public function testWithExtensionsDoesNotMutateReceiver(): void
    {
        $event = CloudEvent::create('id-1', '/source', 'com.example.thing.happened.v1');
        $extended = $event->withExtensions(['added' => 1]);

        self::assertSame([], $event->extensions);
        self::assertSame(['added' => 1], $extended->extensions);
        self::assertNotSame($event, $extended);
    }
}

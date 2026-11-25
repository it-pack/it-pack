<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Mapping;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\DefaultCloudEventFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultCloudEventFactory::class)]
final class DefaultCloudEventFactoryTest extends Unit
{
    public function testCreateProducesCloudEvent(): void
    {
        $factory = new DefaultCloudEventFactory();

        $event = $factory->create(
            id: 'evt-1',
            source: '/test',
            type: 'itpack.test.v1',
        );

        $data = $event->toArray();
        self::assertSame('evt-1', $data['id']);
        self::assertSame('/test', $data['source']);
        self::assertSame('itpack.test.v1', $data['type']);
    }

    public function testCreateWithAllFields(): void
    {
        $factory = new DefaultCloudEventFactory();

        $event = $factory->create(
            id: 'evt-2',
            source: '/test',
            type: 'itpack.test.v1',
            data: ['key' => 'value'],
            subject: 'subj',
            time: '2024-01-01T00:00:00Z',
            dataContentType: 'application/json',
            dataSchema: 'schema-1',
            extensions: ['ext' => 'val'],
            specVersion: CloudEvent::SPEC_VERSION_1_0,
        );

        $data = $event->toArray();
        self::assertSame('evt-2', $data['id']);
        self::assertSame(['key' => 'value'], $data['data']);
        self::assertSame('subj', $data['subject']);
    }

    public function testCreateWithDefaultSpecVersion(): void
    {
        $factory = new DefaultCloudEventFactory();

        $event = $factory->create('evt-3', '/test', 'itpack.test.v1');

        self::assertSame(CloudEvent::SPEC_VERSION_1_0, $event->toArray()['specversion']);
    }
}

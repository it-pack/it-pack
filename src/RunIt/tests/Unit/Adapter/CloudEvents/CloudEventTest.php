<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEvent;
use PHPUnit\Framework\Attributes\CoversClass;

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

    public function testIsValidExtensionKey(): void
    {
        self::assertTrue(CloudEvent::isValidExtensionKey('valid-key'));
        self::assertTrue(CloudEvent::isValidExtensionKey('a-b-c'));
        self::assertFalse(CloudEvent::isValidExtensionKey('Invalid'));
        self::assertFalse(CloudEvent::isValidExtensionKey('with.dots'));
        self::assertFalse(CloudEvent::isValidExtensionKey(''));
    }
}

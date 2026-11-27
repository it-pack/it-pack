<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventAttributeKey;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudEventAttributeKey::class)]
final class CloudEventAttributeKeyTest extends Unit
{
    public function testCasesRepresentCloudEventsStructuredJsonKeys(): void
    {
        self::assertSame(
            ['specversion', 'id', 'source', 'type', 'subject', 'time', 'datacontenttype', 'dataschema',
                'data', 'data_base64'],
            array_map(static fn (CloudEventAttributeKey $key): string => $key->value, CloudEventAttributeKey::cases()),
        );
    }
}

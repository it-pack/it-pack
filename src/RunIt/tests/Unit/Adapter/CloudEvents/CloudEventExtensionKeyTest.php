<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventExtensionKey;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudEventExtensionKey::class)]
final class CloudEventExtensionKeyTest extends Unit
{
    public function testCasesHaveValues(): void
    {
        self::assertSame('executionid', CloudEventExtensionKey::ExecutionId->value);
        self::assertSame('activityid', CloudEventExtensionKey::ActivityId->value);
        self::assertSame('correlationid', CloudEventExtensionKey::CorrelationId->value);
        self::assertSame('sequence', CloudEventExtensionKey::Sequence->value);
    }

    public function testKnownKeysReturnsAllCases(): void
    {
        self::assertSame(
            ['executionid', 'activityid', 'correlationid', 'sequence'],
            CloudEventExtensionKey::knownKeys(),
        );
    }
}

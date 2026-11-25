<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents\Mapping;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\Mapping\CloudEventMappingPolicy;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudEventMappingPolicy::class)]
final class CloudEventMappingPolicyTest extends Unit
{
    public function testDefaults(): void
    {
        $policy = new CloudEventMappingPolicy();

        self::assertSame(CloudEventMappingPolicy::DEFAULT_MAX_SERIALIZED_BYTES, $policy->maxSerializedBytes);
    }

    public function testDefaultFactory(): void
    {
        self::assertInstanceOf(CloudEventMappingPolicy::class, CloudEventMappingPolicy::default());
    }
}

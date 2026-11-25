<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\CloudEvents;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfig;
use ItPack\RunIt\Adapter\CloudEvents\CloudEventsBridgeConfigBuilder;
use ItPack\RunIt\Adapter\CloudEvents\Contract\CloudEventSinkInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CloudEventsBridgeConfig::class)]
final class CloudEventsBridgeConfigTest extends Unit
{
    public function testPropertiesAreSet(): void
    {
        $sink = $this->createMock(CloudEventSinkInterface::class);
        $config = new CloudEventsBridgeConfig($sink);

        self::assertSame($sink, $config->sink);
        self::assertNull($config->mapper);
    }

    public function testBuilderReturnsBuilder(): void
    {
        self::assertInstanceOf(
            CloudEventsBridgeConfigBuilder::class,
            CloudEventsBridgeConfig::builder(),
        );
    }
}

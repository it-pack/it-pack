<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Poller\NativePoller;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Contract\CapabilityDescriptor;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Factory\PollerFactory
 */
final class PollerFactoryTest extends TestCase
{
    private function descriptor(bool $nativeEdge): CapabilityDescriptor
    {
        return new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => $nativeEdge
                ? CapabilityStatus::Supported
                : CapabilityStatus::Emulated,
        ]);
    }

    public function testFallsBackToSelectPollerWhenNativeUnavailable(): void
    {
        self::assertFalse(PollerFactory::nativePollAvailable());

        $poller = (new PollerFactory())->create($this->descriptor(true));

        self::assertInstanceOf(SelectPoller::class, $poller);
    }

    public function testSelectsNativePollerWhenIoPollAvailable(): void
    {
        if (!PollerFactory::nativePollAvailable()) {
            self::markTestSkipped('PHP 8.6 Io\\Poll not available.');
        }

        $poller = (new PollerFactory())->create($this->descriptor(true));

        self::assertInstanceOf(NativePoller::class, $poller);
    }
}

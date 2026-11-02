<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use ItPack\AsyncIt\Application\Factory\PollerFactory;
use ItPack\AsyncIt\Application\Poller\SelectPoller;
use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Factory\PollerFactory
 */
final class PollerFactoryTest extends TestCase
{
    public function testSelectPollerChosenWhenEdgeTriggeredNotSupported(): void
    {
        $factory = new PollerFactory();
        $descriptor = new CapabilityDescriptor([
            CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
        ]);

        $poller = $factory->create($descriptor);

        self::assertInstanceOf(SelectPoller::class, $poller);
    }

    public function testNativePollAvailableReturnsFalseOnPhp85(): void
    {
        // PHP 8.5.12 in this environment — Io\Poll is PHP 8.6 only
        self::assertFalse(PollerFactory::nativePollAvailable());
    }

    public function testSelectPollerChosenForEveryCapabilityStatus(): void
    {
        $factory = new PollerFactory();

        foreach ([CapabilityStatus::Supported, CapabilityStatus::Emulated, CapabilityStatus::Unavailable] as $status) {
            $descriptor = new CapabilityDescriptor([
                CapabilityKind::EdgeTriggeredPolling->value => $status,
            ]);

            self::assertInstanceOf(SelectPoller::class, $factory->create($descriptor));
        }
    }
}

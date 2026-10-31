<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Discovery;

use ItPack\AsyncIt\Application\Discovery\ReferenceRuntimeDiscovery;
use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\CapabilityObserverInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Discovery\ReferenceRuntimeDiscovery
 */
final class ReferenceRuntimeDiscoveryTest extends TestCase
{
    public function testDiscoverReturnsInjectedDescriptor(): void
    {
        $descriptor = new CapabilityDescriptor([]);

        $discovery = new ReferenceRuntimeDiscovery($descriptor);

        self::assertSame($descriptor, $discovery->discover());
    }

    public function testObserveStoresObserverForLaterNotification(): void
    {
        $discovery = new ReferenceRuntimeDiscovery(new CapabilityDescriptor([]));

        $observer = $this->createMock(CapabilityObserverInterface::class);

        $discovery->observe($observer);

        $newDescriptor = new CapabilityDescriptor([]);
        $observer->expects($this->once())
            ->method('onCapabilitiesChanged')
            ->with($newDescriptor)
        ;

        $discovery->notifyChanged($newDescriptor);
    }

    public function testNotifyChangedIsNoOpWithoutObserver(): void
    {
        $discovery = new ReferenceRuntimeDiscovery(new CapabilityDescriptor([]));

        $descriptor = new CapabilityDescriptor([]);

        $discovery->notifyChanged($descriptor);

        // No exception thrown — null-safe observer call.
        self::assertInstanceOf(CapabilityDescriptorInterface::class, $discovery->discover());
    }
}

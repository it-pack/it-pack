<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\CapabilityDescriptorInterface;
use ItPack\AsyncIt\Contract\CapabilityObserverInterface;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\CapabilityObserverInterface
 */
final class CapabilityObserverInterfaceTest extends TestCase
{
    public function testInterfaceExistsAndIsContract(): void
    {
        self::assertTrue(interface_exists(CapabilityObserverInterface::class));
    }

    public function testOnCapabilitiesChangedReceivesDescriptor(): void
    {
        $observer = new class () implements CapabilityObserverInterface {
            public mixed $received = null;

            public function onCapabilitiesChanged(CapabilityDescriptorInterface $descriptor): void
            {
                $this->received = $descriptor;
            }
        };

        $descriptor = new CapabilityDescriptor([]);
        $observer->onCapabilitiesChanged($descriptor);

        self::assertSame($descriptor, $observer->received);
    }
}

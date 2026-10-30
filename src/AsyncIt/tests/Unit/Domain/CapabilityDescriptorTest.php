<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\CapabilityDescriptor
 */
final class CapabilityDescriptorTest extends TestCase
{
    public function testReturnsKnownStatus(): void
    {
        $descriptor = new CapabilityDescriptor([
            CapabilityKind::FiberExecution->value => CapabilityStatus::Supported,
        ]);

        self::assertSame(CapabilityStatus::Supported, $descriptor->statusFor(CapabilityKind::FiberExecution));
    }

    public function testUnknownCapabilityDefaultsToUnavailable(): void
    {
        $descriptor = new CapabilityDescriptor([]);

        self::assertSame(CapabilityStatus::Unavailable, $descriptor->statusFor(CapabilityKind::NativeChannels));
    }

    public function testVersionDefaultsToOne(): void
    {
        $descriptor = new CapabilityDescriptor([]);

        self::assertSame(1, $descriptor->version());
    }

    public function testVersionIsMutableViaConstructor(): void
    {
        $descriptor = new CapabilityDescriptor([], 3);

        self::assertSame(3, $descriptor->version());
    }
}

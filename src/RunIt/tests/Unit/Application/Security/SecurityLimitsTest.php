<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Security;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * @group application
 * @group security
 */
final class SecurityLimitsTest extends Unit
{
    public function testDefaultLimitsAreConservativeAndPositive(): void
    {
        $limits = SecurityLimits::default();

        self::assertSame(64, $limits->maxAttributeCount);
        self::assertSame(256, $limits->maxAttributeKeyBytes);
        self::assertSame(4096, $limits->maxAttributeValueBytes);
        self::assertSame(8, $limits->maxAttributeDepth);
        self::assertSame(65536, $limits->maxOccurrencePayloadBytes);
        self::assertSame(262144, $limits->maxSnapshotPayloadBytes);
        self::assertSame(64, $limits->maxActivityDepth);
        self::assertSame(10000, $limits->maxArtifactsPerExecution);
        self::assertSame(64, $limits->maxSerializationDepth);
        self::assertSame(1048576, $limits->maxSerializationBytes);
    }

    public function testWithReturnsCopyWithSingleChangedLimit(): void
    {
        $limits = SecurityLimits::default()->withMaxAttributeCount(4);

        self::assertSame(4, $limits->maxAttributeCount);
        self::assertSame(SecurityLimits::default()->maxAttributeValueBytes, $limits->maxAttributeValueBytes);
    }

    public function testNegativeOrZeroLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SecurityLimits(maxAttributeCount: 0);
    }
}

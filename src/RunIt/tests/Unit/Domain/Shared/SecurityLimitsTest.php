<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\SecurityLimitsInterface;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Runtime\PropertyHook;

/**
 * @group domain
 * @group security
 */
#[CoversClass(SecurityLimits::class)]
final class SecurityLimitsTest extends Unit
{
    public function testDefaultsAreBoundedAndPositive(): void
    {
        $limits = new SecurityLimits();

        self::assertSame(
            [64, 256, 4096, 8, 65536, 262144, 64, 10000, 64, 1048576, 10000],
            [
                $limits->maxAttributeCount,
                $limits->maxAttributeKeyBytes,
                $limits->maxAttributeValueBytes,
                $limits->maxAttributeDepth,
                $limits->maxOccurrencePayloadBytes,
                $limits->maxSnapshotPayloadBytes,
                $limits->maxActivityDepth,
                $limits->maxArtifactsPerExecution,
                $limits->maxSerializationDepth,
                $limits->maxSerializationBytes,
                $limits->maxTrackedExecutions,
            ],
        );
    }

    public function testWithersReturnIndependentInstancesWithTheValueApplied(): void
    {
        $base = new SecurityLimits();

        $modified = $base
            ->withMaxAttributeCount(7)
            ->withMaxAttributeKeyBytes(11)
            ->withMaxAttributeValueBytes(13)
            ->withMaxAttributeDepth(17)
            ->withMaxOccurrencePayloadBytes(19)
            ->withMaxSnapshotPayloadBytes(23)
            ->withMaxActivityDepth(29)
            ->withMaxArtifactsPerExecution(31)
            ->withMaxSerializationDepth(37)
            ->withMaxSerializationBytes(41)
            ->withMaxTrackedExecutions(43)
        ;

        self::assertNotSame($base, $modified);
        self::assertSame(7, $modified->maxAttributeCount);
        self::assertSame(11, $modified->maxAttributeKeyBytes);
        self::assertSame(13, $modified->maxAttributeValueBytes);
        self::assertSame(17, $modified->maxAttributeDepth);
        self::assertSame(19, $modified->maxOccurrencePayloadBytes);
        self::assertSame(23, $modified->maxSnapshotPayloadBytes);
        self::assertSame(29, $modified->maxActivityDepth);
        self::assertSame(31, $modified->maxArtifactsPerExecution);
        self::assertSame(37, $modified->maxSerializationDepth);
        self::assertSame(41, $modified->maxSerializationBytes);
        self::assertSame(43, $modified->maxTrackedExecutions);

        self::assertSame(64, $base->maxAttributeCount, 'the base instance stays untouched');
    }

    public function testValidatorUsesLimitsFromAnotherImplementation(): void
    {
        $limits = self::createStub(SecurityLimitsInterface::class);
        $limits->method(PropertyHook::get('maxAttributeKeyBytes'))->willReturn(3);
        $validator = new ContextValueValidator($limits);

        $validator->assertValidKey('abc');
        $this->expectException(InvalidContextValueException::class);
        $validator->assertValidKey('abcd');
    }
}

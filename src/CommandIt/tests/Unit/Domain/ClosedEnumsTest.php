<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Codeception\Test\Unit;
use ItPack\CommandIt\Adapter\Reporting\OverflowPolicy;
use ItPack\CommandIt\Contract\Domain\Classification;
use ItPack\CommandIt\Domain\CommandResultStatus;
use ItPack\CommandIt\Domain\InputKind;

/**
 * @covers \ItPack\CommandIt\Domain\CommandResultStatus
 * @covers \ItPack\CommandIt\Domain\InputKind
 * @covers \ItPack\CommandIt\Contract\Domain\Classification
 * @covers \ItPack\CommandIt\Adapter\Reporting\OverflowPolicy
 */
final class ClosedEnumsTest extends Unit
{
    public function testCommandResultStatusLabels(): void
    {
        self::assertSame('succeeded', CommandResultStatus::Succeeded->label());
        self::assertSame('failed', CommandResultStatus::Failed->label());
    }

    public function testInputKindLabels(): void
    {
        self::assertSame('argument', InputKind::Argument->label());
        self::assertSame('option', InputKind::Option->label());
    }

    public function testStatusesAreClosedSets(): void
    {
        self::assertSame(
            ['succeeded', 'failed'],
            array_map(static fn (CommandResultStatus $s): string => $s->value, CommandResultStatus::cases()),
        );
    }

    public function testClassificationLabels(): void
    {
        self::assertSame('public', Classification::Public->label());
        self::assertSame('secret', Classification::Secret->label());
        self::assertTrue(Classification::Secret->isSecret());
        self::assertFalse(Classification::Public->isSecret());
    }

    public function testOverflowPolicyLabels(): void
    {
        self::assertSame('drop_oldest', OverflowPolicy::DropOldest->label());
        self::assertSame('drop_newest', OverflowPolicy::DropNewest->label());
        self::assertSame('throw', OverflowPolicy::Throw->label());
    }
}

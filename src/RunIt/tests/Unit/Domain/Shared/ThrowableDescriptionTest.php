<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;

final class ThrowableDescriptionTest extends Unit
{
    public function testCreateExposesCapturedPrimitiveValues(): void
    {
        $previous = ThrowableDescription::create('Inner\\Failure', 'boom', 7, null, null);

        $description = ThrowableDescription::create(
            'Outer\\Failure',
            'wrapped',
            3,
            $previous,
            'stack trace line',
        );

        self::assertInstanceOf(ThrowableDescriptionInterface::class, $description);
        self::assertSame('Outer\\Failure', $description->class());
        self::assertSame('wrapped', $description->message());
        self::assertSame(3, $description->code());
        self::assertSame('stack trace line', $description->trace());
        self::assertNotNull($description->previous());
        self::assertSame('Inner\\Failure', $description->previous()->class());
        self::assertSame(7, $description->previous()->code());
    }

    public function testAbsentPreviousAndTraceAreNull(): void
    {
        $description = ThrowableDescription::create('RuntimeException', 'x', 0, null, null);

        self::assertNull($description->previous());
        self::assertNull($description->trace());
    }

    public function testToArrayIsDeterministicAndRecursivelyPrimitive(): void
    {
        $previous = ThrowableDescription::create('Inner', 'i', 1, null, null);
        $description = ThrowableDescription::create('Outer', 'o', 2, $previous, 'trace');

        $expected = [
            'class' => 'Outer',
            'message' => 'o',
            'code' => 2,
            'previous' => [
                'class' => 'Inner',
                'message' => 'i',
                'code' => 1,
                'previous' => null,
                'trace' => null,
            ],
            'trace' => 'trace',
        ];

        self::assertSame($expected, $description->toArray());
        self::assertSame($description->toArray(), $description->toArray());
    }
}

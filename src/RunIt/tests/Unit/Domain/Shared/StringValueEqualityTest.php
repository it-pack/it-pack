<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Common\Contract\StringableInterface;
use ItPack\RunIt\Domain\Shared\StringValueEquality;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringValueEquality::class)]
final class StringValueEqualityTest extends Unit
{
    public function testEqualsSameValueIsTrue(): void
    {
        $subject = new class () implements StringableInterface {
            public function toString(): string
            {
                return 'value';
            }

            public function __toString(): string
            {
                return 'value';
            }
        };

        self::assertTrue(StringValueEquality::equals($subject, $subject));
    }

    public function testEqualsDifferentClassIsFalse(): void
    {
        $subject = new class () implements StringableInterface {
            public function toString(): string
            {
                return 'value';
            }

            public function __toString(): string
            {
                return 'value';
            }
        };
        $other = new class () implements StringableInterface {
            public function toString(): string
            {
                return 'value';
            }

            public function __toString(): string
            {
                return 'value';
            }
        };

        self::assertFalse(StringValueEquality::equals($subject, $other));
    }

    public function testEqualsDifferentValueIsFalse(): void
    {
        $subject = new class (
            'value'
        ) implements StringableInterface {
            public function __construct(
                public string $value
            ) {
            }

            public function toString(): string
            {
                return $this->value;
            }

            public function __toString(): string
            {
                return $this->toString();
            }
        };


        $other = clone $subject;
        $other->value = 'other';

        self::assertFalse(StringValueEquality::equals($subject, $other));
    }
}

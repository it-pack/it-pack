<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Behavior\Domain\BoundedKeyGuard;
use ItPack\RunIt\Behavior\Domain\StringValueEquality;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Domain\Shared\AttributeKey;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(AttributeKey::class)]
#[UsesClass(AttributeValidator::class)]
#[UsesClass(BoundedKeyGuard::class)]
#[UsesClass(StringValueEquality::class)]
#[UsesClass(InvalidAttributeException::class)]
#[UsesClass(SecurityLimits::class)]
final class AttributeKeyTest extends Unit
{
    public function testFromStringProjectsAndRendersCanonically(): void
    {
        $key = AttributeKey::fromString('http.status');

        self::assertSame('http.status', $key->toString());
        self::assertSame('http.status', (string) $key);
    }

    public function testDottedNamespacingIsAccepted(): void
    {
        self::assertSame('a.b-c_d', AttributeKey::fromString('a.b-c_d')->toString());
    }

    public function testEqualsIsTypeAwareAndValueBased(): void
    {
        $left = AttributeKey::fromString('http.status');
        $right = AttributeKey::fromString('http.status');
        $other = AttributeKey::fromString('http.method');

        self::assertTrue($left->equals($right));
        self::assertFalse($left->equals($other));
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        AttributeKey::fromString('');
    }

    public function testOversizedKeyIsRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        // @phpstan-ignore argument.type (Deliberate oversized value to assert rejection.)
        AttributeKey::fromString(str_repeat('a', 257));
    }

    public function testSymbolsInKeyAreRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        AttributeKey::fromString('bad key!');
    }
}

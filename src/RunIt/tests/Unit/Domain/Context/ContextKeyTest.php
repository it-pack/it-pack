<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\BoundedKeyGuard;
use ItPack\RunIt\Behavior\Domain\StringValueEquality;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ContextKey::class)]
#[UsesClass(BoundedKeyGuard::class)]
#[UsesClass(StringValueEquality::class)]
#[UsesClass(InvalidContextValueException::class)]
#[UsesClass(ContextValueValidator::class)]
#[UsesClass(SecurityLimits::class)]
final class ContextKeyTest extends Unit
{
    public function testFromStringProjectsAndRendersCanonically(): void
    {
        $key = ContextKey::fromString('tenant.id');

        self::assertSame('tenant.id', $key->toString());
        self::assertSame('tenant.id', (string) $key);
    }

    public function testDottedNamespacingIsAccepted(): void
    {
        self::assertSame('a.b-c_d', ContextKey::fromString('a.b-c_d')->toString());
    }

    public function testEqualsIsTypeAwareAndValueBased(): void
    {
        $left = ContextKey::fromString('tenant.id');
        $right = ContextKey::fromString('tenant.id');

        self::assertTrue($left->equals($right));
        self::assertFalse($left->equals(ContextKey::fromString('tenant.name')));
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ContextKey::fromString('');
    }

    public function testOversizedKeyIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        // @phpstan-ignore argument.type (Deliberate oversized value to assert rejection.)
        ContextKey::fromString(str_repeat('a', 257));
    }

    public function testSymbolsInKeyAreRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        ContextKey::fromString('bad key!');
    }
}

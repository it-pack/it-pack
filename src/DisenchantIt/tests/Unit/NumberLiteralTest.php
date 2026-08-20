<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;

final class NumberLiteralTest extends Unit
{
    public function testIntCarriesParsedValueAndExactRawToken(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '31',
            raw: '0x1F',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame('int', $lit->kind);
        $this->assertSame('31', $lit->value);
        $this->assertSame('0x1F', $lit->raw);
        // needle() for numbers is the exact raw token, not the parsed scalar.
        $this->assertSame('0x1F', $lit->needle());
    }

    public function testFloatNeedleUsesRaw(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Float->value,
            value: '3.14',
            raw: '3.14',
            line: 2,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame('float', $lit->kind);
        $this->assertSame('3.14', $lit->needle());
    }
}

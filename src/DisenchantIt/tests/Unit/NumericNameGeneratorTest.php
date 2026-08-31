<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Naming\NumericNameGenerator;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\NumberLiteral;
use ItPack\DisenchantIt\ValueObject\Source\NamingContext;

final class NumericNameGeneratorTest extends Unit
{
    private NumericNameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = NumericNameGenerator::create();
    }

    private function lit(NamingContext $ctx): NumberLiteral
    {
        return new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '30',
            raw: '30',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
            namingContext: $ctx,
        );
    }

    public function testUnitSuffixProducesBasePlusUnit(): void
    {
        $ctx = new NamingContext(variableName: 'timeoutMs', unit: 'ms');

        $this->assertSame('TIMEOUT_MS', $this->generator->generate($this->lit($ctx), $ctx));
    }

    public function testSnakeVariableWithUnitSuffix(): void
    {
        $ctx = new NamingContext(variableName: 'retry_count', unit: 'count');

        $this->assertSame('RETRY_COUNT', $this->generator->generate($this->lit($ctx), $ctx));
    }

    public function testCommentProducesVariablePlusComment(): void
    {
        $ctx = new NamingContext(variableName: 't', comment: 'seconds');

        $this->assertSame('T_SECONDS', $this->generator->generate($this->lit($ctx), $ctx));
    }

    public function testCommentWithoutVariableUsesCommentOnly(): void
    {
        $ctx = new NamingContext(comment: 'max retries');

        $this->assertSame('MAX_RETRIES', $this->generator->generate($this->lit($ctx), $ctx));
    }

    public function testArrayKeyNameOnly(): void
    {
        $ctx = new NamingContext(variableName: 'timeout');

        $this->assertSame('TIMEOUT', $this->generator->generate($this->lit($ctx), $ctx));
    }

    public function testNoHintReturnsNull(): void
    {
        $ctx = new NamingContext();

        $this->assertNull($this->generator->generate($this->lit($ctx), $ctx));
    }
}

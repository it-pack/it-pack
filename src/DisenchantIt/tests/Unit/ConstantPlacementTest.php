<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\LiteralKind;

final class ConstantPlacementTest extends Unit
{
    private function placement(string $type): ConstantPlacement
    {
        return new ConstantPlacement('C', 'FOO', 'x', $type, []);
    }

    public function testTypeKeywordForString(): void
    {
        $this->assertSame('string', $this->placement(LiteralKind::String->value)->typeKeyword());
    }

    public function testTypeKeywordForInt(): void
    {
        $this->assertSame('int', $this->placement(LiteralKind::Int->value)->typeKeyword());
    }

    public function testTypeKeywordForFloat(): void
    {
        $this->assertSame('float', $this->placement(LiteralKind::Float->value)->typeKeyword());
    }

    public function testCarriesPlacementFields(): void
    {
        $p = new ConstantPlacement('C', 'FOO', 'val', LiteralKind::String->value, []);

        $this->assertSame('C', $p->className);
        $this->assertSame('FOO', $p->constantName);
        $this->assertSame('val', $p->value);
    }
}

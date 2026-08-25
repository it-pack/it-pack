<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\SemConvAliasRewriter;
use ItPack\DisenchantIt\ValueObject\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

final class SemConvAliasRewriterTest extends Unit
{
    private function rewriter(): SemConvAliasRewriter
    {
        return new SemConvAliasRewriter(new SemConvConfig());
    }

    private function placement(string $name, string $className = 'C'): ConstantPlacement
    {
        return new ConstantPlacement($className, $name, 'x', LiteralKind::String->value, []);
    }

    public function testEmptyPlacementsReturnsCodeUnchanged(): void
    {
        $code = "<?php\nclass C {}\n";

        $this->assertSame($code, $this->rewriter()->rewrite($code, []));
    }

    public function testRewritesDeclarationToAliasAndAddsUse(): void
    {
        $code = "<?php\nnamespace App;\n\nclass C {\n    private const string FOO = 'x';\n}\n";
        $result = $this->rewriter()->rewrite($code, [$this->placement('FOO')]);

        $this->assertStringContainsString('private const string FOO = SemConv::FOO;', $result);
        $this->assertStringContainsString('use Generated\\SemConv;', $result);
    }

    public function testAlreadyAliasedIsIdempotent(): void
    {
        $code = "<?php\nnamespace App;\n\nuse Generated\\SemConv;\n\n"
            . "class C {\n    private const string FOO = SemConv::FOO;\n}\n";

        $this->assertSame($code, $this->rewriter()->rewrite($code, [$this->placement('FOO')]));
    }

    public function testPlacementNotInCodeLeavesCodeUnchanged(): void
    {
        $code = "<?php\nclass C {\n    private const string BAR = 'y';\n}\n";

        $this->assertSame($code, $this->rewriter()->rewrite($code, [$this->placement('FOO')]));
    }
}

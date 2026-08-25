<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\LineBasedRewriter;
use ItPack\DisenchantIt\ValueObject\Insertion;
use ItPack\DisenchantIt\ValueObject\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Replacement;
use ItPack\DisenchantIt\ValueObject\TargetKind;

final class LineBasedRewriterTest extends Unit
{
    private function artifact(array $insertions = [], array $replacements = []): PromotionArtifact
    {
        return new PromotionArtifact($insertions, $replacements, TargetKind::Const);
    }

    public function testEmptyItemsReturnsCodeUnchanged(): void
    {
        $code = "<?php\nclass C\n{\n}";
        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, []));
    }

    public function testDryRunReturnsCodeUnchanged(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        \$x = 'admin';\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(7, "'admin'", 'self::FOO')],
        );

        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, [$artifact], true));
    }

    public function testSingleLineReplacement(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        \$x = 'admin';\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(6, "'admin'", 'self::FOO')],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('$x = self::FOO;', $result);
        $this->assertStringNotContainsString("'admin'", $result);
    }

    public function testSingleLineReplacementNeedleNotFoundIsSkipped(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        \$x = 'admin';\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(7, "'absent'", 'self::FOO')],
        );

        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, [$artifact]));
    }

    public function testStaleSingleLineIsSkipped(): void
    {
        $code = "<?php\nclass C\n{\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(999, "'x'", 'self::FOO')],
        );

        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, [$artifact]));
    }

    public function testMultiLineSpanReplacement(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        // start\n        \$x = 'aaa'\n        . 'bbb';\n        // end\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(7, "'aaa'", 'self::FOO', 8)],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('$x = self::FOO', $result);
        $this->assertStringContainsString(". 'bbb';", $result);
        $this->assertStringNotContainsString("'aaa'", $result);
    }

    public function testMultiLineSpanNeedleNotFoundFallsBackToReplacement(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        // start\n        \$x = 'aaa'\n        . 'bbb';\n        // end\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(7, "'zzz'", 'self::FOO', 8)],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('self::FOO', $result);
        $this->assertStringNotContainsString("'aaa'", $result);
        $this->assertStringNotContainsString(". 'bbb';", $result);
    }

    public function testStaleSpanIsSkipped(): void
    {
        $code = "<?php\nclass C\n{\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(7, "'aaa'", 'self::FOO', 999)],
        );

        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, [$artifact]));
    }

    public function testClassBodyInsertion(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void {}\n}";
        $artifact = $this->artifact(
            [new Insertion('C', ["    private const string FOO = 'admin';"])],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('private const string FOO =', $result);
        $this->assertLessThan(
            strpos($result, 'public function f'),
            strpos($result, 'private const string FOO'),
        );
    }

    public function testDuplicateConstIsNotReDeclared(): void
    {
        $code = "<?php\nclass C\n{\n    private const string FOO = 'admin';\n}";
        $artifact = $this->artifact(
            [new Insertion('C', ["    private const string FOO = 'admin';"])],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertSame(1, substr_count($result, 'private const string FOO'));
    }

    public function testTopLevelEnumInsertion(): void
    {
        $code = "<?php\nclass C\n{\n}";
        $artifact = $this->artifact(
            [new Insertion('C', [
                'enum Status : string',
                '{',
                "    case ACTIVE = 'active';",
                '}',
            ], true)],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('enum Status : string', $result);
        $this->assertGreaterThan(strpos($result, 'class C'), strpos($result, 'enum Status'));
    }

    public function testExistingTopLevelEnumIsNotDuplicated(): void
    {
        $code = "<?php\nclass C\n{\n}\nenum Status : string\n{\n    case ACTIVE = 'active';\n}";
        $artifact = $this->artifact(
            [new Insertion('C', [
                'enum Status : string',
                '{',
                "    case ACTIVE = 'active';",
                '}',
            ], true)],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertSame(1, substr_count($result, 'enum Status : string'));
    }

    public function testMultiClassInsertionIsBottomUp(): void
    {
        $code = "<?php\nclass A\n{\n}\nclass B\n{\n}\n";
        $artifact = $this->artifact([
            new Insertion('A', ["    private const string A_FOO = 'a';"]),
            new Insertion('B', ["    private const string B_FOO = 'b';"]),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString("class A\n{\n    private const string A_FOO", $result);
        $this->assertStringContainsString("class B\n{\n    private const string B_FOO", $result);
    }

    public function testInsertionForUnknownClassIsSkipped(): void
    {
        $code = "<?php\nclass C\n{\n}";
        $artifact = $this->artifact(
            [new Insertion('Missing', ["    private const string FOO = 'x';"])],
        );

        $this->assertSame($code, new LineBasedRewriter()->rewrite($code, [$artifact]));
    }
}

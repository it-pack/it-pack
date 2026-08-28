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
        // 'admin' lives on line 6; the replacement WOULD apply without dry-run,
        // so this guards the early `if ($dryRun) return` branch meaningfully.
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        \$x = 'admin';\n    }\n}";
        $artifact = $this->artifact(
            [],
            [new Replacement(6, "'admin'", 'self::FOO')],
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

    /**
     * A stale (out-of-range) replacement must be skipped WITHOUT aborting the
     * remaining valid replacements. Guards the `continue` in the Step-1 loop
     * (a `break` mutant would drop every later replacement).
     */
    public function testStaleReplacementDoesNotSkipLaterReplacements(): void
    {
        $code = "<?php\nclass C\n{\n    public function f(): void\n    {\n        \$x = 'admin';\n        \$y = 'user';\n    }\n}";
        $artifact = $this->artifact(
            [],
            [
                new Replacement(999, "'absent'", 'self::FOO'), // stale line
                new Replacement(6, "'admin'", 'self::FOO'),    // valid line
            ],
        );

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('self::FOO', $result);
        $this->assertStringNotContainsString("'admin'", $result);
    }

    /**
     * When one class is absent from the file, its body-insertion `continue`
     * must not abort insertions planned for a present class. A `break` mutant
     * would drop the present class's const entirely.
     */
    public function testMissingClassBodyInsertionDoesNotAbortOthers(): void
    {
        $code = "<?php\nclass A\n{\n}";
        $artifact = $this->artifact([
            new Insertion('Ghost', ["    private const string GHOST = 'g';"]),
            new Insertion('A', ["    private const string A_FOO = 'a';"]),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('private const string A_FOO', $result);
        $this->assertStringNotContainsString('GHOST', $result);
    }

    /**
     * Same guard as above, but for top-level enum insertions: a missing class
     * must not abort a present class's enum emission.
     */
    public function testMissingClassTopLevelInsertionDoesNotAbortOthers(): void
    {
        $code = "<?php\nclass A\n{\n}";
        $artifact = $this->artifact([
            new Insertion('Ghost', ['enum Ghost : string', '{', '}'], true),
            new Insertion('A', ['enum AStatus : string', '{', '}'], true),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('enum AStatus : string', $result);
        $this->assertStringNotContainsString('enum Ghost', $result);
    }

    /**
     * A top-level enum that already exists must be skipped (the `continue` at
     * the enum-already-exists guard) WITHOUT aborting insertion of a different
     * enum for the same class. A `break` mutant would drop the second enum.
     */
    public function testExistingTopLevelEnumDoesNotAbortOtherEnum(): void
    {
        // Two classes so each enum lives in its own `$insertionsByClass` group:
        // A's enum already exists (continue @ the enum-already-exists guard),
        // B's enum is new and must still be emitted. A `break` mutant would drop
        // B's enum.
        $code = "<?php\nclass A\n{\n}\nclass B\n{\n}\nenum Status : string\n{\n    case ACTIVE = 'active';\n}";
        $artifact = $this->artifact([
            new Insertion('A', ['enum Status : string', '{', "    case ACTIVE = 'active';", '}'], true),
            new Insertion('B', ['enum Other : string', '{', "    case ON = 'on';", '}'], true),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('enum Other : string', $result);
        $this->assertSame(1, substr_count($result, 'enum Status : string'));
    }

    /**
     * Within one class, a const already declared must be skipped (the inner
     * `continue` in the per-insertion loop) WITHOUT dropping a subsequent new
     * const. A `break` mutant would omit the new const.
     */
    public function testDuplicateConstDoesNotAbortOtherConst(): void
    {
        $code = "<?php\nclass A\n{\n    private const string FOO = 'a';\n}";
        $artifact = $this->artifact([
            new Insertion('A', [
                "    private const string FOO = 'a';",
                "    private const string BAR = 'b';",
            ]),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('private const string BAR', $result);
        $this->assertSame(1, substr_count($result, 'private const string FOO'));
    }

    /**
     * When a class contributes no new consts (all already declared → empty
     * declaration lines → `continue`), a following class's insertion must
     * still apply. A `break` mutant would abort the second class.
     */
    public function testFullyDuplicateClassDoesNotAbortOtherClass(): void
    {
        $code = "<?php\nclass A\n{\n    private const string FOO = 'a';\n}\nclass B\n{\n}";
        $artifact = $this->artifact([
            new Insertion('A', ["    private const string FOO = 'a';"]),
            new Insertion('B', ["    private const string BAR = 'b';"]),
        ]);

        $result = new LineBasedRewriter()->rewrite($code, [$artifact]);

        $this->assertStringContainsString('private const string BAR', $result);
    }
}

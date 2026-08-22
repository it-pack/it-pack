<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\FlagSetTarget;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\Factory\PromotionTargetFactory;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\NamingContext;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;
use ItPack\DisenchantIt\ValueObject\TargetKind;

final class FlagSetTargetTest extends Unit
{
    private function flagOccurrence(int|string $value, int $line, ?string $variable = 'mode'): NumberLiteral
    {
        return new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: (string) $value,
            raw: (string) $value,
            line: $line,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'f',
            namingContext: $variable === null ? null : new NamingContext(variableName: $variable),
            subkind: LiteralSubkind::Bitmask,
        );
    }

    private function flagCandidate(NumberLiteral ...$occurrences): Candidate
    {
        return new Candidate((string) $occurrences[0]->value, 'FLAG', $occurrences);
    }

    private function flagGroup(NumberLiteral ...$occurrences): GroupedCandidate
    {
        return new GroupedCandidate($this->flagCandidate(...$occurrences));
    }

    public function testSupportsTrueForBitmaskPowersOfTwoSharedVariable(): void
    {
        $target = new FlagSetTarget();

        $this->assertTrue($target->supports($this->flagGroup(
            $this->flagOccurrence(1, 5),
            $this->flagOccurrence(2, 6),
        )));
    }

    public function testSupportsFalseWhenNotBitmask(): void
    {
        $lit = $this->flagOccurrence(1, 5);
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '1',
            raw: '1',
            line: 5,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'f',
            namingContext: new NamingContext(variableName: 'mode'),
            subkind: LiteralSubkind::Unknown,
        );

        $this->assertFalse(new FlagSetTarget()->supports($this->flagGroup($lit, $this->flagOccurrence(2, 6))));
    }

    public function testSupportsFalseWhenNotPowerOfTwo(): void
    {
        $this->assertFalse(new FlagSetTarget()->supports($this->flagGroup(
            $this->flagOccurrence(3, 5),
            $this->flagOccurrence(2, 6),
        )));
    }

    public function testSupportsFalseWhenDifferentVariables(): void
    {
        $this->assertFalse(new FlagSetTarget()->supports($this->flagGroup(
            $this->flagOccurrence(1, 5, 'a'),
            $this->flagOccurrence(2, 6, 'b'),
        )));
    }

    public function testSupportsFalseWhenSingleOccurrence(): void
    {
        $this->assertFalse(new FlagSetTarget()->supports($this->flagGroup(
            $this->flagOccurrence(1, 5),
        )));
    }

    public function testBuildEmitsFlagConstsAndSelfReferences(): void
    {
        $target = new FlagSetTarget();
        $artifact = $target->build($this->flagGroup(
            $this->flagOccurrence(1, 5),
            $this->flagOccurrence(2, 6),
        ));

        $this->assertSame(TargetKind::FlagSet, $artifact->kind);
        $this->assertSame([], $artifact->placements, 'flag sets are not collected into SemConv');

        $insertion = $artifact->insertions[0];
        $this->assertFalse($insertion->topLevel, 'flags are class-level consts');
        $this->assertSame('C', $insertion->className);

        $source = implode("\n", $insertion->lines);
        $this->assertStringContainsString('public const int Mode1 = 1;', $source);
        $this->assertStringContainsString('public const int Mode2 = 2;', $source);

        $byNeedle = [];
        foreach ($artifact->replacements as $r) {
            $byNeedle[$r->needle] = $r->replacement;
        }
        $this->assertSame('self::Mode1', $byNeedle['1']);
        $this->assertSame('self::Mode2', $byNeedle['2']);
    }

    public function testBuildPreservesCombinedBitwiseExpression(): void
    {
        $target = new FlagSetTarget();
        // Both flags on the same line as `1 | 2`.
        $artifact = $target->build($this->flagGroup(
            $this->flagOccurrence(1, 5),
            $this->flagOccurrence(2, 5),
        ));

        $source = implode("\n", $artifact->insertions[0]->lines);
        $this->assertStringContainsString('public const int Mode1 = 1;', $source);
        $this->assertStringContainsString('public const int Mode2 = 2;', $source);

        // Each literal is replaced independently; the `|` stays, yielding
        // `self::Mode1 | self::Mode2` after the rewriter applies both edits.
        $needles = array_map(static fn ($r) => $r->needle, $artifact->replacements);
        $this->assertSame(['1', '2'], $needles);
    }

    public function testCompositePrefersFlagSetOverClassConstant(): void
    {
        $composite = PromotionTargetFactory::create()->build();

        $group = $this->flagGroup(
            $this->flagOccurrence(1, 5),
            $this->flagOccurrence(2, 6),
        );

        $this->assertTrue($composite->supports($group));
        $this->assertSame(TargetKind::FlagSet, $composite->build($group)->kind);
    }

    public function testEndToEndRewritePromotesFlagsAndIsIdempotent(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php

            declare(strict_types=1);

            final class Widget
            {
                public function permissions(): int
                {
                    $mode = 2 | 4 | 8;

                    return $mode;
                }
            }

            PHP;

        $rewritten = $disenchanter->rewrite($code);

        $this->assertStringContainsString('public const int Mode2 = 2;', $rewritten);
        $this->assertStringContainsString('public const int Mode4 = 4;', $rewritten);
        $this->assertStringContainsString('public const int Mode8 = 8;', $rewritten);
        $this->assertStringContainsString('$mode = self::Mode2 | self::Mode4 | self::Mode8;', $rewritten);

        // Second pass must not change the already-rewritten source.
        $this->assertSame($rewritten, $disenchanter->rewrite($rewritten));
    }
}

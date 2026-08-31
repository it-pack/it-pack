<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\Candidate\Candidate;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\NumberLiteral;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Rewrite\Insertion;
use ItPack\DisenchantIt\ValueObject\Rewrite\Replacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

final class PromotionArtifactTest extends Unit
{
    private function literal(int $line, string $value): NumberLiteral
    {
        return new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: $value,
            raw: (string) $value,
            line: $line,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'f',
        );
    }

    public function testCarriesInsertionsReplacementsAndKind(): void
    {
        $candidate = new Candidate('1', 'ONE', [$this->literal(5, '1'), $this->literal(8, '1')]);
        $this->assertNotNull($candidate);

        $insertion = new Insertion('C', ['    private const int ONE = 1;'], false);
        $replacement = new Replacement(5, '1', 'self::ONE');

        $artifact = new PromotionArtifact(
            [$insertion],
            [$replacement],
            TargetKind::Const,
        );

        $this->assertCount(1, $artifact->insertions);
        $this->assertCount(1, $artifact->replacements);
        $this->assertSame(TargetKind::Const, $artifact->kind);
        $this->assertSame([], $artifact->placements);
        $this->assertSame('C', $artifact->insertions[0]->className);
        $this->assertFalse($artifact->insertions[0]->topLevel);
        $this->assertSame('self::ONE', $artifact->replacements[0]->replacement);
    }

    public function testDefaultsToNoPlacements(): void
    {
        $artifact = new PromotionArtifact([], [], TargetKind::Enum);
        $this->assertSame([], $artifact->placements);
    }
}

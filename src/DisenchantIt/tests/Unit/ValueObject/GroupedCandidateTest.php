<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;

final class GroupedCandidateTest extends Unit
{
    private function candidate(): Candidate
    {
        return new Candidate('1', 'ONE', [
            new NumberLiteral(
                kind: LiteralKind::Int->value,
                value: '1',
                raw: '1',
                line: 5,
                role: LiteralRole::NumericExpression,
                className: 'C',
                methodName: 'f',
            ),
        ]);
    }

    public function testDefaultsAreNotClosed(): void
    {
        $candidate = $this->candidate();
        $group = new GroupedCandidate($candidate);

        $this->assertFalse($group->isClosedSet);
        $this->assertNull($group->proposedEnumName);
        $this->assertSame($candidate, $group->candidate);
    }

    public function testClosedSetCarriesProposedName(): void
    {
        $group = new GroupedCandidate($this->candidate(), true, 'Status');

        $this->assertTrue($group->isClosedSet);
        $this->assertSame('Status', $group->proposedEnumName);
    }
}

<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\ByValueAndContextGroupingStrategy;
use ItPack\DisenchantIt\Adapter\ByValueGroupingStrategy;
use ItPack\DisenchantIt\Adapter\CandidateGrouper;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

final class CandidateGrouperTest extends Unit
{
    private CandidateGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new CandidateGrouper(
            new ByValueGroupingStrategy(),
            new ByValueAndContextGroupingStrategy(),
        );
    }

    private function literal(string $value, int $line): StringLiteral
    {
        return new StringLiteral($value, $line, LiteralRole::Unclassified, 'C', 'm');
    }

    public function testGroupsByValuePreservingOrder(): void
    {
        $candidates = [
            $this->literal('a', 10),
            $this->literal('b', 11),
            $this->literal('a', 20),
        ];

        $groups = $this->grouper->groupByValue($candidates);

        $this->assertSame(['a', 'b'], array_keys($groups));
        $this->assertCount(2, $groups['a']);
        $this->assertSame([10, 20], array_map(static fn (StringLiteralInterface $l) => $l->line, $groups['a']));
    }

    public function testEmptyInputYieldsEmptyGroups(): void
    {
        $this->assertSame([], $this->grouper->groupByValue([]));
    }
}

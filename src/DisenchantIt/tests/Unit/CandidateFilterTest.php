<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\CandidateFilter;
use ItPack\DisenchantIt\RoleClassifier;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

final class CandidateFilterTest extends Unit
{
    private CandidateFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new CandidateFilter(new RoleClassifier());
    }

    private function literal(string $value, string $role): StringLiteral
    {
        return new StringLiteral($value, 1, $role, 'C', 'm');
    }

    public function testKeepsIdentifierShapedUnclassifiedLiteral(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('admin-user', 'unclassified')]);

        $this->assertCount(1, $kept);
    }

    public function testDropsExceptionMessage(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('boom', 'exception_message')]);

        $this->assertCount(0, $kept);
    }

    public function testDropsFormatString(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('%s', 'format_string')]);

        $this->assertCount(0, $kept);
    }

    public function testDropsEnumBackingValue(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('php', 'enum_backing_value')]);

        $this->assertCount(0, $kept);
    }

    public function testDropsSentenceShapedLiteral(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('Configuration key is empty.', 'unclassified')]);

        $this->assertCount(0, $kept, 'prose is not identifier-shaped');
    }

    public function testPreservesKeysAndDropsNoiseInOnePass(): void
    {
        $literals = [
            $this->literal('origin', 'array_key_literal'),
            $this->literal('boom', 'exception_message'),
            $this->literal('APP_KEY', 'unclassified'),
        ];

        $kept = $this->filter->filterCandidates($literals);

        $this->assertCount(2, $kept);
        $this->assertSame(['origin', 'APP_KEY'], array_map(static fn (StringLiteralInterface $l) => $l->value, $kept));
    }
}

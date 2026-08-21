<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\CandidateFilter;
use ItPack\DisenchantIt\Adapter\RoleClassifier;
use ItPack\DisenchantIt\Adapter\ScopeFilter;
use ItPack\DisenchantIt\ValueObject\DisenchantScope;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

final class CandidateFilterTest extends Unit
{
    private CandidateFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new CandidateFilter(new RoleClassifier(), new ScopeFilter(DisenchantScope::default()));
    }

    private function literal(string $value, LiteralRole $role): StringLiteral
    {
        return new StringLiteral($value, 1, $role, 'C', 'm');
    }

    public function testKeepsIdentifierShapedUnclassifiedLiteral(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('admin-user', LiteralRole::Unclassified)]);

        $this->assertCount(1, $kept);
    }

    public function testDropsExceptionMessage(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('boom', LiteralRole::ExceptionMessage)]);

        $this->assertCount(0, $kept);
    }

    public function testDropsFormatString(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('%s', LiteralRole::FormatString)]);

        $this->assertCount(0, $kept);
    }

    public function testDropsEnumBackingValue(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('php', LiteralRole::EnumBackingValue)]);

        $this->assertCount(0, $kept);
    }

    public function testDropsClassConstDeclarationValue(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('admin-user', LiteralRole::ClassConstValue)]);

        $this->assertCount(0, $kept, 'a class constant own declaration value is not a usage site');
    }

    public function testDropsSentenceShapedLiteral(): void
    {
        $kept = $this->filter->filterCandidates([$this->literal('Configuration key is empty.', LiteralRole::Unclassified)]);

        $this->assertCount(0, $kept, 'prose is not identifier-shaped');
    }

    public function testPreservesKeysAndDropsNoiseInOnePass(): void
    {
        $literals = [
            $this->literal('origin', LiteralRole::ArrayKeyLiteral),
            $this->literal('boom', LiteralRole::ExceptionMessage),
            $this->literal('APP_KEY', LiteralRole::Unclassified),
        ];

        $kept = $this->filter->filterCandidates($literals);

        $this->assertCount(2, $kept);
        $this->assertSame(['origin', 'APP_KEY'], array_map(static fn (StringLiteralInterface $l) => $l->value, $kept));
    }

    public function testScopeFilterDropsExcludedLiteralValue(): void
    {
        $filter = new CandidateFilter(
            new RoleClassifier(),
            new ScopeFilter(DisenchantScope::fromConfig(['excludeLiteralValues' => ['APP_KEY']])),
        );

        $kept = $filter->filterCandidates([
            $this->literal('origin', LiteralRole::ArrayKeyLiteral),
            $this->literal('APP_KEY', LiteralRole::Unclassified),
        ]);

        $this->assertCount(1, $kept);
        $this->assertSame(['origin'], array_map(static fn (StringLiteralInterface $l) => $l->value, $kept));
    }

    public function testScopeFilterDropsExcludedPath(): void
    {
        $filter = new CandidateFilter(
            new RoleClassifier(),
            new ScopeFilter(DisenchantScope::fromConfig(['excludePaths' => ['tests/**']])),
        );

        $kept = $filter->filterCandidates(
            [$this->literal('origin', LiteralRole::ArrayKeyLiteral)],
            'tests/seed.php',
        );

        $this->assertCount(0, $kept, 'literals inside an excluded path are dropped');
    }
}

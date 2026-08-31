<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Detection\ScopeFilter;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use ItPack\DisenchantIt\ValueObject\Literal\StringLiteral;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScope;

final class ScopeFilterTest extends Unit
{
    private function literal(string $value): StringLiteral
    {
        return new StringLiteral($value, 1, LiteralRole::Unclassified, 'C', 'm');
    }

    public function testIgnoreTestsRejectsTestDirs(): void
    {
        $filter = new ScopeFilter(DisenchantScope::fromConfig(['ignoreTests' => true]));

        $this->assertFalse($filter->isAllowed($this->literal('x'), 'tests/Foo.php'));
        $this->assertFalse($filter->isAllowed($this->literal('x'), 'fixtures/seed.php'));
        $this->assertFalse($filter->isAllowed($this->literal('x'), 'var/cache/x.php'));
        $this->assertTrue($filter->isAllowed($this->literal('x'), 'src/Foo.php'));
    }

    public function testIgnoreTestsDoesNotRejectWhenDisabled(): void
    {
        $filter = new ScopeFilter(DisenchantScope::fromConfig(['ignoreTests' => false]));

        $this->assertTrue($filter->isAllowed($this->literal('x'), 'tests/Foo.php'));
    }

    public function testExcludeLiteralValuesRejectsByValue(): void
    {
        $filter = new ScopeFilter(DisenchantScope::fromConfig(['excludeLiteralValues' => ['admin-user']]));

        $this->assertFalse($filter->isAllowed($this->literal('admin-user'), 'src/Foo.php'));
        $this->assertTrue($filter->isAllowed($this->literal('other'), 'src/Foo.php'));
    }

    public function testExcludePathsRejectsMatchingFile(): void
    {
        $filter = new ScopeFilter(DisenchantScope::fromConfig(['excludePaths' => ['tests/**']]));

        $this->assertFalse($filter->isAllowed($this->literal('x'), 'tests/Foo.php'));
        $this->assertTrue($filter->isAllowed($this->literal('x'), 'src/Foo.php'));
    }

    public function testIncludePathsIsAnAllowList(): void
    {
        $filter = new ScopeFilter(DisenchantScope::fromConfig(['includePaths' => ['src/**']]));

        $this->assertTrue($filter->isAllowed($this->literal('x'), 'src/Foo.php'));
        $this->assertFalse($filter->isAllowed($this->literal('x'), 'tests/Foo.php'));
    }
}

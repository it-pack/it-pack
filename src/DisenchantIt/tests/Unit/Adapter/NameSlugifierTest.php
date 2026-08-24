<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\NameSlugifier;

final class NameSlugifierTest extends Unit
{
    public function testSplitsOnNonAlphanumericRunsAndTitleCasesWords(): void
    {
        $slugifier = new NameSlugifier();

        $this->assertSame('CacheTtlSeconds', $slugifier->pascalCase('cache-ttl_seconds'));
        $this->assertSame('RoleAdmin', $slugifier->pascalCase('role:admin'));
        $this->assertSame('RoleUser', $slugifier->pascalCase('role-user'));
        $this->assertSame('New', $slugifier->pascalCase('new'));
        $this->assertSame('Mode', $slugifier->pascalCase('Mode'));
    }

    public function testGuardsLeadingDigit(): void
    {
        $slugifier = new NameSlugifier();

        $this->assertSame('V4xxMode', $slugifier->pascalCase('4xx_mode'));
        $this->assertSame('V0', $slugifier->pascalCase('0'));
    }

    public function testReturnsDefaultValueFallbackOnEmptyResult(): void
    {
        $slugifier = new NameSlugifier();

        $this->assertSame('Value', $slugifier->pascalCase('!!!'));
        $this->assertSame('Value', $slugifier->pascalCase('___'));
    }

    public function testHonorsCustomFallback(): void
    {
        $slugifier = new NameSlugifier();

        $this->assertSame('Flag', $slugifier->pascalCase('!!!', 'Flag'));
    }
}

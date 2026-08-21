<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\DisenchantScope;

final class DisenchantScopeTest extends Unit
{
    public function testEmptyConfigOptsNothingOut(): void
    {
        $scope = DisenchantScope::fromConfig();

        $this->assertSame([], $scope->includePaths);
        $this->assertSame([], $scope->excludePaths);
        $this->assertSame([], $scope->includeNamespaces);
        $this->assertSame([], $scope->excludeNamespaces);
        $this->assertSame([], $scope->excludeLiteralValues);
        $this->assertFalse($scope->ignoreTests);
    }

    public function testDefaultScopeIgnoresTests(): void
    {
        $scope = DisenchantScope::default();

        $this->assertTrue($scope->ignoreTests);
        $this->assertSame([], $scope->excludePaths);
    }

    public function testFromConfigMapsEveryKey(): void
    {
        $scope = DisenchantScope::fromConfig([
            'includePaths' => ['src/**'],
            'excludePaths' => ['tests/**'],
            'includeNamespaces' => ['App\\'],
            'excludeNamespaces' => ['Tests\\'],
            'excludeLiteralValues' => ['admin-user'],
            'ignoreTests' => true,
        ]);

        $this->assertSame(['src/**'], $scope->includePaths);
        $this->assertSame(['tests/**'], $scope->excludePaths);
        $this->assertSame(['App\\'], $scope->includeNamespaces);
        $this->assertSame(['Tests\\'], $scope->excludeNamespaces);
        $this->assertSame(['admin-user'], $scope->excludeLiteralValues);
        $this->assertTrue($scope->ignoreTests);
    }

    public function testInterfaceGettersMirrorProperties(): void
    {
        $scope = DisenchantScope::fromConfig(['excludeLiteralValues' => ['x'], 'ignoreTests' => true]);

        $this->assertSame(['x'], $scope->getExcludeLiteralValues());
        $this->assertTrue($scope->isIgnoreTests());
    }
}

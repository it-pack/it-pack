<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScope;

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

    public function testAllGettersReflectConfiguredValues(): void
    {
        $scope = DisenchantScope::fromConfig([
            'includePaths' => ['/a'],
            'excludePaths' => ['/b'],
            'includeNamespaces' => ['App'],
            'excludeNamespaces' => ['Vendor'],
            'excludeLiteralValues' => ['x'],
            'ignoreTests' => true,
            'maxHeredocBodyChars' => 2048,
            'convertFqcnToClassConst' => true,
        ]);

        $this->assertSame(['/a'], $scope->getIncludePaths());
        $this->assertSame(['/b'], $scope->getExcludePaths());
        $this->assertSame(['App'], $scope->getIncludeNamespaces());
        $this->assertSame(['Vendor'], $scope->getExcludeNamespaces());
        $this->assertSame(2048, $scope->getMaxHeredocBodyChars());
        $this->assertTrue($scope->isConvertFqcnToClassConst());
    }

    public function testStringListNonArrayFallsBackToEmpty(): void
    {
        $scope = DisenchantScope::fromConfig(['includePaths' => 'not-array']);

        $this->assertSame([], $scope->getIncludePaths());
    }

    public function testIntConfigNonNumericFallsBackToDefault(): void
    {
        $scope = DisenchantScope::fromConfig(['maxHeredocBodyChars' => 'abc']);

        $this->assertSame(4096, $scope->getMaxHeredocBodyChars());
    }

    public function testIntConfigNumericStringIsCast(): void
    {
        $scope = DisenchantScope::fromConfig(['maxHeredocBodyChars' => '1024']);

        $this->assertSame(1024, $scope->getMaxHeredocBodyChars());
    }

    public function testWithConvertFqcnToClassConstIsImmutable(): void
    {
        $scope = DisenchantScope::default();
        $next = $scope->withConvertFqcnToClassConst(true);

        $this->assertFalse($scope->isConvertFqcnToClassConst());
        $this->assertTrue($next->isConvertFqcnToClassConst());
    }
}

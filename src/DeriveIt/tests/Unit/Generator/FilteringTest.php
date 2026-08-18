<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\ConstructorFilter;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\MagicMethodFilter;
use ItPack\DeriveIt\Generator\MethodExtractor;
use ItPack\DeriveIt\Generator\StaticFilter;
use ItPack\DeriveIt\Generator\VisibilityFilter;
use ReflectionClass;
use ReflectionMethod;

class FilteringTest extends Unit
{
    private function method(string $class, string $name): ReflectionMethod
    {
        return new ReflectionClass($class)->getMethod($name);
    }

    public function testDeriveConfigDefaultsReproducePhase1Behaviour(): void
    {
        $config = new DeriveConfig();

        verify($config->visibility)->equals(DeriveConfig::VISIBILITY_PUBLIC);
        verify($config->includeStatic)->false();
        verify($config->includeMagic)->false();
        verify($config->dropConstruct)->true();
        verify($config->dropDestruct)->true();
        verify($config->visibilityMask())->equals(ReflectionMethod::IS_PUBLIC);
    }

    public function testDeriveConfigRejectsUnknownVisibility(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DeriveConfig(visibility: 'private');
    }

    public function testDeriveConfigIsImmutable(): void
    {
        $config = new DeriveConfig();
        $clone = $config->withIncludeStatic(true);

        verify($clone)->notEquals($config);
        verify($config->includeStatic)->false();
        verify($clone->includeStatic)->true();
    }

    public function testVisibilityFilter(): void
    {
        $filter = new VisibilityFilter(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);

        verify($filter->keep($this->method(\_Fixture\StaticHeavyClass::class, 'instanceMethod')))->true();
    }

    public function testStaticFilterDropsUnlessIncluded(): void
    {
        verify(new StaticFilter(false)->keep($this->method(\_Fixture\StaticHeavyClass::class, 'staticPublicMethod')))->false();
        verify(new StaticFilter(true)->keep($this->method(\_Fixture\StaticHeavyClass::class, 'staticPublicMethod')))->true();
    }

    public function testMagicFilterDropsUnlessIncluded(): void
    {
        verify(new MagicMethodFilter(false)->keep($this->method(\_Fixture\MagicMethodsClass::class, '__toString')))->false();
        verify(new MagicMethodFilter(true)->keep($this->method(\_Fixture\MagicMethodsClass::class, '__toString')))->true();
    }

    public function testConstructorFilterHonoursDropFlags(): void
    {
        verify(new ConstructorFilter(dropConstruct: true, dropDestruct: true)
            ->keep($this->method(\_Fixture\ReadonlyClass::class, '__construct')))->false();

        verify(new ConstructorFilter(dropConstruct: false, dropDestruct: true)
            ->keep($this->method(\_Fixture\ReadonlyClass::class, '__construct')))->true();

        verify(new ConstructorFilter(dropConstruct: true, dropDestruct: true)
            ->keep($this->method(\_Fixture\UnionTypeClass::class, 'queryLeaf')))->true();
    }

    public function testExplicitFilterChainOverridesDefaults(): void
    {
        $config = new DeriveConfig(includeStatic: true, includeMagic: true, dropConstruct: false);
        $extractor = new MethodExtractor($config);

        $methods = $extractor->extract(new ReflectionClass(\_Fixture\StaticHeavyClass::class));
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $methods);

        verify(in_array('staticPublicMethod', $names, true))->true();
        verify(in_array('instanceMethod', $names, true))->true();
    }

    public function testExplicitFilterChainCanReplaceBuiltins(): void
    {
        $keepAll = new class () implements \ItPack\DeriveIt\Contract\Generator\MethodFilterInterface {
            public function keep(ReflectionMethod $method): bool
            {
                return true;
            }
        };

        $extractor = new MethodExtractor(new DeriveConfig(), $keepAll);
        $methods = $extractor->extract(new ReflectionClass(\_Fixture\ReadonlyClass::class));

        verify(count($methods))->equals(1);
    }

    public function testTypeFidelityUnionIntersectionNullable(): void
    {
        $source = DeriveIt::derive(\_Fixture\UnionTypeClass::class);
        verify($source)->stringContainsString('function queryLeaf(string $key): array|string|int|float|bool|null');

        $intersection = DeriveIt::derive(\_Fixture\IntersectionTypeClass::class);
        verify($intersection)->stringContainsString('function process(\Countable&\Iterator $input): void');
    }

    public function testTypeFidelityVariadicAndByRef(): void
    {
        $source = DeriveIt::derive(\_Fixture\VariadicAndByRefClass::class);

        verify($source)->stringContainsString('function combine(string ...$parts): array');
        verify($source)->stringContainsString('function increment(int &$counter): void');
    }

    public function testTypeFidelityEnumParamAndStaticReturn(): void
    {
        $source = DeriveIt::derive(\_Fixture\ConsumesEnumParamClass::class);

        verify($source)->stringContainsString('function withDistribution(\SimpleBackedEnum $distribution = SimpleBackedEnum::DEFAULT): static');
    }

    public function testTypeFidelityNullableSelfReturn(): void
    {
        $source = DeriveIt::derive(\_Fixture\NullableTypeClass::class);

        verify($source)->stringContainsString('function find(?string $id = null): ?NullableTypeClass');
    }

    public function testConstructorPromotionParamsAreNotSeparateMethods(): void
    {
        $interface = DeriveIt::deriveInterface(\_Fixture\ConstructorPromotionMixedVisibilityClass::class);

        verify($interface->getMethods())->equals([]);
    }
}

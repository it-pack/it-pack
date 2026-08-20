<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\LeakageDetectedException;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\Naming;
use ItPack\DeriveIt\Generator\TypeSubstitutor;
use ReflectionClass;

/**
 * Phase 10 correctness cluster: deep leakage auditor (G1), family honors caller
 * config (G2), injectable instance cache (G4), per-mode collision/enum parity (G12).
 */
class Phase10CorrectnessTest extends Unit
{
    public function testAuditorFlagsUnmappedForeignType(): void
    {
        // ConsumesEnumParamClass references the global \SimpleBackedEnum, which is
        // outside the _Fixture target namespace and not in any substitution map.
        $this->expectException(LeakageDetectedException::class);

        $config = new DeriveConfig()->withAuditLeakage(true);

        new InterfaceDeriver(config: $config)
            ->derive(\_Fixture\ConsumesEnumParamClass::class)
        ;
    }

    public function testAuditorAllowsWhenForeignTypeIsSubstitutedIntoTargetNamespace(): void
    {
        $config = new DeriveConfig(
            auditLeakage: true,
            typeSubstitutionMap: ['SimpleBackedEnum' => '_Fixture\ConsumesEnumParamClassInterface'],
        );

        $interface = new InterfaceDeriver(config: $config)
            ->derive(\_Fixture\ConsumesEnumParamClass::class)
        ;

        verify($interface->getMethod('withDistribution')->getParameters()['distribution']->getType())
            ->equals('_Fixture\ConsumesEnumParamClassInterface')
        ;
    }

    public function testAuditorIsSilentByDefault(): void
    {
        // With the default (audit off) the foreign-type "leak" must not throw.
        $interface = new InterfaceDeriver()->derive(\_Fixture\ConsumesEnumParamClass::class);

        verify($interface->hasMethod('withDistribution'))->true();
    }

    public function testCollisionDetectedForEnumInTargetNamespace(): void
    {
        $ns = 'EnumCollide' . uniqid();
        eval("namespace $ns; enum Target {}");
        eval("namespace $ns; enum TargetInterface {}");

        $this->expectException(NamingCollisionException::class);
        Naming::assertNoCollision(new ReflectionClass($ns . '\Target'));
    }

    public function testFamilyHonorsIncludeStaticConfig(): void
    {
        $withStatic = DeriveIt::deriveFamily(
            [\_Fixture\StaticHeavyClass::class],
            new DeriveConfig(includeStatic: true),
        );
        verify($withStatic[\_Fixture\StaticHeavyClass::class])->stringContainsString('staticPublicMethod');

        $default = DeriveIt::deriveFamily([\_Fixture\StaticHeavyClass::class]);
        verify($default[\_Fixture\StaticHeavyClass::class])->stringNotContainsString('staticPublicMethod');
    }

    public function testFamilyHonorsIncludeConstantsConfig(): void
    {
        $with = DeriveIt::deriveFamily(
            [\_Fixture\ConstantsVisibilityClass::class],
            new DeriveConfig(includeConstants: true),
        );
        verify($with[\_Fixture\ConstantsVisibilityClass::class])->stringContainsString('PUBLIC_CONST');

        $default = DeriveIt::deriveFamily([\_Fixture\ConstantsVisibilityClass::class]);
        verify($default[\_Fixture\ConstantsVisibilityClass::class])->stringNotContainsString('PUBLIC_CONST');
    }

    public function testFamilyCacheIsInstanceScopedNotStatic(): void
    {
        // If the cache were process-global static, the second (default-config)
        // deriver would wrongly reuse the first instance's includeStatic result.
        $a = new FamilyDeriver(new DeriveConfig(includeStatic: true))
            ->derive([\_Fixture\StaticHeavyClass::class])
        ;
        $b = new FamilyDeriver(new DeriveConfig())
            ->derive([\_Fixture\StaticHeavyClass::class])
        ;

        $name = \_Fixture\StaticHeavyClass::class;
        verify($a[$name]->hasMethod('staticPublicMethod'))->true();
        verify($b[$name]->hasMethod('staticPublicMethod'))->false();
    }

    public function testPerTraitDeriveThrowsOnSameShortNameClash(): void
    {
        $ns = 'Clash' . uniqid();
        eval("namespace {$ns}\\A; trait Foo { public function a(): void {} }");
        eval("namespace {$ns}\\B; trait Foo { public function b(): void {} }");
        eval("namespace {$ns}; class C { use \\{$ns}\\A\\Foo; use \\{$ns}\\B\\Foo; }");

        $this->expectException(NamingCollisionException::class);
        new \ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver()
            ->deriveTraitInterfaces(new ReflectionClass($ns . '\C'))
        ;
    }

    public function testClearCacheKeepsDerivationStable(): void
    {
        $deriver = new FamilyDeriver(new DeriveConfig(includeStatic: true));

        $first = $deriver->derive([\_Fixture\StaticHeavyClass::class]);
        $deriver->clearCache();
        $second = $deriver->derive([\_Fixture\StaticHeavyClass::class]);

        $name = \_Fixture\StaticHeavyClass::class;
        verify($first[$name]->hasMethod('staticPublicMethod'))->true();
        verify($second[$name]->hasMethod('staticPublicMethod'))->true();
    }

    public function testAdapterSupportsStaticMethods(): void
    {
        // G3 / EC4: an adapter must implement static interface methods by
        // forwarding to the adaptee class.
        $source = DeriveIt::deriveAdapter(
            \_Fixture\StaticHeavyClass::class,
            [],
            new DeriveConfig(includeStatic: true),
        );

        verify($source)->stringContainsString('static function staticPublicMethod');
        verify($source)->stringContainsString('\\_Fixture\\StaticHeavyClass::staticPublicMethod');
    }

    public function testEnumAndAbstractTargetsSupported(): void
    {
        // G5 / EC13: enums and abstract classes derive without crashing; their
        // concrete/instance public surface is lifted.
        $enum = DeriveIt::derive(\_Fixture\PureEnum::class);
        verify($enum)->stringContainsString('interface PureEnumInterface');
        verify($enum)->stringContainsString('function label');

        $abstract = DeriveIt::derive(\_Fixture\AbstractBaseClass::class);
        verify($abstract)->stringContainsString('interface AbstractBaseClassInterface');
        verify($abstract)->stringContainsString('function concreteBaseMethod');
        verify($abstract)->stringContainsString('function abstractMethod');
    }

    public function testSubstitutorRewritesSelfAndParentConst(): void
    {
        // G6 / EC6: `self::CONST` and `parent::CONST` default references are
        // rewritten to the derived interface FQCN.
        $substitutor = new TypeSubstitutor(
            ['Foo' => 'FooInterface'],
            'SelfInterface',
            'ParentInterface',
        );

        verify($substitutor->rewriteConstantExpression('self::DEFAULT'))->equals('SelfInterface::DEFAULT');
        verify($substitutor->rewriteConstantExpression('parent::DEFAULT'))->equals('ParentInterface::DEFAULT');
        verify($substitutor->rewriteConstantExpression('Foo::BAR'))->equals('FooInterface::BAR');
    }

    public function testConstantDefaultRewrittenToInterface(): void
    {
        // G6 / EC6: a `self::CONST` default becomes `<Interface>::CONST` and the
        // `self::` form disappears (valid because the constant is lifted).
        $source = new InterfaceDeriver(config: new DeriveConfig(includeConstants: true))
            ->deriveSource(\_Fixture\ConstantDefaultClass::class)
        ;

        verify($source)->stringContainsString('ConstantDefaultClassInterface::DEFAULT');
        verify($source)->stringNotContainsString('self::DEFAULT');
    }

    public function testOwnedAttributesAllowListDropsForeignAttributes(): void
    {
        // G7 / EC8: with an allow-list, only owned attributes survive; the builtin
        // #[Override] (if present) is always dropped. Without an allow-list
        // (back-compat) all attributes are kept.
        $all = new InterfaceDeriver(config: new DeriveConfig(attributePassThrough: true))
            ->deriveSource(\_Fixture\AttributedClass::class)
        ;
        verify($all)->stringContainsString('Deprecated');
        verify($all)->stringContainsString('SensitiveParameter');

        $onlyOwned = new InterfaceDeriver(
            config: new DeriveConfig(attributePassThrough: true)->withOwnedAttributes(['Deprecated']),
        )->deriveSource(\_Fixture\AttributedClass::class);
        verify($onlyOwned)->stringContainsString('Deprecated');
        verify($onlyOwned)->stringNotContainsString('SensitiveParameter');
    }
}

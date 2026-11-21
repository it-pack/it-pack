<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Exception\NamingCollisionException;
use ItPack\DeriveIt\Generator\DeriveConfig;
use ItPack\DeriveIt\Generator\FamilyDeriver;
use ItPack\DeriveIt\Generator\InterfaceDeriver;
use ItPack\DeriveIt\Generator\Naming;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use ReflectionClass;

/**
 * Destination-namespace configuration (DeriveConfig::$namespace): per-trait and
 * family rendering honor the configured namespace, falling back to the reflected
 * namespace when it is unset.
 */
final class NamespaceConfigTest extends Unit
{
    public function testEffectiveNamespaceFallsBackToReflectedWhenUnset(): void
    {
        $config = new DeriveConfig();

        self::assertSame('_Fixture', $config->effectiveNamespace('_Fixture'));
        self::assertSame('', $config->effectiveNamespace(''));
    }

    public function testEffectiveNamespaceHonorsConfiguredNamespace(): void
    {
        $config = new DeriveConfig()->withNamespace('Custom\\Port');

        self::assertSame('Custom\\Port', $config->effectiveNamespace('_Fixture'));
        self::assertSame('Custom\\Port', $config->effectiveNamespace(''));
    }

    public function testDerivedInterfaceFqcnHonorsOverrideNamespace(): void
    {
        $reflection = new ReflectionClass(\_Fixture\ReadonlyClass::class);

        self::assertSame(
            '_Fixture\ReadonlyClassInterface',
            Naming::derivedInterfaceFqcn($reflection),
        );
        self::assertSame(
            'Custom\\Port\\ReadonlyClassInterface',
            Naming::derivedInterfaceFqcn($reflection, 'Custom\\Port'),
        );
    }

    public function testAssertNoCollisionUsesOverrideNamespace(): void
    {
        $configured = 'CollCfg' . uniqid();
        eval('namespace ' . $configured . '; class FooInterface {}');

        $ns = 'CollTgt' . uniqid();
        eval('namespace ' . $ns . '; class Foo { public function a(): void {} }');

        $config = new DeriveConfig()->withNamespace($configured);

        $this->expectException(NamingCollisionException::class);
        new InterfaceDeriver(config: $config)->derive($ns . '\Foo');
    }

    public function testFlatDeriveSourceRendersIntoConfiguredNamespace(): void
    {
        $ns = 'FlatNs' . uniqid();
        eval('namespace ' . $ns . '; class FlatPort { public function work(): string { return \'x\'; } }');

        $config = new DeriveConfig()->withNamespace('Custom\\Flat');

        $source = new InterfaceDeriver(config: $config)->deriveSource($ns . '\FlatPort');

        verify($source)->stringContainsString('namespace Custom\\Flat;');
        verify($source)->stringContainsString('interface FlatPortInterface');
        verify($source)->stringNotContainsString('namespace ' . $ns . ';');
    }

    public function testFlatDeriveSourcePreservesReflectedNamespaceByDefault(): void
    {
        $ns = 'FlatDefault' . uniqid();
        eval('namespace ' . $ns . '; class FlatDefaultPort { public function work(): string { return \'x\'; } }');

        $source = new InterfaceDeriver()->deriveSource($ns . '\FlatDefaultPort');

        verify($source)->stringContainsString('namespace ' . $ns . ';');
        verify($source)->stringNotContainsString('namespace Custom\\Flat;');
    }

    public function testFlatDeriveResultFqcnUsesEffectiveNamespace(): void
    {
        $ns = 'FlatResult' . uniqid();
        eval('namespace ' . $ns . '; class FlatResultPort { public function work(): string { return \'x\'; } }');

        $config = new DeriveConfig()->withNamespace('Custom\\FlatResult');

        $result = new InterfaceDeriver(config: $config)->deriveWithResult($ns . '\FlatResultPort');

        verify($result->fqcn)->equals('Custom\\FlatResult\\FlatResultPortInterface');
    }

    public function testFlatAnalyzeFqcnUsesEffectiveNamespace(): void
    {
        $ns = 'FlatAnalyze' . uniqid();
        eval('namespace ' . $ns . '; class FlatAnalyzePort { public function work(): string { return \'x\'; } }');

        $config = new DeriveConfig()->withNamespace('Custom\\FlatAnalyze');

        $result = new InterfaceDeriver(config: $config)->analyze($ns . '\FlatAnalyzePort');

        verify($result->fqcn)->equals('Custom\\FlatAnalyze\\FlatAnalyzePortInterface');
    }

    public function testPerTraitDeriveSourceRendersIntoConfiguredNamespace(): void
    {
        $ns = 'PtNs' . uniqid();
        eval(
            'namespace ' . $ns .
            '; trait TraitA { public function fromTrait(): void {} }' .
            ' class UsesTrait { use TraitA; public function ownMethod(): void {} }'
        );

        $config = new DeriveConfig()->withNamespace('Custom\\Pt');

        $source = new PerTraitInterfaceDeriver(config: $config)->deriveSource($ns . '\UsesTrait');

        verify($source)->stringContainsString('namespace Custom\\Pt;');
        verify($source)->stringContainsString('interface TraitAInterface');
        verify($source)->stringContainsString('interface UsesTraitInterface');
        verify($source)->stringNotContainsString('namespace ' . $ns . ';');
    }

    public function testFamilyDeriveSourceRendersIntoConfiguredNamespace(): void
    {
        $ns = 'FamNs' . uniqid();
        eval(
            'namespace ' . $ns .
            '; class Alpha { public function beta(): Beta { return new Beta(); } }' .
            ' class Beta { public function gamma(): string { return \'x\'; } }'
        );

        $config = new DeriveConfig()->withNamespace('Custom\\Fam');

        $source = new FamilyDeriver($config)->deriveSource([$ns . '\Alpha', $ns . '\Beta']);

        verify($source)->stringContainsString('namespace Custom\\Fam;');
        verify($source)->stringContainsString('interface AlphaInterface');
        verify($source)->stringContainsString('interface BetaInterface');
        verify($source)->stringNotContainsString('namespace ' . $ns . ';');
    }

    public function testDeriveFamilyRendersIntoConfiguredNamespace(): void
    {
        $ns = 'DeriveFamNs' . uniqid();
        eval(
            'namespace ' . $ns .
            '; class Gamma { public function delta(): Delta { return new Delta(); } }' .
            ' class Delta { public function epsilon(): string { return \'x\'; } }'
        );

        $config = new DeriveConfig()->withNamespace('Custom\\DeriveFam');

        $derived = DeriveIt::deriveFamily([$ns . '\Gamma', $ns . '\Delta'], $config);

        foreach ($derived as $source) {
            verify($source)->stringContainsString('namespace Custom\\DeriveFam;');
        }
    }

    public function testDeriveFamilyFromRootRendersIntoConfiguredNamespace(): void
    {
        $ns = 'RootNs' . uniqid();
        eval(
            'namespace ' . $ns .
            '; class Root { public function leaf(): Leaf { return new Leaf(); } }' .
            ' class Leaf { public function value(): string { return \'x\'; } }'
        );

        $config = new DeriveConfig()->withNamespace('Custom\\Root');

        $derived = DeriveIt::deriveFamilyFromRoot($ns . '\Root', [], $config);

        foreach ($derived as $source) {
            verify($source)->stringContainsString('namespace Custom\\Root;');
        }
    }
}

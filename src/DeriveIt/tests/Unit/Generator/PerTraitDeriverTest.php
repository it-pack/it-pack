<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ItPack\DeriveIt\Generator\PerTraitInterfaceDeriver;
use ReflectionClass;

class PerTraitDeriverTest extends Unit
{
    public function testDeriveTraitInterfacesCreatesOnePerTraitWithMethods(): void
    {
        $interfaces = new PerTraitInterfaceDeriver()
            ->deriveTraitInterfaces(new ReflectionClass(\_Fixture\Stubs\ClassD::class))
        ;

        verify(array_keys($interfaces))->equals(['TraitE', 'TraitA']);
        verify($interfaces['TraitE']->hasMethod('traitEMethod'))->true();
        verify($interfaces['TraitA']->hasMethod('traitAMethod'))->true();
    }

    public function testEmptyTraitInterfacesAreSkipped(): void
    {
        $interfaces = new PerTraitInterfaceDeriver()
            ->deriveTraitInterfaces(new ReflectionClass(\_Fixture\Stubs\ClassD::class))
        ;

        verify(isset($interfaces['TraitB']))->false();
        verify(isset($interfaces['TraitC']))->false();
        verify(isset($interfaces['TraitD']))->false();
    }

    public function testMainExtendsOnlyDerivedTraitInterfaces(): void
    {
        $main = new PerTraitInterfaceDeriver()
            ->derive(new ReflectionClass(\_Fixture\Stubs\ClassD::class))
        ;

        verify($main->getExtends())->equals(['TraitEInterface', 'TraitAInterface']);
        verify($main->getMethods())->equals([]);
    }

    public function testPerTraitSourceComposesTraitInterfacesAndMain(): void
    {
        $source = DeriveIt::derivePerTrait(\_Fixture\Stubs\ClassD::class);

        verify($source)->stringContainsString('interface TraitEInterface');
        verify($source)->stringContainsString('interface TraitAInterface');
        verify($source)->stringContainsString('interface ClassDInterface extends');
    }

    public function testConflictResolutionKeepsMethodInExactlyOneTraitInterface(): void
    {
        $interfaces = new PerTraitInterfaceDeriver()
            ->deriveTraitInterfaces(new ReflectionClass(\_Fixture\Stubs\ConflictingTraitsClass::class))
        ;

        verify($interfaces['FirstConflictTrait']->hasMethod('shared'))->true();
        verify($interfaces['FirstConflictTrait']->hasMethod('fromFirst'))->true();
        verify($interfaces['SecondConflictTrait']->hasMethod('fromSecond'))->true();
        verify($interfaces['SecondConflictTrait']->hasMethod('shared'))->false();

        $allNames = [];
        foreach ($interfaces as $interface) {
            foreach ($interface->getMethods() as $method) {
                $allNames[] = $method->getName();
            }
        }
        verify(array_count_values($allNames)['shared'] ?? 0)->equals(1);
    }

    public function testConflictMainExtendsBothTraitInterfaces(): void
    {
        $main = new PerTraitInterfaceDeriver()
            ->derive(new ReflectionClass(\_Fixture\Stubs\ConflictingTraitsClass::class))
        ;

        verify($main->getExtends())
            ->equals(['FirstConflictTraitInterface', 'SecondConflictTraitInterface'])
        ;
    }

    public function testNoAdapteeLeakageInPerTraitMode(): void
    {
        $main = new PerTraitInterfaceDeriver()
            ->derive(new ReflectionClass(\_Fixture\Stubs\ClassD::class))
        ;

        foreach ($main->getExtends() as $extend) {
            verify(str_ends_with($extend, 'Interface'))->true();
        }
    }
}

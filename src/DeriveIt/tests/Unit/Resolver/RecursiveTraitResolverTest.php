<?php

declare(strict_types=1);

namespace Tests\Unit\Resolver;

use _Fixture\Stubs;
use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Depends;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Resolver\RecursiveTraitResolver;
use ReflectionClass;

class RecursiveTraitResolverTest extends Unit
{
    private const array CLASS_INHERITANCE_DATA = [
        Stubs\ClassA::class => false,
        Stubs\ClassB::class => Stubs\ClassA::class,
        Stubs\ClassC::class => Stubs\ClassB::class,
        Stubs\ClassD::class => Stubs\ClassC::class,
    ];

    private const array TRAIT_INHERITANCE_DATA = [
        Stubs\ClassA::class => [
            Stubs\TraitA::class,
            Stubs\TraitB::class,
        ],
        Stubs\TraitA::class => [
            Stubs\TraitB::class,
        ],
        Stubs\TraitB::class => [
            Stubs\TraitC::class,
        ],
        Stubs\TraitC::class => [
            Stubs\TraitD::class,
        ],
        Stubs\TraitD::class => [],
    ];

    private const array CLASS_TRAITS_DATA = [
        Stubs\ClassA::class => [
            Stubs\TraitA::class,
            Stubs\TraitB::class,
        ],
        Stubs\ClassB::class => [],
        Stubs\ClassC::class => [],
        Stubs\ClassD::class => [
            Stubs\TraitE::class,
        ],
    ];

    // Verify trait inheritance architecture
    #[DataProvider('provideTraitInheritanceData')]
    public function testTraitStubInheritance(string $class, array $traits): void
    {
        $this->verifyTraitUsage($class, $traits);
    }

    // Verify class inheritance architecture
    #[DataProvider('provideClassInheritanceData')]
    public function testStubClassInheritance(string $class, string|false $parent): void
    {
        $class = new ReflectionClass($class);

        verify(
            $class->getParentClass()
                ? $class->getParentClass()->getName()
                : false
        )->equals(
            $parent
        );
    }

    // Verify class trait usage architecture
    #[DataProvider('provideClassTraitData')]
    public function testClassTraitUsage(string $class, array $traits): void
    {
        $this->verifyTraitUsage($class, $traits);
    }

    #[Depends('testTraitStubInheritance')]
    public function testResolveRecursiveTraits(): void
    {
        $traits = array_map(
            static fn (ReflectionClass $trait) => $trait->getName(),
            RecursiveTraitResolver::create()->resolve(
                new ReflectionClass(
                    Stubs\ClassA::class
                )
            )
        );

        sort($traits);

        verify(
            $traits
        )->equals([
            Stubs\TraitA::class,
            Stubs\TraitB::class,
            Stubs\TraitC::class,
            Stubs\TraitD::class,
        ]);
    }

    #[Depends('testTraitStubInheritance', 'testStubClassInheritance', 'testClassTraitUsage')]
    public function testResolveClassInheritance(): void
    {
        $traits = array_map(
            static fn (ReflectionClass $trait) => $trait->getName(),
            RecursiveTraitResolver::create()->resolve(
                new ReflectionClass(
                    Stubs\ClassD::class
                )
            )
        );

        sort($traits);

        verify(
            $traits
        )->equals([
            Stubs\TraitA::class,
            Stubs\TraitB::class,
            Stubs\TraitC::class,
            Stubs\TraitD::class,
            Stubs\TraitE::class,
        ]);
    }

    private function provideTraitInheritanceData(): array
    {
        $result = [];
        foreach (self::TRAIT_INHERITANCE_DATA as $class => $traits) {
            $result[] = [$class, $traits];
        }
        return $result;
    }

    private function provideClassInheritanceData(): array
    {
        $result = [];
        foreach (self::CLASS_INHERITANCE_DATA as $class => $parent) {
            $result[] = [$class, $parent];
        }

        return $result;
    }

    private function provideClassTraitData(): array
    {
        $result = [];
        foreach (self::CLASS_TRAITS_DATA as $class => $traits) {
            $result[] = [$class, $traits];
        }
        return $result;
    }

    private function verifyTraitUsage(string $class, array $traits): void
    {
        $traitNames = array_map(
            static fn (ReflectionClass $trait) => $trait->getName(),
            new ReflectionClass($class)->getTraits()
        );

        sort($traitNames);

        verify(
            $traitNames
        )->equals(
            $traits
        );
    }
}

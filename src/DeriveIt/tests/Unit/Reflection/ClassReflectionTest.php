<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection;

use _Fixture\Stubs;
use Codeception\Stub;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Contract\Resolver\RecursiveClassResolverInterface;
use ItPack\DeriveIt\Contract\Resolver\RecursiveInterfaceResolverInterface;
use ItPack\DeriveIt\Contract\Resolver\RecursiveTraitResolverInterface;
use ItPack\DeriveIt\Reflection\ClassReflection;
use ItPack\DeriveIt\Resolver\RecursiveClassResolver;
use ItPack\DeriveIt\Resolver\RecursiveInterfaceResolver;
use ItPack\DeriveIt\Resolver\RecursiveTraitResolver;

class ClassReflectionTest extends Unit
{
    public const array RECURSIVE_CLASSES = [
        Stubs\ClassA::class,
        Stubs\ClassB::class,
        Stubs\ClassC::class,
        Stubs\ClassD::class,
    ];

    public const array RECURSIVE_INTERFACES = [
        Stubs\InterfaceA::class,
        Stubs\InterfaceB::class,
        Stubs\InterfaceC::class,
        Stubs\InterfaceD::class,
    ];

    public const array RECURSIVE_TRAITS = [
        Stubs\TraitA::class,
        Stubs\TraitB::class,
        Stubs\TraitC::class,
        Stubs\TraitD::class,
    ];

    public function testGetClassesRecursively(): void
    {
        ClassReflection::setRecursiveClassResolver(
            Stub::makeEmpty(RecursiveClassResolverInterface::class, [
                'resolve' => self::RECURSIVE_CLASSES,
            ])
        );

        $reflection = ClassReflection::create(
            new class () {}
        );

        verify(
            $reflection->getClassesRecursively()
        )->equals(
            self::RECURSIVE_CLASSES
        );
    }

    public function testGetInterfacesRecursively(): void
    {
        ClassReflection::setRecursiveInterfaceResolver(
            Stub::makeEmpty(RecursiveInterfaceResolverInterface::class, [
                'resolve' => self::RECURSIVE_INTERFACES,
            ])
        );

        $reflection = ClassReflection::create(
            new class () {}
        );

        verify(
            $reflection->getInterfacesRecursively()
        )->equals(
            self::RECURSIVE_INTERFACES
        );
    }

    public function testGetTraitResolver(): void
    {
        ClassReflection::setRecursiveTraitResolver(
            Stub::makeEmpty(RecursiveTraitResolverInterface::class, [
                'resolve' => self::RECURSIVE_TRAITS,
            ])
        );

        $reflection = ClassReflection::create(
            new class () {}
        );

        verify(
            $reflection->getTraitsRecursively()
        )->equals(
            self::RECURSIVE_TRAITS
        );
    }

    protected function tearDown(): void
    {
        ClassReflection::setRecursiveClassResolver(RecursiveClassResolver::create());
        ClassReflection::setRecursiveInterfaceResolver(RecursiveInterfaceResolver::create());
        ClassReflection::setRecursiveTraitResolver(RecursiveTraitResolver::create());

        parent::tearDown();
    }
}

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
        $reflection = new ClassReflection(
            new class () {},
            classResolver: Stub::makeEmpty(RecursiveClassResolverInterface::class, [
                'resolve' => self::RECURSIVE_CLASSES,
            ]),
        );

        verify($reflection->getClassesRecursively())->equals(self::RECURSIVE_CLASSES);
    }

    public function testGetInterfacesRecursively(): void
    {
        $reflection = new ClassReflection(
            new class () {},
            interfaceResolver: Stub::makeEmpty(RecursiveInterfaceResolverInterface::class, [
                'resolve' => self::RECURSIVE_INTERFACES,
            ]),
        );

        verify($reflection->getInterfacesRecursively())->equals(self::RECURSIVE_INTERFACES);
    }

    public function testGetTraitResolver(): void
    {
        $reflection = new ClassReflection(
            new class () {},
            traitResolver: Stub::makeEmpty(RecursiveTraitResolverInterface::class, [
                'resolve' => self::RECURSIVE_TRAITS,
            ]),
        );

        verify($reflection->getTraitsRecursively())->equals(self::RECURSIVE_TRAITS);
    }
}

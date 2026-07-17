<?php

declare(strict_types=1);

namespace Tests\Unit\Resolver;

use _Fixture\Stubs;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Resolver\RecursiveInterfaceResolver;
use ReflectionClass;

class RecursiveInterfaceResolverTest extends Unit
{
    private const array INTERFACE_INHERITANCE_DATA = [
        Stubs\ClassA::class => [
            Stubs\InterfaceA::class,
        ],
        Stubs\ClassB::class => [
            Stubs\InterfaceA::class,
            Stubs\InterfaceB::class,
        ],
        Stubs\ClassC::class => [
            Stubs\InterfaceA::class,
            Stubs\InterfaceB::class,
            Stubs\InterfaceC::class,
        ],
        Stubs\ClassD::class => [
            Stubs\InterfaceA::class,
            Stubs\InterfaceB::class,
            Stubs\InterfaceC::class,
            Stubs\InterfaceD::class,
            Stubs\InterfaceE::class,
            Stubs\InterfaceF::class,
        ],
    ];

    // Verify class inheritance architecture
    #[DataProvider('provideInterfaceData')]
    public function testStubClassInheritance(string $class, array $expectedInterfaces): void
    {
        $resolver = RecursiveInterfaceResolver::create();

        $interfaces = $resolver->resolve(
            new ReflectionClass($class),
        );

        sort($interfaces);

        verify(
            array_map(
                static fn (ReflectionClass $interface) => $interface->getName(),
                $interfaces
            ),
        )->equals(
            $expectedInterfaces
        );
    }

    private function provideInterfaceData(): array
    {
        $result = [];
        foreach (self::INTERFACE_INHERITANCE_DATA as $class => $interfaces) {
            $result[] = [$class, $interfaces];
        }
        return $result;
    }

}

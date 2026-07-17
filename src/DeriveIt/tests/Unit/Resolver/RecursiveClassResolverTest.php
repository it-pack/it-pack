<?php

declare(strict_types=1);

namespace Tests\Unit\Resolver;

use _Fixture\Stubs;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\DeriveIt\Resolver\RecursiveClassResolver;
use ReflectionClass;

class RecursiveClassResolverTest extends Unit
{
    private const array CLASS_INHERITANCE_DATA = [
        Stubs\ClassA::class => [
            Stubs\ClassA::class,
        ],
        Stubs\ClassB::class => [
            Stubs\ClassA::class,
            Stubs\ClassB::class,
        ],
        Stubs\ClassC::class => [
            Stubs\ClassA::class,
            Stubs\ClassB::class,
            Stubs\ClassC::class,
        ],
        Stubs\ClassD::class => [
            Stubs\ClassA::class,
            Stubs\ClassB::class,
            Stubs\ClassC::class,
            Stubs\ClassD::class,
        ],
    ];

    // Verify class inheritance architecture
    #[DataProvider('provideInterfaceData')]
    public function testStubClassInheritance(string $class, array $expectedClasses): void
    {
        $resolver = RecursiveClassResolver::create();

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
            $expectedClasses
        );
    }

    private function provideInterfaceData(): array
    {
        $result = [];
        foreach (self::CLASS_INHERITANCE_DATA as $class => $classes) {
            $result[] = [$class, $classes];
        }
        return $result;
    }

}

<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Scripts;

require_once __DIR__ . '/../vendor/autoload.php';


use ItPack\DeriveIt\Reflection\ClassReflection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\TraitType;
use Nette\PhpGenerator\TraitUse;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Traits\ConstantsAware;
use ItPack\DeriveIt\Reflection;
use ReflectionClass;
use ReflectionMethod;
use Generator;

const OUTPUT_DIR = __DIR__ . '/../generated/';


################################
## Util
################################

function retrieveTraitMethods(string $trait) : array
{
    return new Factory()->fromClassReflection(
        new ReflectionClass($trait)
    )->getMethods();

    return TraitType::from($trait)->getMethods();
}

function retrieveClassMethods(string $class): array
{
    return array_map(
        fn(ReflectionMethod $method) => $method->getReturnType(),
        new ReflectionClass($class)->getMethods(),
    );
}


function retrieveClassTraitNames(string $class): array
{
    return array_values(array_map(static fn(TraitUse $trait) => $trait->getName(), ClassType::from($class)->getTraits()));
}

function filterPublicMethods(Method|ReflectionMethod $method)
{
    return $method->isPublic();
}

function retrieveAllClassMethods(string $className)
{
    return array_merge(
        array_merge(
            ...array_map(
                retrieveTraitMethods(...),
                retrieveClassTraitNames($className)
            )
        ),
        retrieveClassMethods($className)
    );
}

function retrieveAllPublicClassMethods(string $className)
{
    return array_filter(
        retrieveAllClassMethods($className),
        filterPublicMethods(...)
    );
}

function deriveInterfaceFromTrait(string $className): InterfaceType
{
    return new InterfaceType($className . 'Interface')
        ->setMethods(
            retrieveAllPublicClassMethods($className)
        )
        ->removeMethod('__destruct')
        ->removeMethod('__construct');
}


function resolveClass(string $class): ClassReflection
{

    return ClassReflection::create($class);
}

function printClassInfo(ClassReflection $classReflection): void
{
    echo "\n***************************************\n";

    foreach ([
        'Classes' => 'getClassesRecursively',
        'Interfaces' => 'getInterfacesRecursively',
        'Traits' => 'getTraitsRecursively',
             ] as $type => $method) {
        printResolverInfo($classReflection, $type, $method);
    }

    printMethods($classReflection);
}

function printResolverInfo(ClassReflection $classReflection, string $type, string $method): void
{
    echo "\n***************************************\n";

    $class = $classReflection->getName();
    echo "$type for $class:\n";

    $items = $classReflection->{$method}();

    sort($items);

    print_r($items);

}

function printMethods(ClassReflection $classReflection): void
{
    echo "\n***************************************\n";
    echo " - Methods:\n";

    print_r(
        array_map(
            static fn(Method|ReflectionMethod $method) => $method->getReturnType(),
            retrieveAllPublicClassMethods($classReflection->getName())
        )
    );
}

function resolveClasses(string ...$class): Generator
{
    yield from array_map(resolveClass(...), func_get_args());
}

function generate(string ...$class): void
{
    foreach (resolveClasses(...$class) as $classReflection) {
        printClassInfo($classReflection);


    }
}

generate(
    ReflectionClass::class
);


exit;

$interface->setMethods(
    array_filter(
        array_merge(
            array_merge(
                ...array_map(
                    static fn(string $trait) => TraitType::from($trait)->getMethods(),
                    array_values(
                        array_map(
                            static fn(TraitUse $trait) => $trait->getName(),
                            $class->getTraits()
                        )
                    )
                )
            ),
            $class->getMethods()
        ),
        static fn(Method $method) => $method->isPublic()
    )
);


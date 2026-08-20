<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Reflection;

use ItPack\DeriveIt\Contract\Resolver\RecursiveClassResolverInterface;
use ItPack\DeriveIt\Contract\Resolver\RecursiveInterfaceResolverInterface;
use ItPack\DeriveIt\Contract\Resolver\RecursiveTraitResolverInterface;
use ItPack\DeriveIt\Resolver\RecursiveClassResolver;
use ItPack\DeriveIt\Resolver\RecursiveInterfaceResolver;
use ItPack\DeriveIt\Resolver\RecursiveTraitResolver;
use ReflectionClass;

/**
 * Reflection facade for the derivation pipeline.
 *
 * Resolvers are held as **instance** state (optionally injected via the
 * constructor / {@see self::create()}), falling back to a single shared,
 * immutable default set. There is deliberately no mutable static setter: the
 * earlier static-singleton design let tests leak resolver state across cases and
 * broke mutation-testing runs under shuffled order. Instance state removes that
 * entire class of failure.
 */
final class ClassReflection extends ReflectionClass
{
    private RecursiveClassResolverInterface $classResolver;

    private RecursiveInterfaceResolverInterface $interfaceResolver;

    private RecursiveTraitResolverInterface $traitResolver;

    private static ?RecursiveClassResolverInterface $defaultClassResolver = null;

    private static ?RecursiveInterfaceResolverInterface $defaultInterfaceResolver = null;

    private static ?RecursiveTraitResolverInterface $defaultTraitResolver = null;

    public function __construct(
        object|string $objectOrClass,
        ?RecursiveClassResolverInterface $classResolver = null,
        ?RecursiveInterfaceResolverInterface $interfaceResolver = null,
        ?RecursiveTraitResolverInterface $traitResolver = null,
    ) {
        parent::__construct($objectOrClass);

        $this->classResolver = $classResolver ?? self::defaultClassResolver();
        $this->interfaceResolver = $interfaceResolver ?? self::defaultInterfaceResolver();
        $this->traitResolver = $traitResolver ?? self::defaultTraitResolver();
    }

    public static function create(
        object|string $objectOrClass,
        ?RecursiveClassResolverInterface $classResolver = null,
        ?RecursiveInterfaceResolverInterface $interfaceResolver = null,
        ?RecursiveTraitResolverInterface $traitResolver = null,
    ): self {
        return new self($objectOrClass, $classResolver, $interfaceResolver, $traitResolver);
    }

    public function getClassesRecursively(): array
    {
        return $this->classResolver->resolve($this);
    }

    public function getInterfacesRecursively(): array
    {
        return $this->interfaceResolver->resolve($this);
    }

    public function getTraitsRecursively(): array
    {
        return $this->traitResolver->resolve($this);
    }

    private static function defaultClassResolver(): RecursiveClassResolverInterface
    {
        return self::$defaultClassResolver ??= RecursiveClassResolver::create();
    }

    private static function defaultInterfaceResolver(): RecursiveInterfaceResolverInterface
    {
        return self::$defaultInterfaceResolver ??= RecursiveInterfaceResolver::create();
    }

    private static function defaultTraitResolver(): RecursiveTraitResolverInterface
    {
        return self::$defaultTraitResolver ??= RecursiveTraitResolver::create();
    }
}

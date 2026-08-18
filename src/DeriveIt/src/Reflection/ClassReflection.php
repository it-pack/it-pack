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

final class ClassReflection extends ReflectionClass
{
    private static ?RecursiveClassResolverInterface $classResolver = null;
    private static ?RecursiveInterfaceResolverInterface $interfaceResolver = null;
    private static ?RecursiveTraitResolverInterface $traitResolver = null;

    public static function create(object|string $objectOrClass): self
    {
        return new self($objectOrClass);
    }

    public function getClassesRecursively(): array
    {
        return self::getRecursiveClassResolver()->resolve($this);
    }

    public static function getRecursiveClassResolver(): RecursiveClassResolverInterface
    {
        return self::$classResolver ??= RecursiveClassResolver::create();
    }

    public static function setRecursiveClassResolver(RecursiveClassResolverInterface $resolver): void
    {
        self::$classResolver = $resolver;
    }

    public function getInterfacesRecursively(): array
    {
        return self::getRecursiveInterfaceResolver()->resolve($this);
    }

    public static function getRecursiveInterfaceResolver(): RecursiveInterfaceResolverInterface
    {
        return self::$interfaceResolver ??= RecursiveInterfaceResolver::create();
    }

    public static function setRecursiveInterfaceResolver(RecursiveInterfaceResolverInterface $resolver): void
    {
        self::$interfaceResolver = $resolver;
    }

    public function getTraitsRecursively(): array
    {
        return self::getRecursiveTraitResolver()->resolve($this);
    }

    public static function getRecursiveTraitResolver(): RecursiveTraitResolverInterface
    {
        return self::$traitResolver ??= RecursiveTraitResolver::create();
    }

    public static function setRecursiveTraitResolver(RecursiveTraitResolverInterface $resolver): void
    {
        self::$traitResolver = $resolver;
    }
}

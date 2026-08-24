<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Resolver;

use ItPack\DeriveIt\Behavior\Utility\LazyCreatable;
use ItPack\DeriveIt\Contract\Resolver\RecursiveInterfaceResolverInterface;
use ReflectionClass;

final class RecursiveInterfaceResolver implements RecursiveInterfaceResolverInterface
{
    use LazyCreatable;

    public function resolve(ReflectionClass $reflection): array
    {
        return $this->resolveClassInheritance($reflection);
    }

    private function resolveClassInheritance(ReflectionClass $reflection): array
    {
        return ($parent = $reflection->getParentClass())
            ? array_merge(
                $this->resolveInterfaceInheritance($reflection),
                $this->resolveClassInheritance($parent)
            )
            : $this->resolveInterfaceInheritance($reflection);
    }

    private function resolveInterfaceInheritance(ReflectionClass $reflection): array
    {
        $interfaces = $reflection->getInterfaces();

        return array_merge(
            $interfaces,
            ...array_values(
                array_map(
                    $this->resolveInterfaceInheritance(...),
                    $interfaces
                )
            )
        );
    }
}

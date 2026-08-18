<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Resolver;

use ItPack\DeriveIt\Behavior\Utility\LazyCreatable;
use ItPack\DeriveIt\Contract\Resolver\RecursiveTraitResolverInterface;
use ReflectionClass;

final class RecursiveTraitResolver implements RecursiveTraitResolverInterface
{
    use LazyCreatable;

    public static function create(): self
    {
        return new self();
    }

    public function resolve(ReflectionClass $reflection): array
    {
        $traits = $this->resolveClassInheritance($reflection);

        return $traits === []
            ? []
            : array_unique(
                array_merge(
                    $traits,
                    ...$this->resolveTraitInheritance(
                        $reflection
                    )
                )
            );
    }

    private function resolveClassInheritance(ReflectionClass $reflection): array
    {
        return ($parent = $reflection->getParentClass())
            ? array_merge(
                $reflection->getTraits(),
                $this->resolve($parent)
            )
            : $reflection->getTraits();
    }

    private function resolveTraitInheritance(ReflectionClass $reflection): array
    {
        return array_values(
            array_map(
                $this->resolve(...),
                $reflection->getTraits()
            )
        );
    }
}

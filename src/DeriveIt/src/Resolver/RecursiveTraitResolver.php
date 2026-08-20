<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Resolver;

use ItPack\DeriveIt\Behavior\Utility\LazyCreatable;
use ItPack\DeriveIt\Contract\Resolver\RecursiveTraitResolverInterface;
use ReflectionClass;

final class RecursiveTraitResolver implements RecursiveTraitResolverInterface
{
    use LazyCreatable;

    /**
     * Per-node resolution cache so a class/trait visited more than once (it is
     * common for a shared trait to appear under several subclasses) is only
     * walked once instead of being re-resolved on every path (F4).
     *
     * @var array<string, list<ReflectionClass>>
     */
    private array $memo = [];

    public static function create(): self
    {
        return new self();
    }

    public function resolve(ReflectionClass $reflection): array
    {
        $key = $reflection->getName();
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $traits = $this->resolveClassInheritance($reflection);
        $resolved = $traits === []
            ? []
            : array_unique(
                array_merge(
                    $traits,
                    ...$this->resolveTraitInheritance($reflection)
                )
            );

        return $this->memo[$key] = $resolved;
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

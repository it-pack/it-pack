<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Resolver;

use ItPack\DeriveIt\Behavior\Utility\LazyCreatable;
use ItPack\DeriveIt\Contract\Resolver\RecursiveClassResolverInterface;
use ReflectionClass;

class RecursiveClassResolver implements RecursiveClassResolverInterface
{
    use LazyCreatable;

    public function resolve(ReflectionClass $reflection): array
    {
        return ($parent = $reflection->getParentClass())
            ? array_merge(
                [$reflection],
                $this->resolve($parent)
            )
            : [$reflection];
    }
}

<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Resolver;

use ReflectionClass;

interface RecursiveResolverInterface
{
    public function resolve(ReflectionClass $reflection): array;
}

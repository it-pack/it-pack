<?php

declare(strict_types=1);

namespace ItPack\LazeIt\SDK;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;

class InitializerValidator
{
    public function validate(Closure $initializer): bool
    {
        $reflection = new ReflectionFunction($initializer);

        if ($reflection->getNumberOfRequiredParameters() === 0) {
            return false;
        }

        $parameter = $reflection->getParameters()[0];
        $type = $parameter->getType();

        if ($type === null) {
            return true;
        }

        if (! $type instanceof ReflectionNamedType) {
            return true;
        }

        if (! $type->isBuiltin()) {
            return true;
        }

        return $type->getName() === 'object';
    }
}

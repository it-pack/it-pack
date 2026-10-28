<?php

declare(strict_types=1);

namespace ItPack\LazeIt\SDK;

use ReflectionFunction;

class InitializerValidator
{
    public function validate(callable $initializer)
    {
        $reflection = new ReflectionFunction($initializer);

        if($reflection->getNumberOfRequiredParameters() === 0) {
            return false;
        }

        $parameter = $reflection->getParameters()[0];

        if($parameter->getType() !== null && $parameter->getType()->isBuiltin()) {}
    }

    private function validateParamter(ReflectionFunction $reflection)
    {
        if($reflection->getNumberOfRequiredParameters() === 0) {
            return true;
        }

        $parameter = $reflection->getParameters()[0];

        if($parameter->getType() === null || !$parameter->getType()->isBuiltin()) {
            return true;
        }

        if($parameter->getType()->getName() === 'object') {
            return true;
        }

        return false;
    }
}
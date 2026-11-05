<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Closure;
use ReflectionFunctionAbstract;

interface CallableReflectionResolverInterface
{
    public function resolve(callable|Closure $callable): ?ReflectionFunctionAbstract;
}

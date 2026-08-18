<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Generator;

use ReflectionMethod;

/**
 * A single, independently testable rule applied to the extracted method surface.
 * A method is kept only when every filter in the chain returns true.
 */
interface MethodFilterInterface
{
    public function keep(ReflectionMethod $method): bool;
}

<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionMethod;

/**
 * Drops __construct / __destruct according to the configured drop flags.
 */
final class ConstructorFilter implements MethodFilterInterface
{
    public function __construct(
        private readonly bool $dropConstruct = true,
        private readonly bool $dropDestruct = true,
    ) {
    }

    public function keep(ReflectionMethod $method): bool
    {
        $name = $method->getName();

        if ($name === '__construct' && $this->dropConstruct) {
            return false;
        }

        if ($name === '__destruct' && $this->dropDestruct) {
            return false;
        }

        return true;
    }
}

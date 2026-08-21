<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionMethod;

/**
 * Drops __construct / __destruct according to the configured drop flags.
 */
final readonly class ConstructorFilter implements MethodFilterInterface
{
    public function __construct(
        private bool $dropConstruct = true,
        private bool $dropDestruct = true,
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

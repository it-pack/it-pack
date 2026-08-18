<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Generator;

use Nette\PhpGenerator\InterfaceType;
use ReflectionClass;

interface DeriverInterface
{
    /**
     * Derive a self-contained interface (Nette InterfaceType) for the given class.
     *
     * @param ReflectionClass|class-string $class
     */
    public function derive(ReflectionClass|string $class): InterfaceType;

    /**
     * Derive the interface and return the rendered PHP source.
     *
     * @param ReflectionClass|class-string $class
     */
    public function deriveSource(ReflectionClass|string $class): string;
}

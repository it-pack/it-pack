<?php

declare(strict_types=1);

namespace ItPack\ExtendIt\Inspection;

use ReflectionClass;

class ClassInspector
{
    public function __construct()
    {

    }

    public function inspect(ReflectionClass $class): void
    {
        $methods = $class->getMethods();
    }

}
<?php

declare(strict_types=1);

namespace ItPack\ExtendIt\Inspection;

use ReflectionClass;

class ClassInspector
{
    public function __construct()
    {
    }

    /**
     * @param ReflectionClass<object> $class
     */
    public function inspect(ReflectionClass $class): void
    {
        // Intentionally a no-op placeholder — to be implemented by the
        // reflection pipeline consumer. See R7 in docs/REPORT.md.
    }
}

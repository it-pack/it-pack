<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Behavior\Utility;

use ReflectionClass;

trait LazyCreatable
{
    abstract public static function create(): self;

    public static function createLazy(): self
    {
        return new ReflectionClass(self::class)->newLazyProxy(
            self::create(...),
        );
    }
}

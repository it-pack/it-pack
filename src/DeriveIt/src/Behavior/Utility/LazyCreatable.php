<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Behavior\Utility;

trait LazyCreatable
{
    /**
     * Default factory: `new self()` in a trait binds to the using class (F-6 / RP-6).
     */
    public static function create(): self
    {
        return new self();
    }
}

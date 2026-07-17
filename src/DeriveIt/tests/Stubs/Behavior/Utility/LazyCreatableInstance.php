<?php

declare(strict_types=1);

namespace Tests\Stubs\Behavior\Utility;

use ItPack\DeriveIt\Behavior\Utility\LazyCreatable;

class LazyCreatableInstance
{
    use LazyCreatable;

    public static function create(): self
    {
        return new self();
    }
}

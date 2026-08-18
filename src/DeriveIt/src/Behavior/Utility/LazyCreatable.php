<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Behavior\Utility;

trait LazyCreatable
{
    abstract public static function create(): self;
}

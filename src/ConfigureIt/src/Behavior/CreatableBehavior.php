<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Behavior;

trait CreatableBehavior
{
    public static function create(): self
    {
        return new self();
    }
}

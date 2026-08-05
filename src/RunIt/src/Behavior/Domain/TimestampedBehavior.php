<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

trait TimestampedBehavior
{
    abstract public function timestamp(): int;

    public function count(): int
    {
        return $this->timestamp();
    }
}

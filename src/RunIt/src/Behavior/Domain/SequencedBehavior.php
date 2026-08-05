<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

trait SequencedBehavior
{
    abstract public function sequence(): int;

    public function count(): int
    {
        return $this->sequence();
    }
}

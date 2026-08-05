<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

trait StringIdentifierBehavior
{
    abstract public function toString(): string;

    public function __toString(): string
    {
        return $this->toString();
    }
}

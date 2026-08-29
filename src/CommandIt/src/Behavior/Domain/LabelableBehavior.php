<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

/**
 * Provides {@see self::label()} for string-backed enums by returning the case
 * value, removing the repeated one-line body from every closed enum.
 *
 * @internal
 */
trait LabelableBehavior
{
    public function label(): string
    {
        return $this->value;
    }
}

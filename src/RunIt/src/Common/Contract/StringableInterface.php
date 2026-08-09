<?php

declare(strict_types=1);

namespace ItPack\RunIt\Common\Contract;

use Stringable;

interface StringableInterface extends Stringable
{
    /**
     * Returns the canonical string form of this object.
     */
    public function toString(): string;
}

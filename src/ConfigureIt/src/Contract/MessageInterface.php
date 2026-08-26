<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use Stringable;

interface MessageInterface extends Stringable
{
    public function toString(): string;
}

<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

use Stringable;

interface HashInterface extends Stringable
{
    public string|Stringable $algorithm {
        get;
    }
    public int $length {
        get;
    }
    public bool $binary {
        get;
    }
    public string|Stringable  $value {
        get;
    }
}
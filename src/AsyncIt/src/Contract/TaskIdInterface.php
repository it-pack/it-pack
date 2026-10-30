<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

interface TaskIdInterface extends StringableValueObjectInterface
{
    public string $value {
        get;
    }
}

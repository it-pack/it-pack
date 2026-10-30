<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Port;

use ItPack\AsyncIt\Contract\StringableValueObjectInterface;

interface StreamPollHandleInterface extends StringableValueObjectInterface
{
    public string $id {
        get;
    }
}

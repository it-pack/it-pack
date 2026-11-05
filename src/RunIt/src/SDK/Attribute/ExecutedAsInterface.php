<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

interface ExecutedAsInterface
{
    const string DEFAULT_KIND = 'operation';
    public string $name {
        get;
    }
    public string $kind {
        get;
    }
}

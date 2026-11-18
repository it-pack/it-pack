<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Support\ActivityContextStorage;

/** Synchronous, last-in-first-out activity context storage. */
final class StackActivityContextStorage extends ActivityContextStorage
{
    public function __construct()
    {
        parent::__construct(new StackContextStorage());
    }
}

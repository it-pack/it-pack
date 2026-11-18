<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Fiber;

use ItPack\RunIt\Contract\Support\ActivityContextStorage;

/** Fiber-local activity context storage. */
final class FiberActivityContextStorage extends ActivityContextStorage
{
    public function __construct()
    {
        parent::__construct(new FiberContextStorage());
    }
}

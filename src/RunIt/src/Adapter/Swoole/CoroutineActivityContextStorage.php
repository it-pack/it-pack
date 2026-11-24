<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use ItPack\RunIt\Contract\Support\ActivityContextStorage;

/** Coroutine-local activity context storage. */
final class CoroutineActivityContextStorage extends ActivityContextStorage
{
    public function __construct(CoroutineIdProviderInterface $coroutines)
    {
        parent::__construct(new CoroutineContextStorage($coroutines));
    }
}

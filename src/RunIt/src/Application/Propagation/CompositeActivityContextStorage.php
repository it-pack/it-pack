<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\RunIt\Contract\Support\ActivityContextStorage;

/** Selects a dedicated activity store for the active runtime. */
final class CompositeActivityContextStorage extends ActivityContextStorage
{
    /** All three stores must be dedicated to activity context. */
    public function __construct(
        ContextStorageInterface $synchronous,
        ContextStorageInterface $fiber,
        ContextStorageInterface $coroutine,
        RuntimeContextDetectorInterface $detector,
    ) {
        parent::__construct(new CompositeContextStorage(
            $synchronous,
            $fiber,
            $coroutine,
            $detector,
        ));
    }
}

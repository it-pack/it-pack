<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

/**
 * Marker for exceptions raised on lifecycle violations.
 *
 * Thrown when an operation attempts an illegal state transition (for example,
 * completing an already terminal execution or reopening a completed activity),
 * or when completion ordering/guard invariants are violated. Lifecycle
 * exceptions never corrupt the original business result or exception.
 */
interface LifecycleExceptionInterface extends RunItExceptionInterface
{
}

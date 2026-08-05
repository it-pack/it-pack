<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

/**
 * Marker for exceptions raised when an operation is not supported.
 *
 * Thrown when the requested behavior is intentionally unavailable in the
 * current configuration or adapter (for example, a publisher that is not
 * flushable, or an adapter that requires an optional dependency that is not
 * installed).
 */
interface UnsupportedOperationExceptionInterface extends RunItExceptionInterface
{
}

<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when a {@see \ItPack\AsyncIt\Domain\PersistentTask} cannot be
 * constructed, validated, or rehydrated from persistence.
 *
 * @see https://w3id.org/itpack/asyncit#PersistentTask
 */
final class PersistentTaskException extends RuntimeException implements AsyncItExceptionInterface
{
}

<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when a task group's backpressure policy is set to {@see \ItPack\AsyncIt\Domain\BackpressurePolicy::Error}
 * and the max-concurrency limit is exceeded.
 *
 * @see https://w3id.org/itpack/asyncit#BackpressurePolicy
 * @see https://w3id.org/itpack/asyncit-taxonomy#backpressure-error
 */
final class BackpressureExceededException extends RuntimeException implements AsyncItExceptionInterface
{
}

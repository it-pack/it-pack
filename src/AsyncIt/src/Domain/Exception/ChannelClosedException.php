<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when an operation targets a channel that has been closed.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 */
final class ChannelClosedException extends RuntimeException implements AsyncItExceptionInterface
{
}

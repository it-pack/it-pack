<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when sending to a bounded channel that has reached capacity.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 */
final class ChannelFullException extends RuntimeException implements AsyncItExceptionInterface
{
}

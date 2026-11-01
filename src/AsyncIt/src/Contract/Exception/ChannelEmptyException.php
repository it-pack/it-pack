<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when a value is received from an empty channel.
 *
 * @see https://w3id.org/itpack/asyncit#TaskChannel
 */
final class ChannelEmptyException extends RuntimeException implements AsyncItExceptionInterface
{
}

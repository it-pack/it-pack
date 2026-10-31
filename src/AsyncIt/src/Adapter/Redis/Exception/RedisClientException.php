<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Redis\Exception;

use RuntimeException;

/**
 * Thrown by a {@see \ItPack\AsyncIt\Adapter\Redis\RedisClientInterface}
 * implementation when a Redis operation fails.
 */
final class RedisClientException extends RuntimeException
{
}

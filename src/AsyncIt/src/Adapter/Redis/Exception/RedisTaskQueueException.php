<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Redis\Exception;

use RuntimeException;

/**
 * Thrown by {@see \ItPack\AsyncIt\Adapter\Redis\RedisTaskQueue} when a
 * persistent task queue operation fails due to a Redis client error.
 */
final class RedisTaskQueueException extends RuntimeException
{
}

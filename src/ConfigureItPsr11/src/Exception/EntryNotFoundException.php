<?php

declare(strict_types=1);

namespace ItPack\ConfigureItPsr11\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a PSR-11 entry is requested that the container does not expose.
 *
 * ConfigureIt intentionally exposes a single, well-known entry (the resolved
 * {@see \ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface}); anything
 * else is out of scope and therefore "not found".
 */
final class EntryNotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
}

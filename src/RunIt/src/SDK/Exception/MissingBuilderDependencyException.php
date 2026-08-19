<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use LogicException;

/**
 * Thrown when {@see \ItPack\RunIt\SDK\Builder\RunItBuilderInterface::build()} is called before a
 * required dependency has been configured.
 *
 * The message names the missing dependency so the caller can supply it through the matching
 * `with*` method. This is a programming error, not a runtime failure.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class MissingBuilderDependencyException extends LogicException implements RunItExceptionInterface
{
    public static function forDependency(string $dependency): self
    {
        return new self(sprintf('Cannot build RunIt runtime: missing required dependency "%s".', $dependency));
    }
}

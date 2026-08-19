<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use LogicException;

/**
 * Thrown when an {@see \ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface} is invoked without a
 * resolvable execution definition and no {@see \ItPack\RunIt\SDK\Attribute\ExecutedAs} attribute.
 *
 * Wrap the callable with either a static {@see \ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface}
 * or a definition factory, or attach an {@see \ItPack\RunIt\SDK\Attribute\ExecutedAs} attribute.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class MissingExecutionDefinitionException extends LogicException implements RunItExceptionInterface
{
}

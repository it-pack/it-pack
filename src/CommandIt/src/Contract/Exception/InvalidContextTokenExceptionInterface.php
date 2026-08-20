<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Exception;

/**
 * Marker for context-token violations (mismatched or already-detached tokens).
 *
 * @api
 */
interface InvalidContextTokenExceptionInterface extends CommandExceptionInterface
{
}

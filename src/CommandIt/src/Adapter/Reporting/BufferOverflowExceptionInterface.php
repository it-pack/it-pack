<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Exception\CommandExceptionInterface;

/**
 * Marker for a bounded reporter buffer that overflows under {@see OverflowPolicy::Throw}.
 *
 * Lets consumers catch buffer overflow precisely without coupling to the
 * concrete {@see BufferOverflowException} class.
 *
 * @api
 */
interface BufferOverflowExceptionInterface extends CommandExceptionInterface
{
}

<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Exception;

/**
 * Thrown when snapshot payload data cannot be normalized into a safely-serializable form.
 *
 * @api
 */
interface SerializationExceptionInterface extends RunItExceptionInterface
{
}

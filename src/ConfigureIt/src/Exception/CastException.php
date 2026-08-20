<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Exception;

/**
 * Thrown when a value cannot be coerced to its declared type by a processor.
 */
class CastException extends ConfigurationException
{
}

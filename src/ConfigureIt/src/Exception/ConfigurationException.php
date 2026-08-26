<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Exception;

use RuntimeException;

/**
 * Marker base exception for all ConfigureIt domain errors.
 *
 * Domain exceptions live in Core. Vendor exceptions must be translated into
 * these by adapters and never leaked (see AGENTS.md "Exceptions").
 */
class ConfigurationException extends RuntimeException
{
}

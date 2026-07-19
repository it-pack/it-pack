<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\ValueObject\ValidationResult;

/**
 * Defines constraints for configuration and validates a graph against them.
 *
 * Schemas validate documents/graphs; they do not load configuration. The
 * preferred implementation is Nette Schema behind this port.
 *
 * @see docs/architecture/MODEL.md "Configuration Schema"
 */
interface ConfigurationSchemaInterface
{
    public function validate(ConfigurationGraphInterface $graph): ValidationResult;
}

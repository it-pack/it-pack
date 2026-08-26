<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;

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
    /**
     * A short, stable identifier for this schema (e.g. "nette-schema").
     *
     * Replaces runtime {@see get_class()} discrimination so compiled-cache
     * fingerprints stay stable across refactors.
     */
    public function name(): string;

    public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface;
}

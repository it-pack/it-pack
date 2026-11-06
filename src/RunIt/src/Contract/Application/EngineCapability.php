<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

/**
 * Immutable description of what an execution engine supports.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class EngineCapability implements EngineCapabilityInterface
{
    public function __construct(
        public bool $supportsFibers,
        public bool $supportsCoroutines,
        public bool $concurrent,
    ) {
    }
}

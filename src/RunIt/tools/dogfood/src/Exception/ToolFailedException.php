<?php

declare(strict_types=1);

namespace Tools\Dogfood\Exception;

use RuntimeException;

/**
 * Raised inside a dogfood execution when the wrapped dev tool exits non-zero.
 *
 * It carries the tool name and exit code so the runner can record the execution
 * as failed and still surface the captured artifacts (occurrence, snapshot) that
 * were emitted before the throw. It is not a RunIt domain exception: dogfooding
 * is tooling, not the execution model being observed.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ToolFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $tool,
        public readonly int $exitCode,
    ) {
        parent::__construct(sprintf('Dogfood tool "%s" exited with code %d.', $tool, $exitCode));
    }
}

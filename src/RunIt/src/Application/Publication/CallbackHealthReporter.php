<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Health reporter that forwards every failure to a callback.
 *
 * Used by tests and by applications that collect internal publication failures
 * (for example into a metrics counter) without touching the guarded publisher.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class CallbackHealthReporter implements InternalHealthReporterInterface
{
    /**
     * @param \Closure $callback
     */
    public function __construct(
        private readonly \Closure $callback,
    ) {
    }

    public function report(\Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        ($this->callback)($error, $artifact);
    }
}

<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use Throwable;

/**
 * Health reporter that forwards every failure to a callback.
 *
 * Used by tests and by applications that collect internal publication failures
 * (for example into a metrics counter) without touching the guarded publisher.
 *
 * The callback is userland code and may throw. A throwing callback is contained
 * here: the primary failure plus the handler fault land in the fallback sink,
 * and nothing escapes into publication or business flow (Phase 0, T0.1).
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CallbackHealthReporter implements InternalHealthReporterInterface
{
    private readonly FallbackErrorLogReporter $fallback;

    /**
     * @param Closure(\Throwable, ExecutionArtifactInterface): void $callback
     */
    public function __construct(
        private Closure $callback,
        ?FallbackErrorLogReporter $fallback = null,
    ) {
        $this->fallback = $fallback ?? new FallbackErrorLogReporter();
    }

    public function report(\Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        try {
            ($this->callback)($error, $artifact);
        } catch (Throwable $handlerFault) {
            $id = $artifact->normalize()[ArtifactField::ID] ?? null;

            $this->fallback->report($error, $handlerFault, is_string($id) ? $id : null);
        }
    }
}

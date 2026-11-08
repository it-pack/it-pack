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
 * A throwing callback is forwarded to the fallback sink along with the primary
 * failure. Artifact normalization or the fallback sink itself can still throw.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CallbackHealthReporter implements InternalHealthReporterInterface
{
    private FallbackErrorLogReporter $fallback;

    /**
     * @param Closure(Throwable, ExecutionArtifactInterface): void $callback
     */
    public function __construct(
        private Closure $callback,
        ?FallbackErrorLogReporter $fallback = null,
    ) {
        $this->fallback = $fallback ?? new FallbackErrorLogReporter();
    }

    public function report(Throwable $error, ExecutionArtifactInterface $artifact): void
    {
        try {
            ($this->callback)($error, $artifact);
        } catch (Throwable $throwable) {
            $id = $artifact->normalize()[ArtifactField::ID] ?? null;

            $this->fallback->report($error, $throwable, is_string($id) ? $id : null);
        }
    }
}

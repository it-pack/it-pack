<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Throwable;

/**
 * Health reporter that forwards every failure to a callback.
 *
 * Used by tests and by applications that collect internal publication failures
 * (for example into a metrics counter) without touching the guarded publisher.
 *
 * A throwing callback triggers a payload-free fallback diagnostic. If that
 * sink fails, a separate system-log diagnostic is attempted and contained.
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
        } catch (Throwable) {
            try {
                $this->fallback->report(
                    new RuntimeException('Publication health reporting failed'),
                    new RuntimeException('Health callback failed'),
                );
            } catch (Throwable) {
                $this->reportLastResort();
            }
        }
    }

    private function reportLastResort(): void
    {
        try {
            new FallbackErrorLogReporter()->report(
                new RuntimeException('Publication health reporting failed'),
                new RuntimeException('Fallback failure'),
            );
        } catch (Throwable) {
            // No further independent sink remains; preserve the business outcome.
        }
    }
}

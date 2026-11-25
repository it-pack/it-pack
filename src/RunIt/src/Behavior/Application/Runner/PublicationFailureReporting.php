<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Application\Runner;

use Closure;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use RuntimeException;
use Throwable;

/**
 * Guards publication-failure reporting so it can never mask the business outcome.
 *
 * A run may fail before, during, or after publication. Each failure path reports
 * through a single guarded entry point that prevents recursive reporting, falls
 * back from a throwing callback to the error log, and falls back from a throwing
 * error-log reporter to an independent payload-free fallback notice.
 *
 * The using class must declare:
 *  - `private ?Closure $onPublicationFailure`
 *  - `private ThrowableMapperInterface $throwableMapper`
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
trait PublicationFailureReporting
{
    private bool $reporting = false;

    /**
     * Reports a publication failure without ever rethrowing or masking the caller.
     *
     * While a report is in progress, subsequent failures route straight to the
     * fallback reporter so reporting can never recurse or replace the result.
     */
    protected function reportFailure(Throwable $failure, ?ExecutionArtifactInterface $artifact = null): void
    {
        if ($this->reporting) {
            $this->reportPublicationFailureToFallback();

            return;
        }

        $this->reporting = true;
        try {
            $this->reportFailureOnce($failure, $artifact);
        } finally {
            $this->reporting = false;
        }
    }

    private function reportFailureOnce(Throwable $failure, ?ExecutionArtifactInterface $artifact): void
    {
        if ($this->onPublicationFailure instanceof Closure) {
            try {
                ($this->onPublicationFailure)($failure);

                return;
            } catch (Throwable) {
                $this->reportPublicationFailureToFallback();

                return;
            }
        }

        // No callback configured: report to the PHP error log so a broken
        // publisher stays observable even when constructed without a handler.
        $this->reportToDefaultErrorLog($failure, $artifact);
    }

    private function reportToDefaultErrorLog(Throwable $failure, ?ExecutionArtifactInterface $artifact): void
    {
        $artifact ??= $this->placeholderArtifact();

        try {
            new ErrorLogHealthReporter($this->throwableMapper)->report($failure, $artifact);
        } catch (Throwable) {
            $this->reportPublicationFailureToFallback();
        }
    }

    private function reportPublicationFailureToFallback(): void
    {
        try {
            new FallbackErrorLogReporter()->report(
                new RuntimeException(FallbackErrorLogReporter::FALLBACK_PRIMARY_MESSAGE),
                new RuntimeException(FallbackErrorLogReporter::FALLBACK_HANDLER_FAULT_MESSAGE),
            );
        } catch (Throwable) {
            // No further independent sink remains; preserve the business outcome.
        }
    }

    private function placeholderArtifact(): ExecutionArtifactInterface
    {
        return new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        };
    }
}

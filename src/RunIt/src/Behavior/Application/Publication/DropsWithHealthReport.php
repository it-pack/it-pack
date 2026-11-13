<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Application\Publication;

use ItPack\RunIt\Application\Factory\RedactedRedactedArtifactFactory;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use RuntimeException;
use Throwable;

trait DropsWithHealthReport
{
    private bool $reporting = false;

    protected function reportDrop(string $message): void
    {
        if ($this->reporting) {
            return;
        }

        $this->tryReportDrop($message);
    }

    /** @return array<string, mixed>|null */
    protected function safeThrowable(mixed $value, int $maxDepth = 16, int $depth = 0): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if ($depth >= $maxDepth
            || !is_string($value['class'] ?? null)
            || !is_int($value['code'] ?? null)
            || strlen($value['class']) > 256
            || preg_match('/\A[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*\z/', $value['class']) !== 1
        ) {
            return null;
        }

        return [
            'class' => $value['class'],
            'message' => '[redacted]',
            'code' => $value['code'],
            'previous' => $this->safeThrowable($value['previous'] ?? null, $maxDepth, $depth + 1),
            'trace' => null,
        ];
    }

    private function reportHealth(InternalHealthReporterInterface $health, RuntimeException $error): void
    {
        $factory = new RedactedRedactedArtifactFactory(ArtifactType::UNKNOWN);
        $health->report(
            $error,
            $factory->create([
                'id' => 'redacted', 'type' => 'runit.artifact.redacted',
            ])
        );
    }

    private function reportFallback(
        FallbackErrorLogReporter $reporter,
        RuntimeException $primary,
        string $message
    ): void {
        $reporter->report(
            $primary,
            new RuntimeException($message)
        );
    }

    private function tryReportDrop(string $message): void
    {
        $error = new RuntimeException($message);
        try {
            $this->reporting = true;
            if ($this->health instanceof InternalHealthReporterInterface) {
                $this->reportHealth($this->health, $error);

                return;
            }

            static $reporter;
            if (!$reporter instanceof FallbackErrorLogReporter) {
                $reporter = new FallbackErrorLogReporter();
            }

            $this->reportFallback($reporter, $error, 'no health reporter configured');
        } catch (Throwable) {
            try {
                static $fallbackReporter;
                if (!$fallbackReporter instanceof FallbackErrorLogReporter) {
                    $fallbackReporter = new FallbackErrorLogReporter();
                }

                $this->reportFallback($fallbackReporter, $error, 'health reporter failed');
            } catch (Throwable) {
                // No independent reporting path remains.
            }
        } finally {
            $this->reporting = false;
        }
    }
}

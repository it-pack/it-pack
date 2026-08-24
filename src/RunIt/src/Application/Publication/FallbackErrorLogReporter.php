<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use Throwable;

/**
 * Last-resort sink used when a configured health callback itself fails.
 *
 * Health callbacks are userland code; nothing prevents them from throwing. If a
 * callback threw while reporting a publication failure and that fault escaped,
 * it would replace the original business outcome — the exact defect the health
 * path exists to prevent. This reporter therefore writes a single bounded line
 * describing both the primary failure and the handler fault to the PHP error
 * log (or an injected sink in tests) and never rethrows.
 *
 * Declared without an interface deliberately: the safety net must never be
 * swapped out by configuration, so exposing a contract would only invite it.
 * Tracked as a permanent `require-interface` exemption in `docs/QA.md`.
 *
 * @internal safety net for health-callback faults; not a public contract.
 */
final readonly class FallbackErrorLogReporter
{
    /**
     * Maximum bytes of any single message segment inside the fallback line.
     */
    private const int MAX_SEGMENT_BYTES = 256;

    private const string LINE_PREFIX = '[runit] publication health fallback: ';

    private readonly Closure $logTo;

    /**
     * @param Closure(string): void|null $logTo injectable destination; defaults to the PHP error log
     */
    public function __construct(?Closure $logTo = null)
    {
        $this->logTo = $logTo ?? static function (string $line): void {
            error_log($line);
        };
    }

    /**
     * Records a primary failure plus the handler fault that occurred while
     * reporting it. Never throws.
     */
    public function report(Throwable $primary, Throwable $handlerFault, ?string $artifactId = null): void
    {
        ($this->logTo)(sprintf(
            '%s handler fault [%s] %s while reporting [%s] %s%s',
            self::LINE_PREFIX,
            $handlerFault::class,
            $this->segment($handlerFault->getMessage()),
            $primary::class,
            $this->segment($primary->getMessage()),
            $artifactId === null ? '' : ' artifact=' . $this->segment($artifactId),
        ));
    }

    private function segment(string $message): string
    {
        return substr($message, 0, self::MAX_SEGMENT_BYTES);
    }
}

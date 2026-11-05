<?php /** @noinspection ForgottenDebugOutputInspection */

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

    private const string MESSAGE_TEMPLATE = '%s handler fault [%s] %s while reporting [%s] %s%s';

    private const string ARTIFACT_TEMPLATE = 'artifact=%s';

    /** @var int @see https://www.php.net/manual/en/function.error-log.php */
    private const int DEFAULT_LOG_TYPE = 0;

    private Closure $logTo;

    /**
     * @param Closure(string): void|null $logTo injectable destination; defaults to the PHP error log
     */
    public function __construct(?Closure $logTo = null)
    {
        $this->logTo = $logTo ?? self::systemLog(...);
    }

    /**
     * Records a primary failure plus the handler fault that occurred while
     * reporting it. Never throws.
     */
    public function report(Throwable $primary, Throwable $handlerFault, ?string $artifactId = null): void
    {
        ($this->logTo)(
            sprintf(
                self::MESSAGE_TEMPLATE,
                self::LINE_PREFIX,
                $handlerFault::class,
                $this->segment(
                    $handlerFault->getMessage()
                ),
                $primary::class,
                $this->segment(
                    $primary->getMessage()
                ),
                $artifactId === null
                    ? ''
                    : sprintf(
                        self::ARTIFACT_TEMPLATE,
                        $this->segment($artifactId)
                    ),
            )
        );
    }

    private function segment(string $message): string
    {
        return substr($message, 0, self::MAX_SEGMENT_BYTES);
    }

    /**
     * Sends the error message to the defined error handling routines
     * @see https://www.php.net/manual/en/function.error-log.php
     */
    private static function systemLog(string $message): void
    {
        error_log(
            $message,
            self::DEFAULT_LOG_TYPE,
        );
    }
}

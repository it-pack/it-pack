<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use Throwable;

/**
 * Contract for last-resort error-log reporting when health callbacks fail.
 *
 * @internal safety net for health-callback faults; not a public contract.
 */
interface FallbackErrorLogReporterInterface
{
    /**
     * Records a primary failure plus the handler fault that occurred while
     * reporting it. Exceptions from an injected log destination can propagate.
     */
    public function report(Throwable $primary, Throwable $handlerFault, ?string $artifactId = null): void;
}

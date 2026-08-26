<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\ValueObject\DiagnosticsReportInterface;

/**
 * Produces a read-only diagnostics report for a resolved graph.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Core\ConfigurationDiagnostics} so
 * observability tooling depends on the contract (ADR-0008).
 */
interface ConfigurationDiagnosticsInterface
{
    public function report(ConfigurationGraphInterface $graph): DiagnosticsReportInterface;
}

<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Reporting;

/**
 * SARIF-reporting execution recorder (ADR-0011). Collects the
 * `disenchant.it.*.v1` occurrences Core emits and renders them as a SARIF 2.1.0
 * log via {@see toSarif()}.
 */
interface SarifRecorderInterface extends ReportRecorderInterface
{
    public function toSarif(): string;
}

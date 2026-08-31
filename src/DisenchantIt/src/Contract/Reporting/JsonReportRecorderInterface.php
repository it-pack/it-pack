<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Reporting;

/**
 * JSON-reporting execution recorder (ADR-0011). Collects the
 * `disenchant.it.*.v1` occurrences Core emits and renders them as a JSON run
 * report via {@see toJson()}.
 */
interface JsonReportRecorderInterface extends ReportRecorderInterface
{
    public function toJson(): string;
}

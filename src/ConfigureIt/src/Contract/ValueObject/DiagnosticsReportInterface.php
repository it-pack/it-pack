<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

/**
 * Structural contract for a configuration diagnostics report.
 *
 * Mirrors {@see \ItPack\ConfigureIt\ValueObject\DiagnosticsReport}; extracted so
 * observability consumers depend on the contract rather than the concrete value
 * object.
 */
interface DiagnosticsReportInterface
{
    public function hasOverrides(): bool;

    public function hasUnresolved(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function summary(): string;
}

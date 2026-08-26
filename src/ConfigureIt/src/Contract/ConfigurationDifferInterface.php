<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationDiffInterface;

/**
 * Computes a semantic diff between two resolved configuration graphs.
 *
 * Extracted from {@see \ItPack\ConfigureIt\Core\ConfigurationDiffer} so
 * observability/diagnostics tooling depends on the contract (ADR-0008).
 */
interface ConfigurationDifferInterface
{
    public function diff(
        ConfigurationGraphInterface $base,
        ConfigurationGraphInterface $other,
    ): ConfigurationDiffInterface;

    public function summarize(ConfigurationDiffInterface $diff): string;
}

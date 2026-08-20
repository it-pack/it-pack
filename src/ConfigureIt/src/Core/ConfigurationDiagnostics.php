<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\ValueObject\DiagnosticsReport;

/**
 * Computes {@see DiagnosticsReport} from a resolved graph and, optionally, emits
 * {@see Observation}s through the resolution reporter so overrides and unresolved
 * variables are observable without a CLI (Roadmap Phase 6). Reuses the Execution
 * observation spine described in docs/EXECUTION.md.
 *
 * @see docs/architecture/GRAPH.md "Diagnostics"
 */
final readonly class ConfigurationDiagnostics
{
    public function __construct(
        private ?ResolutionReporterInterface $reporter = null,
    ) {
    }

    public function report(ConfigurationGraphInterface $graph): DiagnosticsReport
    {
        $overrides = [];
        $bySource = [];
        $byFormat = [];
        $unresolved = [];

        foreach ($graph->nodes() as $key => $node) {
            $provenance = $node->provenance();

            $bySource[$provenance->resourceUri()] = ($bySource[$provenance->resourceUri()] ?? 0) + 1;
            $byFormat[$provenance->format()->name] = ($byFormat[$provenance->format()->name] ?? 0) + 1;

            if ($provenance->overridden()) {
                $overrides[] = [
                    'key'      => $key,
                    'source'   => $provenance->resourceUri(),
                    'shadowed' => $provenance->overriddenSource(),
                ];

                $this->reporter?->observe(new Observation(
                    'info',
                    sprintf('Key "%s" resolved from "%s" overrode "%s".', $key, $provenance->resourceUri(), $provenance->overriddenSource() ?? 'unknown'),
                    ['key' => $key, 'source' => $provenance->resourceUri(), 'shadowed' => $provenance->overriddenSource()],
                ));
            }

            $value = $node->value();

            if (\is_string($value) && \str_contains($value, '${')) {
                $unresolved[] = $key;

                $this->reporter?->observe(new Observation(
                    'warning',
                    sprintf('Key "%s" still contains an unresolved variable placeholder.', $key),
                    ['key' => $key],
                ));
            }
        }

        return new DiagnosticsReport($overrides, $bySource, $byFormat, $unresolved);
    }
}

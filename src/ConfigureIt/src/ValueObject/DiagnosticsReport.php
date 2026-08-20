<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

/**
 * A structured, read-only report of a resolved configuration graph.
 *
 * Surfaces provenance-driven diagnostics: which sources overrode which, how many
 * nodes originated from each source/format, and any unresolved `${...}` variables
 * that survived resolution. Powers CLI explain/diff tooling (Roadmap Phase 6).
 *
 * @see docs/architecture/GRAPH.md "Diagnostics"
 */
final readonly class DiagnosticsReport
{
    /**
     * @param list<array{key:string, source:string, shadowed:?string}> $overrides
     * @param array<string, int> $bySource
     * @param array<string, int> $byFormat
     * @param list<string> $unresolved
     */
    public function __construct(
        public array $overrides,
        public array $bySource,
        public array $byFormat,
        public array $unresolved,
    ) {
    }

    public function hasOverrides(): bool
    {
        return $this->overrides !== [];
    }

    public function hasUnresolved(): bool
    {
        return $this->unresolved !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overrides' => $this->overrides,
            'bySource'  => $this->bySource,
            'byFormat'  => $this->byFormat,
            'unresolved' => $this->unresolved,
        ];
    }

    public function summary(): string
    {
        $lines = ['Configuration diagnostics:'];

        $lines[] = sprintf('  nodes by source: %s', $this->bySource === [] ? '(none)' : json_encode($this->bySource));
        $lines[] = sprintf('  nodes by format: %s', $this->byFormat === [] ? '(none)' : json_encode($this->byFormat));

        if ($this->overrides === []) {
            $lines[] = '  overrides: none';
        } else {
            $lines[] = '  overrides:';
            foreach ($this->overrides as $override) {
                $lines[] = sprintf(
                    '    %s <- %s (overrode %s)',
                    $override['key'],
                    $override['source'],
                    $override['shadowed'] ?? 'unknown',
                );
            }
        }

        if ($this->unresolved !== []) {
            $lines[] = '  unresolved variables: ' . implode(', ', $this->unresolved);
        }

        return implode("\n", $lines);
    }
}

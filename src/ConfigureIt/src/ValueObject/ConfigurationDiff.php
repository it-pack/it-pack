<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use function basename;
use function is_scalar;

use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationDiffInterface;

/**
 * A semantic diff between two resolved configuration graphs.
 *
 * Distinguishes added, removed and changed keys, and attaches the provenance of
 * both sides for every changed key so consumers can explain *why* a value moved
 * (e.g. a higher-priority source overrode a lower one). Powers configuration
 * diff tooling (Roadmap Phase 6).
 *
 * @see docs/architecture/GRAPH.md "Graph Diffing"
 */
final readonly class ConfigurationDiff implements ConfigurationDiffInterface
{
    /**
     * @param array<string, mixed> $added
     * @param array<string, mixed> $removed
     * @param list<array{
     *     key:string,
     *     from:mixed,
     *     to:mixed,
     *     fromProvenance:array<string, mixed>,
     *     toProvenance:array<string, mixed>,
     *     sensitive:bool,
     * }> $changed
     * @param array<string, bool> $addedSensitive dotted key => is secret
     * @param array<string, bool> $removedSensitive dotted key => is secret
     */
    public function __construct(
        public array $added,
        public array $removed,
        public array $changed,
        public array $addedSensitive = [],
        public array $removedSensitive = [],
    ) {
    }

    public function empty(): bool
    {
        return [] === $this->added && [] === $this->removed && [] === $this->changed;
    }

    /**
     * @return array<string, mixed>
     */
    public function added(): array
    {
        return $this->added;
    }

    /**
     * @return array<string, mixed>
     */
    public function removed(): array
    {
        return $this->removed;
    }

    /**
     * @return list<array{
     *     key:string,
     *     from:mixed,
     *     to:mixed,
     *     fromProvenance:array<string, mixed>,
     *     toProvenance:array<string, mixed>,
     *     sensitive:bool,
     * }>
     */
    public function changed(): array
    {
        return $this->changed;
    }

    /**
     * @return array<string, bool>
     */
    public function addedSensitive(): array
    {
        return $this->addedSensitive;
    }

    /**
     * @return array<string, bool>
     */
    public function removedSensitive(): array
    {
        return $this->removedSensitive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'added'   => $this->added,
            'removed' => $this->removed,
            'changed' => $this->changed,
        ];
    }

    public function summary(): string
    {
        if ($this->empty()) {
            return 'Configuration graphs are identical.';
        }

        $lines = ['Configuration diff:'];

        if ([] !== $this->added) {
            $lines[] = '  added:';
            foreach ($this->added as $key => $value) {
                $lines[] = sprintf('    + %s = %s', $key, $this->display($value));
            }
        }

        if ([] !== $this->removed) {
            $lines[] = '  removed:';
            foreach ($this->removed as $key => $value) {
                $lines[] = sprintf('    - %s = %s', $key, $this->display($value));
            }
        }

        if ([] !== $this->changed) {
            $lines[] = '  changed:';
            foreach ($this->changed as $entry) {
                $toSrc = is_scalar($entry['toProvenance']['resourceUri'] ?? null)
                    ? basename((string) $entry['toProvenance']['resourceUri'])
                    : '?';
                $fromSrc = is_scalar($entry['fromProvenance']['resourceUri'] ?? null)
                    ? basename((string) $entry['fromProvenance']['resourceUri'])
                    : '?';

                $lines[] = sprintf(
                    '    ~ %s: %s -> %s (%s overrode %s)',
                    $entry['key'],
                    $this->display($entry['from']),
                    $this->display($entry['to']),
                    $toSrc,
                    $fromSrc,
                );
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param mixed $value the (heterogeneous) changed value to render for display.
     */
    private function display(mixed $value): string
    {
        return (string) json_encode($value);
    }
}

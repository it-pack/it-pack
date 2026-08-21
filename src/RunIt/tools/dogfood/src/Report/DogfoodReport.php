<?php

declare(strict_types=1);

namespace Tools\Dogfood\Report;

use Tools\Dogfood\Tool\DevTool;

/**
 * Aggregates dogfood tool outcomes into a single report.
 *
 * The report exit code is non-zero when any tool failed, matching the convention
 * that CI gates on. Rendering stays plain and dependency-free so the bootstrap is
 * intentionally thin.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DogfoodReport
{
    /**
     * @param list<ToolOutcome> $outcomes
     */
    public function __construct(
        public readonly array $outcomes,
    ) {
    }

    public function exitCode(): int
    {
        foreach ($this->outcomes as $outcome) {
            if (!$outcome->succeeded()) {
                return 1;
            }
        }

        return 0;
    }

    public function succeededCount(): int
    {
        return count(array_filter($this->outcomes, static fn (ToolOutcome $o): bool => $o->succeeded()));
    }

    public function failed(): array
    {
        return array_values(array_filter($this->outcomes, static fn (ToolOutcome $o): bool => !$o->succeeded()));
    }

    public function render(): string
    {
        $lines = ['RunIt dogfood report'];

        foreach ($this->outcomes as $outcome) {
            $status = $outcome->succeeded() ? 'ok' : 'FAILED';
            $duration = number_format($outcome->result->durationMicroseconds / 1000, 1);
            $lines[] = sprintf(
                '  [%s] %s (exit=%d, %s ms)',
                $status,
                $outcome->tool->name,
                $outcome->result->exitCode,
                $duration,
            );
        }

        $lines[] = sprintf('summary: %d/%d tools passed.', $this->succeededCount(), count($this->outcomes));

        return implode(PHP_EOL, $lines);
    }
}

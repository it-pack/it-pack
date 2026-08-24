<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Cli;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;

/**
 * Human-readable telemetry sink for `--verbose` CLI runs: every fact becomes a
 * single `derive: …` diagnostics line on stderr, leaving the stdout derivation
 * output byte-identical to a non-verbose run.
 *
 * Delivery adapter, not domain logic — it exists so operators can watch the
 * pipeline stages live without wiring a full execution backend.
 */
final readonly class DeriveConsoleSink implements DeriveEventSinkInterface
{
    /**
     * @param \Closure(string):void $write diagnostics receiver (stderr in production)
     */
    public function __construct(
        private \Closure $write,
    ) {
    }

    public function activityStarted(string $name, array $attributes = []): void
    {
        ($this->write)($this->line('activity', $name . ' started', $attributes));
    }

    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
        ($this->write)($this->line('activity', $name . ' done' . ($successful ? '' : ' (failed)'), $attributes));
    }

    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
        ($this->write)($this->line('measure', sprintf('%s = %s %s', $name, $value, $unit), $attributes));
    }

    public function observed(string $message, DeriveObservationSeverity $severity, array $attributes = []): void
    {
        ($this->write)('derive: [' . $this->marker($severity) . '] ' . $message . $this->suffix($attributes));
    }

    public function occurred(string $type, array $attributes = []): void
    {
        ($this->write)($this->line('event', $type, $attributes));
    }

    /**
     * One-character severity marker so diagnostics scan quickly in a terminal.
     */
    private function marker(DeriveObservationSeverity $severity): string
    {
        return match ($severity) {
            DeriveObservationSeverity::DEBUG => '.',
            DeriveObservationSeverity::INFO => 'i',
            DeriveObservationSeverity::WARNING => '!',
            DeriveObservationSeverity::ERROR => 'x',
        };
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    private function line(string $tag, string $message, array $attributes): string
    {
        return 'derive: [' . $tag . '] ' . $message . $this->suffix($attributes);
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    private function suffix(array $attributes): string
    {
        return $attributes === [] ? '' : ' ' . (string) json_encode($attributes);
    }
}

<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Contract\Execution\ObservationInterface;

/**
 * A qualitative observation made during configuration resolution.
 *
 * Observations answer "what did we notice?" (warnings, diagnostics, debug
 * information). Mirrors docs/EXECUTION.md "Observation"; PSR-3 logs are one
 * possible external representation (an adapter concern).
 */
final readonly class Observation implements ObservationInterface
{
    /**
     * @param array<string, mixed> $context structured detail about the observation.
     */
    public function __construct(
        private string $level,
        private string $message,
        private array $context = [],
    ) {
    }

    public function level(): string
    {
        return $this->level;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}

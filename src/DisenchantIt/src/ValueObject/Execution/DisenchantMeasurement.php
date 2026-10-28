<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * A quantified measurement of work (e.g. detected literal count, rewrite
 * duration) with a stable name and unit.
 */
final readonly class DisenchantMeasurement implements ExecutionArtifactInterface
{
    // @phpstan-ignore shipmonk.deadMethod
    public function __construct(
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public string $name,
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public int|float $value,
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public string $unit = '',
    ) {
    }

    public function kind(): string
    {
        return 'disenchant.it.measurement.v1';
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'kind' => $this->kind(),
            'name' => $this->name,
            'value' => $this->value,
            'unit' => $this->unit,
        ];
    }
}

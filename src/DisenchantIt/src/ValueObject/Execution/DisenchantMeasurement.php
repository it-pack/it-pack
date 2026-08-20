<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * A quantified measurement of work (e.g. detected literal count, rewrite
 * duration) with a stable name and unit.
 */
final readonly class DisenchantMeasurement implements ExecutionArtifactInterface
{
    public function __construct(
        public string $name,
        public int|float $value,
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

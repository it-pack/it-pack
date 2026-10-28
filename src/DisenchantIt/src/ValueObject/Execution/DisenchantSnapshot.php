<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * Immutable, deterministically serializable capture of a run's result. Preserves
 * the business outcome so a future adapter can publish/transport it without
 * re-running detection. Serialization is bounded and never includes arbitrary
 * PHP objects.
 */
final readonly class DisenchantSnapshot implements ExecutionArtifactInterface
{
    /**
     * @param array<string, mixed> $data
     */
    // @phpstan-ignore shipmonk.deadMethod
    public function __construct(
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public array $data,
    ) {
    }

    public function kind(): string
    {
        return 'disenchant.it.snapshot.v1';
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'kind' => $this->kind(),
            'data' => $this->data,
        ];
    }
}

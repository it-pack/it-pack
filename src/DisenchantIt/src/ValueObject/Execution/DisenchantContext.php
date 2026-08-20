<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * Immutable run context for one DisenchantIt execution. Context never mutates;
 * a change produces a new context (the It_Pack execution model: "context is
 * immutable; changes produce a new context").
 */
final readonly class DisenchantContext implements ExecutionArtifactInterface
{
    public function __construct(
        public string $seed,
        public int $startedAt,
    ) {
    }

    public function kind(): string
    {
        return 'disenchant.it.context.v1';
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'kind' => $this->kind(),
            'seed' => $this->seed,
            'startedAt' => $this->startedAt,
        ];
    }
}

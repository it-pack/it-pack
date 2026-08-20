<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * An observation (PSR-3 style description of work) emitted during a run.
 * Kept separate from occurrences: observations describe, occurrences record.
 */
final readonly class DisenchantObservation implements ExecutionArtifactInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context = [],
    ) {
    }

    public function kind(): string
    {
        return 'disenchant.it.observation.v1';
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'kind' => $this->kind(),
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}

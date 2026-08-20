<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

/**
 * A discrete, immutable fact recorded during a run: an event with a stable,
 * lowercase, versioned name (e.g. "disenchant.it.magic_value.detected.v1") and
 * an optional payload. Name collisions with differing meaning are avoided by
 * versioning the name, not the PHP class.
 */
final readonly class DisenchantOccurrence implements ExecutionArtifactInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $name,
        public array $payload = [],
    ) {
    }

    public function kind(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'kind' => $this->kind(),
            'payload' => $this->payload,
        ];
    }
}

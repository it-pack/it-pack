<?php

declare(strict_types=1);

namespace ItPack\CommitItRunit\Model;

/**
 * Represents a RunIt Snapshot / Measurement — emitted on successful completion.
 *
 * @api
 */
final readonly class Snapshot
{
    /**
     * @param non-empty-string $name        Dotted operation identifier.
     * @param array<string, mixed> $context Enriched operational context.
     * @param non-empty-string $activityId  Unique activity identifier.
     */
    public function __construct(
        public string $name,
        public array $context,
        public string $activityId,
    ) {
    }
}

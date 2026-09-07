<?php

declare(strict_types=1);

namespace ItPack\CommitItRunit\Model;

/**
 * Represents a RunIt Occurrence — emitted on operation failure.
 *
 * @api
 */
final readonly class Occurrence
{
    /**
     * @param non-empty-string $name        Dotted operation identifier.
     * @param array<string, mixed> $context Enriched operational context.
     * @param array{class: class-string, message: string, code: int} $error Structured error data.
     * @param non-empty-string $activityId  Unique activity identifier.
     */
    public function __construct(
        public string $name,
        public array $context,
        public array $error,
        public string $activityId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use DateTimeImmutable;

/**
 * Immutable snapshot of a commit. Once captured, author/committer metadata is
 * read-only.
 *
 * @api
 */
final readonly class Commit
{
    private function __construct(
        public CommitId $id,
        public string $subject,
        public ?string $body,
        public string $authorName,
        public string $authorEmail,
        public DateTimeImmutable $authorDate,
        public string $committerName,
        public string $committerEmail,
        public DateTimeImmutable $committerDate,
    ) {
    }

    public static function from(
        CommitId $id,
        string $subject,
        ?string $body,
        string $authorName,
        string $authorEmail,
        DateTimeImmutable $authorDate,
        string $committerName,
        string $committerEmail,
        DateTimeImmutable $committerDate,
    ): self {
        return new self(
            $id,
            $subject,
            $body,
            $authorName,
            $authorEmail,
            $authorDate,
            $committerName,
            $committerEmail,
            $committerDate,
        );
    }
}

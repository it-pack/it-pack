<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Immutable snapshot of a commit. Once captured, author/committer metadata is
 * read-only.
 *
 * @api
 */
final readonly class Commit
{
    public function __construct(
        public readonly CommitId $id,
        public readonly string $subject,
        public readonly ?string $body,
        public readonly string $authorName,
        public readonly string $authorEmail,
        public readonly \DateTimeImmutable $authorDate,
        public readonly string $committerName,
        public readonly string $committerEmail,
        public readonly \DateTimeImmutable $committerDate,
    ) {
    }
}

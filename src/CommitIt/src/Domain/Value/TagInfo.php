<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

use DateTimeImmutable;

/**
 * Immutable metadata describing a tag.
 *
 * @api
 */
final readonly class TagInfo
{
    public function __construct(
        public TagName $name,
        public CommitId $commitId,
        public TagType $type,
        public ?string $message,
        public ?string $taggerName,
        public ?string $taggerEmail,
        public ?DateTimeImmutable $date,
    ) {
    }
}

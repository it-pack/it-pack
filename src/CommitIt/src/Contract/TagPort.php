<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\TagName;

/**
 * Tag operations.
 *
 * @api
 */
interface TagPort
{
    public function createTag(TagName $name, ?CommitId $target = null): void;

    public function deleteTag(TagName $name): void;

    /**
     * @return list<TagName>
     */
    public function listTags(): array;
}

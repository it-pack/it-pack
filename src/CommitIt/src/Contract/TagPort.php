<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\TagCleanupOptions;
use ItPack\CommitIt\Domain\Value\CommitId;
use ItPack\CommitIt\Domain\Value\TagInfo;
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

    /**
     * Returns rich metadata for the given tag, or null when it does not exist.
     */
    public function getTagInfo(TagName $name): ?TagInfo;

    /**
     * Removes tags matching the given cleanup criteria.
     *
     * @return list<TagName> Tags that were removed.
     */
    public function cleanupTags(TagCleanupOptions $options): array;
}

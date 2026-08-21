<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Port for opening, initializing, and cloning repositories.
 *
 * Opening a path without a valid repository fails fast (it must never silently
 * initialize). Cloning into a non-empty directory fails fast.
 *
 * @api
 */
interface RepositoryFactory
{
    public function open(RepositoryPath $path): Repository;

    public function init(RepositoryPath $path, ?InitOptions $options = null): Repository;

    public function cloneRepository(string $url, RepositoryPath $path, ?CloneOptions $options = null): Repository;
}

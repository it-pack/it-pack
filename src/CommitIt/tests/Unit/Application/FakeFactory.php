<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryFactory;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Minimal {@see RepositoryFactory} test double that always returns a
 * {@see FakeRepository}.
 */
final class FakeFactory implements RepositoryFactory
{
    public Repository $lastOpened;

    public function __construct()
    {
        $this->lastOpened = new FakeRepository();
    }

    public function open(RepositoryPath $path): Repository
    {
        return $this->lastOpened;
    }

    public function init(RepositoryPath $path, ?InitOptions $options = null): Repository
    {
        return $this->lastOpened;
    }

    public function cloneRepository(string $url, RepositoryPath $path, ?CloneOptions $options = null): Repository
    {
        return $this->lastOpened;
    }
}

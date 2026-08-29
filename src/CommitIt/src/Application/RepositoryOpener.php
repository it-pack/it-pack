<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Application;

use ItPack\CommitIt\Contract\NullRepositoryObserver;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryFactory;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Wiring seam composing a {@see RepositoryFactory} with an optional
 * {@see RepositoryObserver}.
 *
 * When an observer is supplied the returned repository is an
 * {@see ObservedRepository}; with the default {@see NullRepositoryObserver} a
 * plain {@see Repository} is returned so there is zero observation overhead.
 *
 * @api
 */
final readonly class RepositoryOpener
{
    private readonly RepositoryObserver $observer;

    public function __construct(
        private readonly RepositoryFactory $factory,
        ?RepositoryObserver $observer = null,
    ) {
        $this->observer = $observer ?? new NullRepositoryObserver();
    }

    public function open(RepositoryPath $path): Repository
    {
        return $this->observe($this->factory->open($path));
    }

    public function init(RepositoryPath $path, ?InitOptions $options = null): Repository
    {
        return $this->observe($this->factory->init($path, $options));
    }

    public function cloneRepository(string $url, RepositoryPath $path, ?CloneOptions $options = null): Repository
    {
        return $this->observe($this->factory->cloneRepository($url, $path, $options));
    }

    private function observe(Repository $repository): Repository
    {
        if ($this->observer instanceof NullRepositoryObserver) {
            return $repository;
        }

        return new ObservedRepository($repository, $this->observer);
    }
}

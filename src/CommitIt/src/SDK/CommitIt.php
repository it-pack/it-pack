<?php

declare(strict_types=1);

namespace ItPack\CommitIt\SDK;

use ItPack\CommitIt\Application\RepositoryOpener;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryFactory;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Stateless, optional programmatic entry point for consumers.
 *
 * The facade composes a {@see RepositoryFactory} with an optional
 * {@see RepositoryObserver} through {@see RepositoryOpener} and returns a
 * vendor-neutral {@see Repository}. It holds no hidden state and performs no
 * global configuration: callers supply the backend (for example the git
 * adapter's factory) explicitly, which keeps the SDK agnostic of any concrete
 * adapter and satisfies the "thin facade" rule.
 *
 * @api
 */
final class CommitIt
{
    public static function open(RepositoryPath $path, RepositoryFactory $factory, ?RepositoryObserver $observer = null): Repository
    {
        return new RepositoryOpener($factory, $observer)->open($path);
    }

    public static function init(RepositoryPath $path, RepositoryFactory $factory, ?InitOptions $options = null, ?RepositoryObserver $observer = null): Repository
    {
        return new RepositoryOpener($factory, $observer)->init($path, $options);
    }

    /**
     * Clone a remote repository into the given path.
     *
     * @param non-empty-string $url
     */
    public static function clone(string $url, RepositoryPath $path, RepositoryFactory $factory, ?CloneOptions $options = null, ?RepositoryObserver $observer = null): Repository
    {
        return new RepositoryOpener($factory, $observer)->cloneRepository($url, $path, $options);
    }
}

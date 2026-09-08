<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Svn;

use ItPack\CommitIt\Adapter\Svn\Exception\SvnAdapterException;
use ItPack\CommitIt\Contract\ProcessRunner;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryFactory;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * SVN adapter entry point. Creates {@see SvnPhpRepository} instances bound to
 * the injected {@see ProcessRunner}.
 *
 * SVN has no `svn init` equivalent — `init()` checks out the repository URL
 * into the given path (behaving like {@see cloneRepository()}). `open()`
 * validates the path is an SVN working copy by running `svn info`.
 *
 * @api
 */
final readonly class SvnPhpRepositoryFactory implements RepositoryFactory
{
    public function __construct(
        private ProcessRunner $runner,
        private string $defaultUsername = '',
    ) {
    }

    public function open(RepositoryPath $path): SvnPhpRepository
    {
        $result = $this->runner->run(
            $path->realPath,
            ['svn', 'info', '--show-item', 'relative-url'],
        );

        if (!$result->isSuccess()) {
            throw new RepositoryNotFoundException(
                'Path is not an SVN working copy: ' . $path->toString(),
            );
        }

        $url = trim($result->stdout);

        return new SvnPhpRepository($this->runner, $path, $url, $this->defaultUsername);
    }

    public function init(RepositoryPath $path, ?InitOptions $options = null): SvnPhpRepository
    {
        throw new SvnAdapterException('SVN does not support local repository initialization. Use cloneRepository() instead.');
    }

    public function cloneRepository(
        string $url,
        RepositoryPath $path,
        ?CloneOptions $options = null,
    ): SvnPhpRepository {
        $options ??= CloneOptions::default();

        $command = ['svn', 'checkout', '--non-interactive'];

        if ($this->defaultUsername !== '') {
            $command[] = '--username';
            $command[] = $this->defaultUsername;
        }

        if ($options->depth !== null) {
            $command[] = '--depth';
            $command[] = (string) $options->depth;
        }

        $command[] = $url;
        $command[] = $path->toString();

        $this->runner->run($path->toString(), $command);

        return new SvnPhpRepository($this->runner, $path, $url, $this->defaultUsername);
    }
}

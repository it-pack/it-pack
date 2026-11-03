<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Git;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\Runners\CliRunner;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Contract\RepositoryFactory;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Exception\VcsOperationException;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\ConfigScope;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * Git adapter entry point. Builds a {@see Git} runner (optionally injected with a
 * test {@see IRunner}) and returns {@see GitPhpRepository} instances.
 *
 * Opening a path without a valid repository fails fast: `Git::open` only checks
 * the directory exists, so we additionally run `rev-parse --git-dir` to confirm
 * it is a real git repository before returning a handle.
 *
 * @api
 */
final readonly class GitPhpRepositoryFactory implements RepositoryFactory
{
    private readonly Git $git;

    public function __construct(?IRunner $runner = null)
    {
        $this->git = new Git(new LocalePinningRunner($runner ?? new CliRunner()));
    }

    public function open(RepositoryPath $path): Repository
    {
        try {
            $repository = $this->git->open($path->realPath);
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Failed to open repository at ' . $path->toString());
        }

        try {
            $repository->execute('rev-parse', '--git-dir');
        } catch (GitException $e) {
            throw new RepositoryNotFoundException(
                'Path is not a git repository: ' . $path->toString(),
                0,
                $e,
            );
        }

        return new GitPhpRepository($repository);
    }

    public function init(RepositoryPath $path, ?InitOptions $options = null): Repository
    {
        $options ??= InitOptions::default();

        $params = [];

        if ($options->bare) {
            $params[] = '--bare';
        }

        if ($options->initialBranch !== null) {
            $params['--initial-branch'] = $options->initialBranch;
        }

        try {
            $repository = $this->git->init($path->toString(), $params === [] ? null : $params);
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Failed to init repository at ' . $path->toString());
        }

        $gitPhpRepository = new GitPhpRepository($repository);

        try {
            $this->applyConfig($gitPhpRepository, $options->config);
        } catch (\Throwable $e) {
            $this->removeDirectory($path->toString());
            throw $e;
        }

        return $gitPhpRepository;
    }

    public function cloneRepository(string $url, RepositoryPath $path, ?CloneOptions $options = null): Repository
    {
        $options ??= CloneOptions::default();

        $params = [];

        if ($options->bare) {
            $params[] = '--bare';
        }

        if ($options->branch !== null) {
            $params['--branch'] = $options->branch;
        }

        if ($options->depth !== null) {
            $params['--depth'] = (string) $options->depth;
        }

        try {
            $repository = $this->git->cloneRepository($url, $path->toString(), $params === [] ? null : $params);
        } catch (GitException $e) {
            throw GitExceptionMap::map($e, 'Failed to clone repository into ' . $path->toString());
        }

        $gitPhpRepository = new GitPhpRepository($repository);

        try {
            $this->applyConfig($gitPhpRepository, $options->config);
        } catch (\Throwable $e) {
            $this->removeDirectory($path->toString());
            throw $e;
        }

        return $gitPhpRepository;
    }

    /**
     * @param array<int|string, array<string, string>> $config keyed by ConfigScope->value string
     */
    private function applyConfig(GitPhpRepository $repository, array $config): void
    {
        foreach ($config as $scopeKey => $keyValues) {
            $scope = ConfigScope::from((string) $scopeKey);

            if ($scope === ConfigScope::File) {
                throw new VcsOperationException(
                    'ConfigScope::File is not supported through RepositoryFactory; use Repository::set() directly with an explicit file path.',
                );
            }

            foreach ($keyValues as $key => $value) {
                $repository->set($key, $value, $scope);
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}

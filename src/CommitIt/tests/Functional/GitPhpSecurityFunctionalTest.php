<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\CommitIt\Adapter\Git\GitPhpRepository;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Exception\PathScopeViolationException;
use ItPack\CommitIt\Domain\Exception\SecurityViolationException;
use ItPack\CommitIt\Domain\Service\DefaultSecretScanner;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use Tests\Support\Helper\GitRepoFactory;

/**
 * Functional coverage of security integration in the git adapter.
 *
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepository
 * @covers \ItPack\CommitIt\Domain\Service\DefaultSecretScanner
 */
final class GitPhpSecurityFunctionalTest extends Unit
{
    private function open(string $path): Repository
    {
        return new GitPhpRepositoryFactory()->open(RepositoryPath::from($path));
    }

    public function testCommitWithSecretKeyThrowsSecurityViolation(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-sec');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $factory = new GitPhpRepositoryFactory();

            $repository = $this->openWithScanner($factory, RepositoryPath::from($repo));

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'hello');
            $repository->stage('a.txt');

            $this->expectException(SecurityViolationException::class);
            $repository->commit(CommitMessage::fromParts('Fix: apiKey=sk-123456789012'));
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testCommitWithoutSecretSucceeds(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-sec-ok');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $factory = new GitPhpRepositoryFactory();

            $repository = $this->openWithScanner($factory, RepositoryPath::from($repo));

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'hello');
            $repository->stage('a.txt');

            $id = $repository->commit(CommitMessage::fromParts('Add a.txt'));

            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id->__toString());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testCommitWithoutScannerAllowsAnyMessage(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-no-scanner');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            file_put_contents($repo . DIRECTORY_SEPARATOR . 'a.txt', 'hello');
            $repository->stage('a.txt');

            $id = $repository->commit(CommitMessage::fromParts('Fix: apiKey=sk-123456789012'));

            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $id->__toString());
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testStagePathTraversalRejected(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-traversal');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $this->expectException(PathScopeViolationException::class);
            $repository->stage('../../../etc/passwd');
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    public function testStageAbsolutePathOutsideRepoRejected(): void
    {
        $repo = GitRepoFactory::createTempDir('commitit-abs-path');

        try {
            GitRepoFactory::initWorkingRepo($repo, 'main');
            $repository = $this->open($repo);

            $this->expectException(PathScopeViolationException::class);
            $repository->stage('/etc/passwd');
        } finally {
            GitRepoFactory::tearDown($repo);
        }
    }

    private function openWithScanner(GitPhpRepositoryFactory $factory, RepositoryPath $path): GitPhpRepository
    {
        $git = new \CzProject\GitPhp\Git(
            new \CzProject\GitPhp\Runners\CliRunner(),
        );

        $repository = $git->open($path->realPath);

        try {
            $repository->execute('rev-parse', '--git-dir');
        } catch (\CzProject\GitPhp\GitException $e) {
            throw new \ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException(
                'Path is not a git repository: ' . $path->toString(),
                0,
                $e,
            );
        }

        return new GitPhpRepository(
            $repository,
            null,
            new DefaultSecretScanner(),
        );
    }
}

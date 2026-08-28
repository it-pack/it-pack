<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Git;

use Codeception\Test\Unit;
use CzProject\GitPhp\Runners\MemoryRunner;
use ItPack\CommitIt\Adapter\Git\GitPhpRepository;
use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Value\RepositoryPath;

/**
 * @covers \ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory
 * @covers \ItPack\CommitIt\Adapter\Git\GitExceptionMap
 */
final class GitPhpRepositoryFactoryTest extends Unit
{
    use GitAdapterMemoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRepoDir();
    }

    protected function tearDown(): void
    {
        $this->tearDownRepoDir();
        parent::tearDown();
    }

    public function testOpenMissingPathThrowsRepositoryNotFound(): void
    {
        $factory = new GitPhpRepositoryFactory(new MemoryRunner(sys_get_temp_dir()));

        $this->expectException(RepositoryNotFoundException::class);
        $factory->open(RepositoryPath::from('/path/that/does/not/exist/commitit_' . bin2hex(random_bytes(4))));
    }

    public function testOpenExistingNonRepoThrowsRepositoryNotFound(): void
    {
        $runner = new MemoryRunner($this->repoDir);
        $runner->setResult(['rev-parse', '--git-dir'], $this->pinnedEnv(), '', 'fatal: not a git repository', 128);

        $factory = new GitPhpRepositoryFactory($runner);

        $this->expectException(RepositoryNotFoundException::class);
        $factory->open(RepositoryPath::from($this->repoDir));
    }

    public function testInitCreatesRepository(): void
    {
        $runner = new MemoryRunner($this->repoDir);
        $runner->setResult(['init', null, '--end-of-options', $this->repoDir], $this->pinnedEnv(), '');

        $factory = new GitPhpRepositoryFactory($runner);
        $repository = $factory->init(RepositoryPath::from($this->repoDir));

        self::assertInstanceOf(GitPhpRepository::class, $repository);
        self::assertSame($this->repoDir, $repository->getPath()->toString());
    }

    public function testCloneCreatesRepository(): void
    {
        $url = 'https://example.com/repo.git';
        $runner = new MemoryRunner($this->repoDir);
        $runner->setResult(['clone', '-q', '--end-of-options', $url, $this->repoDir], $this->pinnedEnv(), '');

        $factory = new GitPhpRepositoryFactory($runner);
        $repository = $factory->cloneRepository($url, RepositoryPath::from($this->repoDir));

        self::assertInstanceOf(GitPhpRepository::class, $repository);
        self::assertSame($this->repoDir, $repository->getPath()->toString());
    }

    /**
     * Mirrors the locale-pinned environment {@see \ItPack\CommitIt\Adapter\Git\LocalePinningRunner}
     * injects, so `MemoryRunner` (which keys results by the env array) matches the command the
     * adapter actually dispatches. Resolved per process so it equals the runner's own view.
     *
     * @return array<string, string>
     */
    private function pinnedEnv(): array
    {
        /** @var array<string, string> $env */
        $env = getenv();

        $env['LC_ALL'] = 'C';
        $env['LANG'] = 'C';

        return $env;
    }
}
